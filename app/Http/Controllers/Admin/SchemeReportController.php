<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Operational (non-accounting) reports:
 * Customer report, Scheme report, Collection, Pending, Agent performance,
 * Joiners.
 */
class SchemeReportController extends Controller
{
    protected function range(Request $request): array
    {
        return [
            $request->from ?: now()->startOfMonth()->toDateString(),
            $request->to ?: now()->toDateString(),
        ];
    }

    /** ALL CUSTOMER report — payment-wise like the old "Customer Report" screen */
    public function customers(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rows = Payment::with(['customer:id,name,reg_no,phone', 'customerScheme:id,account_no'])
            ->approved()
            ->whereBetween('payment_date', [$from, $to])
            ->when($request->customer_id, fn ($q, $v) => $q->where('customer_id', $v))
            ->orderByDesc('payment_date')
            ->paginate(50)->withQueryString();

        $summary = Payment::approved()->whereBetween('payment_date', [$from, $to])
            ->selectRaw('COUNT(*) c, SUM(amount) amount, SUM(tax) tax, SUM(total) total, SUM(gram) gram')
            ->first();

        return view('admin.reports.customers', compact('rows', 'from', 'to', 'summary'));
    }

    /**
     * JOINERS — who came in, and through which door.
     *
     * Defaults to today, because the question staff actually ask in the
     * morning is "who joined?". A signup made in the app, one an agent took on
     * their round and one typed in at the counter used to be indistinguishable
     * once saved, so there was no way to see what the app was bringing in.
     */
    public function joiners(Request $request)
    {
        $from = $request->from ?: now()->toDateString();
        $to = $request->to ?: now()->toDateString();
        $source = $request->source ?: null;

        $customers = Customer::with(['agent:id,name'])
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($source, fn ($q, $v) => $q->where('source', $v))
            ->orderByDesc('created_at')
            ->paginate(50, ['*'], 'c_page')->withQueryString();

        $enrolments = CustomerScheme::with([
            'customer:id,name,reg_no,phone',
            'scheme:id,name',
            'agent:id,name',
        ])
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($source, fn ($q, $v) => $q->where('source', $v))
            ->orderByDesc('created_at')
            ->paginate(50, ['*'], 's_page')->withQueryString();

        // Counted separately from the paginated lists so the totals cover the
        // whole range, not just the page on screen.
        $bySource = fn (string $model) => $model::query()
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('source, COUNT(*) c')
            ->groupBy('source')
            ->pluck('c', 'source');

        $summary = [
            'customers' => $bySource(Customer::class),
            'enrolments' => $bySource(CustomerScheme::class),
        ];

        return view('admin.reports.joiners', compact(
            'customers', 'enrolments', 'summary', 'from', 'to', 'source'
        ));
    }

    /** SCHEME REPORT — per enrollment totals */
    public function schemes(Request $request)
    {
        $rows = CustomerScheme::with(['customer:id,name,reg_no,phone', 'scheme:id,name'])
            ->when($request->scheme_id, fn ($q, $v) => $q->where('scheme_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            // Staff are handed a book number over the counter, so it has to be
            // a way into this report and not just a column to read.
            ->when($request->book_no, fn ($q, $v) => $q->where('book_no', 'like', "%$v%"))
            ->orderByDesc('total_paid')
            ->paginate(50)->withQueryString();

        $schemes = Scheme::orderBy('name')->get(['id', 'name']);

        $summary = CustomerScheme::selectRaw('COUNT(*) c, SUM(total_paid) paid, SUM(total_gst) gst, SUM(total_gram) gram')
            ->when($request->scheme_id, fn ($q, $v) => $q->where('scheme_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->book_no, fn ($q, $v) => $q->where('book_no', 'like', "%$v%"))
            ->first();

        return view('admin.reports.schemes', compact('rows', 'schemes', 'summary'));
    }

    /** Day-wise / mode-wise collection */
    public function collection(Request $request)
    {
        [$from, $to] = $this->range($request);

        $daily = Payment::approved()
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_date, COUNT(*) c, SUM(amount) amount, SUM(tax) tax, SUM(total) total, SUM(gram) gram')
            ->groupBy('payment_date')
            ->orderBy('payment_date')
            ->get();

        $byMode = Payment::approved()
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_mode, COUNT(*) c, SUM(total) total')
            ->groupBy('payment_mode')->get();

        $byAgent = Payment::approved()
            ->whereBetween('payment_date', [$from, $to])
            ->join('agents', 'agents.id', '=', 'payments.agent_id')
            ->selectRaw('agents.name, agents.agent_code, COUNT(*) c, SUM(payments.total) total')
            ->groupBy('agents.id', 'agents.name', 'agents.agent_code')
            ->orderByDesc('total')->get();

        return view('admin.reports.collection', compact('daily', 'byMode', 'byAgent', 'from', 'to'));
    }

    /** PAYMENT PENDING — outstanding installments */
    public function pending(Request $request)
    {
        $rows = Installment::with(['customerScheme.customer:id,name,reg_no,phone', 'customerScheme.scheme:id,name'])
            ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->whereDate('due_date', '<=', $request->as_on ?: now()->toDateString())
            ->orderBy('due_date')
            ->paginate(50)->withQueryString();

        $total = Installment::whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->whereDate('due_date', '<=', $request->as_on ?: now()->toDateString())
            ->sum(DB::raw('amount - paid_amount'));

        return view('admin.reports.pending', compact('rows', 'total'));
    }

    /** AGENT PAY — pending amount to collect from agents */
    public function agentPending(Request $request)
    {
        $rows = Agent::active()
            ->withSum(['payments as collected' => fn ($q) => $q->where('status', 'APPROVED')], 'total')
            ->withCount('customers')
            ->orderByDesc('collected')
            ->get()
            ->map(function ($a) {
                $earned = round((float) $a->collected * $a->commission_percent / 100, 2);
                $paid = (float) $a->payouts()->where('status', 'PAID')->sum('amount');

                return [
                    'agent' => $a,
                    'collected' => (float) $a->collected,
                    'earned' => $earned,
                    'paid' => $paid,
                    'pending' => round($earned - $paid, 2),
                ];
            });

        return view('admin.reports.agent-pending', compact('rows'));
    }

    /** Gram-wise summary per customer */
    public function gramSummary(Request $request)
    {
        $rows = Customer::withSum(['payments as total_amount' => fn ($q) => $q->where('status', 'APPROVED')], 'amount')
            ->withSum(['payments as total_tax' => fn ($q) => $q->where('status', 'APPROVED')], 'tax')
            ->withSum(['payments as gram_sum' => fn ($q) => $q->where('status', 'APPROVED')], 'gram')
            ->withCount(['payments as payment_count' => fn ($q) => $q->where('status', 'APPROVED')])
            ->having('payment_count', '>', 0)
            ->orderByDesc('gram_sum')
            ->paginate(50);

        return view('admin.reports.gram-summary', compact('rows'));
    }
}
