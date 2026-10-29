<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accountm;
use App\Models\Daybookpart;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function __construct(protected AccountingService $accounts) {}

    public function index(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $vouchers = Daybookpart::with('lines.account:accode,name')
            ->whereBetween('voucher_date', [$from, $to])
            ->when($request->voucher_type, fn ($q, $v) => $q->where('voucher_type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('voucher_no', 'like', "%$v%")
                ->orWhere('narration', 'like', "%$v%")
                ->orWhere('reference', 'like', "%$v%")))
            ->orderByDesc('voucher_date')->orderByDesc('sno')
            ->paginate(25)->withQueryString();

        $types = config('scheme.voucher_types');

        return view('accounts.vouchers.index', compact('vouchers', 'from', 'to', 'types'));
    }

    public function create(Request $request)
    {
        $ledgers = Accountm::active()->orderBy('name')->get(['accode', 'name', 'actype']);
        $types = config('scheme.voucher_types');
        $type = $request->type ?: 'JOURNAL';

        return view('accounts.vouchers.form', [
            'voucher' => null,
            'ledgers' => $ledgers,
            'types' => $types,
            'type' => $type,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'voucher_type' => ['required', Rule::in(array_keys(config('scheme.voucher_types')))],
            'voucher_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:60'],
            'narration' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.accode' => ['required', 'exists:accountm,accode'],
            'lines.*.drcr' => ['required', Rule::in(['D', 'C'])],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        // Build signed lines
        $lines = [];
        foreach ($data['lines'] as $l) {
            $amt = round(abs((float) $l['amount']), 2);
            if ($amt < 0.005) {
                continue;
            }
            $lines[] = [
                'accode' => $l['accode'],
                'amount' => $l['drcr'] === 'D' ? $amt : -$amt,
                'remarks' => $l['remarks'] ?? null,
            ];
        }

        // Auto fill opaccode for a simple 2-line voucher
        if (count($lines) === 2) {
            $lines[0]['opaccode'] = $lines[1]['accode'];
            $lines[1]['opaccode'] = $lines[0]['accode'];
        } else {
            // multi-line: opaccode = the biggest opposite-side account
            $dr = collect($lines)->where('amount', '>', 0)->sortByDesc('amount')->first();
            $cr = collect($lines)->where('amount', '<', 0)->sortBy('amount')->first();
            foreach ($lines as $i => $l) {
                $lines[$i]['opaccode'] = $l['amount'] > 0 ? ($cr['accode'] ?? null) : ($dr['accode'] ?? null);
            }
        }

        try {
            $voucher = $this->accounts->post([
                'voucher_type' => $data['voucher_type'],
                'voucher_date' => $data['voucher_date'],
                'reference' => $data['reference'] ?? null,
                'narration' => $data['narration'] ?? null,
                'source_type' => 'manual',
                'created_by' => $request->user()->id,
            ], $lines);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['lines' => $e->getMessage()]);
        }

        return redirect()->route('accounts.vouchers.show', $voucher->sno)
            ->with('success', 'Voucher '.$voucher->voucher_no.' posted.');
    }

    public function show(int $sno)
    {
        $voucher = Daybookpart::with(['lines.account:accode,name', 'lines.oppositeAccount:accode,name', 'creator:id,name'])
            ->findOrFail($sno);

        return view('accounts.vouchers.show', compact('voucher'));
    }

    public function cancel(Request $request, int $sno)
    {
        $this->accounts->cancel($sno, $request->input('reason'));

        return back()->with('success', 'Voucher cancelled.');
    }

    public function destroy(int $sno)
    {
        $voucher = Daybookpart::findOrFail($sno);

        if ($voucher->source_type !== 'manual') {
            return back()->withErrors(['error' => 'Auto generated voucher — cancel it instead of deleting.']);
        }

        $voucher->lines()->delete();
        $voucher->delete();

        return redirect()->route('accounts.vouchers.index')->with('success', 'Voucher deleted.');
    }
}
