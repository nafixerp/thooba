<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Metal;
use App\Models\MetalRate;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Today's metal rates, set from the dashboard.
 *
 * The rate moves every morning and the whole app quotes it - the customer's
 * gold balance, the scheme cards, the jewellery prices. There was no screen
 * for it at all, so it could only be changed by editing the database by hand.
 */
class RateController extends Controller
{
    /** POST /admin/rates */
    public function store(Request $request)
    {
        $data = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*' => ['nullable', 'numeric', 'min:0'],
            'rate_date' => ['nullable', 'date'],
            'notify' => ['nullable', 'boolean'],
        ]);

        $date = $data['rate_date'] ?? now()->toDateString();
        $changed = [];

        DB::transaction(function () use ($request, $data, $date, &$changed) {
            foreach ($data['rates'] as $metalId => $rate) {
                if ($rate === null || $rate === '') {
                    continue;
                }

                $metal = Metal::find($metalId);
                if (! $metal) {
                    continue;
                }

                $rate = round((float) $rate, 2);
                $previous = MetalRate::latest_rate((int) $metalId, $date);

                // Nothing to record or announce when the rate has not moved.
                if (abs($previous - $rate) < 0.005) {
                    continue;
                }

                // One row per metal per day: the morning rate gets corrected
                // during the day, and a second row would leave two "latest"
                // rates fighting over the same date.
                //
                // Matched with whereDate rather than updateOrCreate: the cast
                // writes "2026-09-28 00:00:00", which a DATE column truncates
                // but SQLite keeps, so an exact key match found nothing there
                // and quietly inserted a duplicate.
                $row = MetalRate::where('metal_id', $metal->id)
                    ->whereDate('rate_date', $date)
                    ->first();

                $values = [
                    'rate' => $rate,
                    'rate_time' => now()->format('H:i:s'),
                    'status' => true,
                    'created_by' => $request->user()?->id,
                ];

                $row
                    ? $row->update($values)
                    : MetalRate::create($values + ['metal_id' => $metal->id, 'rate_date' => $date]);

                $changed[] = [
                    'metal' => $metal,
                    'rate' => $rate,
                    'previous' => $previous,
                ];
            }
        });

        if (! $changed) {
            return back()->with('success', 'Rates are already up to date.');
        }

        $sent = $request->boolean('notify', true)
            ? $this->notifyCustomers($changed, $date)
            : 0;

        $summary = collect($changed)
            ->map(fn ($c) => $c['metal']->name.' '.number_format($c['rate'], 2))
            ->implode(', ');

        return back()->with('success', $sent
            ? "Rate updated - {$summary}. {$sent} customers notified."
            : "Rate updated - {$summary}.");
    }

    /**
     * Drop a notification in every active customer's app.
     *
     * The app polls GET /notifications, so writing the row is what makes it
     * appear. There is no push delivery yet, which means a customer sees this
     * the next time they open the app rather than on their lock screen.
     *
     * @param  array<int, array{metal: Metal, rate: float, previous: float}>  $changed
     */
    protected function notifyCustomers(array $changed, string $date): int
    {
        $lines = collect($changed)->map(function ($c) {
            $move = $c['previous'] > 0
                ? ($c['rate'] > $c['previous'] ? ' ▲' : ' ▼')
                : '';

            return $c['metal']->name.' ₹'.number_format($c['rate'], 2)
                .'/'.strtolower($c['metal']->unit ?? 'gram').$move;
        })->implode(' · ');

        $rows = [];
        $now = now();

        Customer::where('status', true)
            ->select('id')
            ->chunkById(500, function ($customers) use (&$rows, $lines, $date, $now) {
                foreach ($customers as $customer) {
                    $rows[] = [
                        'notifiable_type' => Customer::class,
                        'notifiable_id' => $customer->id,
                        'title' => "Today's gold rate",
                        'message' => $lines,
                        'type' => 'GOLD_RATE',
                        // One rate announcement per customer per day, so a
                        // correction at noon replaces the morning one instead
                        // of stacking up in their list.
                        'dedupe_key' => "rate:{$date}:{$customer->id}",
                        'data' => json_encode(['rate_date' => $date]),
                        // A correction later in the day should surface again
                        // even for someone who already read the morning one.
                        'read_at' => null,
                        'sent_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });

        foreach (array_chunk($rows, 500) as $batch) {
            Notification::upsert(
                $batch,
                ['dedupe_key'],
                ['title', 'message', 'data', 'sent_at', 'read_at', 'updated_at'],
            );
        }

        return count($rows);
    }
}
