<?php

namespace App\Services;

use App\Models\Accountg;
use App\Models\Accountgbs;
use App\Models\Accountm;
use App\Models\Daybook;
use App\Models\Daybookpart;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * -----------------------------------------------------------------------------
 *  FinancialReportService
 * -----------------------------------------------------------------------------
 *
 *   daybook
 *      |
 *      +--> General Ledger      (one account, date-wise, running balance)
 *      +--> Trial Balance       (all accounts, opening / dr / cr / closing)
 *      +--> Profit & Loss       (bshead = PL)
 *      +--> Balance Sheet       (bshead = BS  + net profit)
 *
 *  Sign convention everywhere:  + = Debit ,  - = Credit
 */
class FinancialReportService
{
    /* =========================================================================
     |  1. GENERAL LEDGER  (Account statement)
     ========================================================================= */

    public function generalLedger(string $accode, string $from, string $to): array
    {
        $account = Accountm::with('group.base')->findOrFail($accode);

        $opening = $this->openingBalance($accode, $from);

        $rows = Daybook::query()
            ->with('oppositeAccount:accode,name')
            ->where('accode', $accode)
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->orderBy('voucher_date')
            ->orderBy('slno')
            ->get();

        $running = $opening;
        $totalDr = 0.0;
        $totalCr = 0.0;
        $lines = [];

        foreach ($rows as $r) {
            $running += (float) $r->amount;
            $totalDr += (float) $r->dr_amount;
            $totalCr += (float) $r->cr_amount;

            $lines[] = [
                'slno' => $r->slno,
                'sno' => $r->sno,
                'date' => $r->voucher_date->format('d-m-Y'),
                'voucher_type' => $r->voucher_type,
                'voucher_no' => $r->voucher_no,
                'particulars' => $r->oppositeAccount?->name ?? ($r->remarks ?: 'Journal'),
                'opaccode' => $r->opaccode,
                'remarks' => $r->remarks,
                'debit' => (float) $r->dr_amount,
                'credit' => (float) $r->cr_amount,
                'balance' => round($running, 2),
                'balance_side' => $running >= 0 ? 'Dr' : 'Cr',
            ];
        }

        return [
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'opening' => round($opening, 2),
            'opening_side' => $opening >= 0 ? 'Dr' : 'Cr',
            'lines' => $lines,
            'total_debit' => round($totalDr, 2),
            'total_credit' => round($totalCr, 2),
            'closing' => round($running, 2),
            'closing_side' => $running >= 0 ? 'Dr' : 'Cr',
        ];
    }

    /** Opening (signed) of an account before $from */
    public function openingBalance(string $accode, string $from): float
    {
        $acc = Accountm::find($accode);
        if (! $acc) {
            return 0.0;
        }

        $prior = (float) Daybook::where('accode', $accode)
            ->where('status', 'POSTED')
            ->where('voucher_date', '<', $from)
            ->sum('amount');

        return round($acc->signed_opening + $prior, 2);
    }

    /* =========================================================================
     |  2. TRIAL BALANCE
     ========================================================================= */

    /**
     * @return array{rows: array, totals: array, balanced: bool}
     */
    public function trialBalance(string $from, string $to, bool $hideZero = true): array
    {
        $accounts = Accountm::with('group.base')
            ->where('status', true)
            ->orderBy('grcode')
            ->orderBy('accode')
            ->get();

        // Movement inside the period
        $period = Daybook::selectRaw('accode,
                SUM(dr_amount) as dr,
                SUM(cr_amount) as cr,
                SUM(amount) as net')
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->groupBy('accode')
            ->get()
            ->keyBy('accode');

        // Everything before the period
        $before = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->where('voucher_date', '<', $from)
            ->groupBy('accode')
            ->get()
            ->keyBy('accode');

        $rows = [];
        $t = ['opening_dr' => 0, 'opening_cr' => 0, 'dr' => 0, 'cr' => 0, 'closing_dr' => 0, 'closing_cr' => 0];

        foreach ($accounts as $a) {
            $opening = round($a->signed_opening + (float) ($before[$a->accode]->net ?? 0), 2);
            $dr = (float) ($period[$a->accode]->dr ?? 0);
            $cr = (float) ($period[$a->accode]->cr ?? 0);
            $closing = round($opening + $dr - $cr, 2);

            if ($hideZero && abs($opening) < 0.005 && abs($dr) < 0.005 && abs($cr) < 0.005) {
                continue;
            }

            $row = [
                'accode' => $a->accode,
                'name' => $a->name,
                'grcode' => $a->grcode,
                'group' => $a->group?->name,
                'gbscode' => $a->group?->gbscode,
                'base' => $a->group?->base?->name,
                'bshead' => $a->bshead,
                'opening_dr' => $opening > 0 ? $opening : 0,
                'opening_cr' => $opening < 0 ? abs($opening) : 0,
                'debit' => $dr,
                'credit' => $cr,
                'closing_dr' => $closing > 0 ? $closing : 0,
                'closing_cr' => $closing < 0 ? abs($closing) : 0,
                'closing' => $closing,
            ];

            foreach (['opening_dr', 'opening_cr', 'closing_dr', 'closing_cr'] as $k) {
                $t[$k] += $row[$k];
            }
            $t['dr'] += $dr;
            $t['cr'] += $cr;

            $rows[] = $row;
        }

        $t = array_map(fn ($v) => round($v, 2), $t);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => $t,
            'balanced' => abs($t['dr'] - $t['cr']) < 0.05
                       && abs($t['closing_dr'] - $t['closing_cr']) < 0.05,
            'difference' => round($t['closing_dr'] - $t['closing_cr'], 2),
        ];
    }

    /* =========================================================================
     |  3. PROFIT & LOSS
     ========================================================================= */

    /**
     * Income accounts carry credit balances (negative), expenses debit (positive).
     * Net profit = Income - Expense.
     */
    public function profitAndLoss(string $from, string $to): array
    {
        $balances = $this->groupedBalances($from, $to, 'PL');

        $income = $balances->filter(fn ($g) => in_array($g['nature'], ['INCOME']))->values();
        $expense = $balances->filter(fn ($g) => in_array($g['nature'], ['EXPENSE']))->values();

        // income closing is negative (Cr) -> flip to a positive figure
        $totalIncome = round($income->sum(fn ($g) => -$g['total']), 2);
        $totalExpense = round($expense->sum(fn ($g) => $g['total']), 2);
        $net = round($totalIncome - $totalExpense, 2);

        return [
            'from' => $from,
            'to' => $to,
            'income' => $this->flipSign($income),
            'expense' => $expense->all(),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => $net,
            'is_profit' => $net >= 0,
            'grand_total' => round(max($totalIncome, $totalExpense + max($net, 0)), 2),
        ];
    }

    /* =========================================================================
     |  4. BALANCE SHEET
     ========================================================================= */

    public function balanceSheet(string $asOn, ?string $fyFrom = null): array
    {
        $fyFrom ??= app(AccountingService::class)->financialYearRange($asOn)[0]->toDateString();

        $balances = $this->groupedBalances('1900-01-01', $asOn, 'BS');

        // Closing stock is valued, not posted, so the asset side has to move by
        // the same figure the Trading A/c credited. See TradingAccountService.
        $trading = app(TradingAccountService::class);
        $stockAdj = $trading->stockAdjustment($asOn);

        if (abs($stockAdj) > 0.005) {
            $balances = $this->applyStockAdjustment($balances, $stockAdj);
        }

        $assets = $balances->filter(fn ($g) => $g['nature'] === 'ASSET')->values();
        $liabilities = $balances->filter(fn ($g) => in_array($g['nature'], ['LIABILITY']))->values();

        $totalAssets = round($assets->sum(fn ($g) => $g['total']), 2);
        // liabilities are credit (negative) -> flip
        $totalLiab = round($liabilities->sum(fn ($g) => -$g['total']), 2);

        // Net profit of the current financial year goes to the liability side.
        // It comes from the Trading + P&L statement so the two always agree.
        $netProfit = $trading->netProfit($fyFrom, $asOn);

        $liabSide = $this->flipSign($liabilities);
        $liabSide[] = [
            'grcode' => 'PL',
            'group' => $netProfit >= 0 ? 'Profit & Loss A/c (Current Year Profit)' : 'Profit & Loss A/c (Current Year Loss)',
            'gbscode' => 'PL',
            'nature' => 'LIABILITY',
            'total' => $netProfit,
            'accounts' => [],
            'is_computed' => true,
        ];

        $totalLiabWithPl = round($totalLiab + $netProfit, 2);

        return [
            'as_on' => $asOn,
            'fy_from' => $fyFrom,
            'assets' => $assets->all(),
            'liabilities' => $liabSide,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabWithPl,
            'net_profit' => $netProfit,
            'stock_adjustment' => $stockAdj,
            'difference' => round($totalAssets - $totalLiabWithPl, 2),
            'balanced' => abs($totalAssets - $totalLiabWithPl) < 0.05,
        ];
    }

    /**
     * Schedules to the Balance Sheet - the legacy d_balsheet_schedule.
     *
     * Every Balance Sheet figure is a one line total on the face of the sheet;
     * this is the backing detail, grouped by accountm.schedule_group so the
     * office can print "Schedule 3 - Sundry Debtors" and see the parties.
     */
    public function balanceSheetSchedules(string $asOn): array
    {
        $accounts = Accountm::with('group.base')
            ->where('bshead', 'BS')
            ->where('status', true)
            ->where('print_in_reports', true)
            ->orderBy('schedule_pos')
            ->orderBy('name')
            ->get();

        $net = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->where('voucher_date', '<=', $asOn)
            ->groupBy('accode')
            ->pluck('net', 'accode');

        $schedules = [];

        foreach ($accounts as $a) {
            $bal = round($a->signed_opening + (float) ($net[$a->accode] ?? 0), 2);

            if (abs($bal) < 0.005) {
                continue;
            }

            $key = $a->schedule_group ?: ($a->group?->name ?? 'Unclassified');

            $schedules[$key] ??= [
                'name' => $key,
                'nature' => $a->group?->base?->nature ?? 'ASSET',
                'pos' => $a->schedule_pos ?: ($a->group?->sortorder ?? 999),
                'expand' => (bool) ($a->group?->base?->expand ?? true),
                'total' => 0.0,
                'accounts' => [],
            ];

            $schedules[$key]['total'] = round($schedules[$key]['total'] + $bal, 2);
            $schedules[$key]['accounts'][] = [
                'accode' => $a->accode,
                'name' => $a->name,
                'balance' => $bal,
                'side' => $bal > 0 ? 'Dr' : 'Cr',
            ];
        }

        $rows = collect($schedules)->sortBy('pos')->values();

        return [
            'as_on' => $asOn,
            'schedules' => $rows->all(),
            'total_debit' => round($rows->sum(fn ($s) => max($s['total'], 0)), 2),
            'total_credit' => round($rows->sum(fn ($s) => max(-$s['total'], 0)), 2),
        ];
    }

    /**
     * Move the difference between the valued closing stock and the stock
     * ledger's book balance onto the asset side, as its own line.
     */
    protected function applyStockAdjustment(Collection $groups, float $adjustment): Collection
    {
        $stockAccode = config('scheme.accounts.stock');
        $stockGrcode = Accountm::find($stockAccode)?->grcode;

        return $groups->map(function ($g) use ($stockGrcode, $adjustment) {
            if ($g['grcode'] !== $stockGrcode) {
                return $g;
            }

            $g['total'] = round($g['total'] + $adjustment, 2);
            $g['accounts'][] = [
                'accode' => TradingAccountService::CLOSING_STOCK,
                'name' => 'Closing Stock (as valued)',
                'balance' => $adjustment,
                'is_computed' => true,
            ];

            return $g;
        })->when(
            // The stock group drops out of groupedBalances when its ledger sits
            // at zero, so the adjustment needs a group of its own.
            ! $groups->contains(fn ($g) => $g['grcode'] === $stockGrcode),
            fn ($c) => $c->push([
                'grcode' => $stockGrcode ?? 'STOCK',
                'group' => 'Stock in Hand',
                'gbscode' => '1',
                'nature' => 'ASSET',
                'sortorder' => 16,
                'total' => round($adjustment, 2),
                'accounts' => [[
                    'accode' => TradingAccountService::CLOSING_STOCK,
                    'name' => 'Closing Stock (as valued)',
                    'balance' => round($adjustment, 2),
                    'is_computed' => true,
                ]],
            ])
        )->values();
    }

    /* =========================================================================
     |  5. DAYBOOK / CASH & BANK BOOK
     ========================================================================= */

    public function dayBook(string $from, string $to, ?string $voucherType = null): Collection
    {
        return Daybook::with(['account:accode,name', 'oppositeAccount:accode,name'])
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->when($voucherType, fn ($q) => $q->where('voucher_type', $voucherType))
            ->orderBy('voucher_date')
            ->orderBy('sno')
            ->orderBy('line_no')
            ->get();
    }

    public function cashBook(string $from, string $to): array
    {
        return $this->generalLedger(config('scheme.accounts.cash'), $from, $to);
    }

    public function bankBook(string $from, string $to): array
    {
        return $this->generalLedger(config('scheme.accounts.bank'), $from, $to);
    }

    /** Outstanding party balances (customers or agents) */
    public function outstanding(string $actype, string $asOn): array
    {
        $accounts = Accountm::where('actype', $actype)->where('status', true)->get();

        $net = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->where('voucher_date', '<=', $asOn)
            ->groupBy('accode')
            ->pluck('net', 'accode');

        $rows = [];
        foreach ($accounts as $a) {
            $bal = round($a->signed_opening + (float) ($net[$a->accode] ?? 0), 2);
            if (abs($bal) < 0.005) {
                continue;
            }
            $rows[] = [
                'accode' => $a->accode,
                'name' => $a->name,
                'phone' => $a->phone,
                'balance' => $bal,
                'side' => $bal > 0 ? 'Dr' : 'Cr',
            ];
        }

        usort($rows, fn ($x, $y) => abs($y['balance']) <=> abs($x['balance']));

        return $rows;
    }

    /* =========================================================================
     |  INTERNAL
     ========================================================================= */

    /**
     * Closing balances grouped by accountg, for a bshead ('BS' or 'PL').
     */
    protected function groupedBalances(string $from, string $to, string $bshead): Collection
    {
        $accounts = Accountm::with('group.base', 'group.flipGroup.base')
            ->where('bshead', $bshead)
            ->where('status', true)
            ->where('print_in_reports', true)
            ->get();

        $net = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->groupBy('accode')
            ->pluck('net', 'accode');

        // For P&L only the period movement counts; for BS the opening also counts.
        $groups = [];
        $autoCorrect = $bshead === 'BS' && config('scheme.sundry_auto_correct', true);

        foreach ($accounts as $a) {
            $bal = (float) ($net[$a->accode] ?? 0);
            if ($bshead === 'BS') {
                $bal += $a->signed_opening;
            }
            $bal = round($bal, 2);

            if (abs($bal) < 0.005) {
                continue;
            }

            $group = $a->group;

            /*
             | Legacy SunDbCrAutoCorrect. A Sundry Debtor who has overpaid now
             | owes nothing and is money the shop holds, so it is reported under
             | Sundry Creditors instead of as a negative asset - and the other
             | way round for a creditor in debit.
             */
            if ($autoCorrect && $group?->flipGroup) {
                $naturalSide = $group->base?->normal_side ?? 'D';
                $onNaturalSide = $naturalSide === 'D' ? $bal > 0 : $bal < 0;

                if (! $onNaturalSide) {
                    $group = $group->flipGroup;
                }
            }

            $gr = $group?->grcode ?? $a->grcode;
            $groups[$gr] ??= [
                'grcode' => $gr,
                'group' => $group?->name ?? 'Ungrouped',
                'gbscode' => $group?->gbscode,
                'nature' => $group?->base?->nature ?? 'ASSET',
                'sortorder' => $group?->sortorder ?? 999,
                'total' => 0.0,
                'accounts' => [],
            ];

            $groups[$gr]['total'] = round($groups[$gr]['total'] + $bal, 2);
            $groups[$gr]['accounts'][] = [
                'accode' => $a->accode,
                'name' => $a->name,
                'balance' => $bal,
            ];
        }

        return collect($groups)->sortBy('sortorder')->values();
    }

    /* =========================================================================
     |  6. REPORTS PORTED FROM dua soft
     ========================================================================= */

    /**
     * Cash Flow Statement - legacy d_cashflowreport.
     *
     * Day by day movement of the cash and bank ledgers, with the counterparty
     * ledgers that caused each day's inflow and outflow.
     */
    public function cashFlow(string $from, string $to): array
    {
        $accodes = $this->cashAndBankAccodes();

        if (empty($accodes)) {
            return ['from' => $from, 'to' => $to, 'opening' => 0, 'days' => [], 'closing' => 0,
                'total_inflow' => 0, 'total_outflow' => 0];
        }

        $opening = 0.0;
        foreach ($accodes as $accode) {
            $opening += $this->openingBalance($accode, $from);
        }

        $lines = Daybook::with('oppositeAccount:accode,name')
            ->whereIn('accode', $accodes)
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->orderBy('voucher_date')
            ->orderBy('slno')
            ->get();

        $days = [];

        foreach ($lines as $l) {
            $key = $l->voucher_date->toDateString();

            $days[$key] ??= [
                'date' => $key,
                'inflow' => [],
                'outflow' => [],
                'in_total' => 0.0,
                'out_total' => 0.0,
            ];

            // Money into the cash/bank ledger is a debit on it.
            $side = (float) $l->amount >= 0 ? 'inflow' : 'outflow';
            $value = round(abs((float) $l->amount), 2);
            $name = $l->oppositeAccount?->name ?? ($l->remarks ?: 'Journal');

            $days[$key][$side][$name] = round(($days[$key][$side][$name] ?? 0) + $value, 2);
            $days[$key][$side === 'inflow' ? 'in_total' : 'out_total'] += $value;
        }

        $running = round($opening, 2);
        $rows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($days as $day) {
            $day['in_total'] = round($day['in_total'], 2);
            $day['out_total'] = round($day['out_total'], 2);
            $running = round($running + $day['in_total'] - $day['out_total'], 2);

            $day['closing'] = $running;
            $totalIn += $day['in_total'];
            $totalOut += $day['out_total'];

            $rows[] = $day;
        }

        return [
            'from' => $from,
            'to' => $to,
            'opening' => round($opening, 2),
            'days' => $rows,
            'total_inflow' => round($totalIn, 2),
            'total_outflow' => round($totalOut, 2),
            'closing' => $running,
        ];
    }

    /**
     * Receipts & Payments - legacy d_rcptpmnts.
     *
     * Every cash and bank voucher line for the period with the account on the
     * other side, split into what came in and what went out.
     */
    public function receiptsAndPayments(string $from, string $to): array
    {
        $accodes = $this->cashAndBankAccodes();

        if (empty($accodes)) {
            return ['from' => $from, 'to' => $to, 'opening' => 0, 'receipts' => [],
                'payments' => [], 'total_receipts' => 0, 'total_payments' => 0, 'closing' => 0];
        }

        $opening = 0.0;
        foreach ($accodes as $accode) {
            $opening += $this->openingBalance($accode, $from);
        }

        $lines = Daybook::with(['account:accode,name', 'oppositeAccount:accode,name'])
            ->whereIn('accode', $accodes)
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->orderBy('voucher_date')
            ->orderBy('slno')
            ->get();

        $receipts = [];
        $payments = [];

        foreach ($lines as $l) {
            $row = [
                'date' => $l->voucher_date->format('d-m-Y'),
                'voucher_type' => $l->voucher_type,
                'voucher_no' => $l->voucher_no,
                'account' => $l->account?->name ?? $l->accode,
                'party' => $l->oppositeAccount?->name ?? ($l->remarks ?: 'Journal'),
                'party_accode' => $l->opaccode,
                'narration' => $l->narration ?: $l->remarks,
                'amount' => round(abs((float) $l->amount), 2),
            ];

            if ((float) $l->amount >= 0) {
                $receipts[] = $row;
            } else {
                $payments[] = $row;
            }
        }

        $totalReceipts = round(array_sum(array_column($receipts, 'amount')), 2);
        $totalPayments = round(array_sum(array_column($payments, 'amount')), 2);

        return [
            'from' => $from,
            'to' => $to,
            'opening' => round($opening, 2),
            'receipts' => $receipts,
            'payments' => $payments,
            'total_receipts' => $totalReceipts,
            'total_payments' => $totalPayments,
            'closing' => round($opening + $totalReceipts - $totalPayments, 2),
        ];
    }

    /**
     * Group-wise Account Summary - legacy d_group_accountsummary.
     *
     * One line per ledger in a group: opening, debit, credit and closing, so
     * the whole Sundry Debtors or Scheme Customers book prints on one page.
     */
    public function groupAccountSummary(string $grcode, string $from, string $to): array
    {
        $group = Accountg::with('base')->find($grcode);

        $codes = $group ? $group->descendantCodes() : [$grcode];

        $accounts = Accountm::whereIn('grcode', $codes)
            ->where('status', true)
            ->where('print_in_reports', true)
            ->orderBy('name')
            ->get();

        $period = Daybook::selectRaw('accode, SUM(dr_amount) as dr, SUM(cr_amount) as cr')
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->groupBy('accode')
            ->get()
            ->keyBy('accode');

        $before = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->where('voucher_date', '<', $from)
            ->groupBy('accode')
            ->pluck('net', 'accode');

        $rows = [];
        $t = ['opening' => 0.0, 'dr' => 0.0, 'cr' => 0.0, 'closing' => 0.0];

        foreach ($accounts as $a) {
            $opening = round($a->signed_opening + (float) ($before[$a->accode] ?? 0), 2);
            $dr = (float) ($period[$a->accode]->dr ?? 0);
            $cr = (float) ($period[$a->accode]->cr ?? 0);
            $closing = round($opening + $dr - $cr, 2);

            if (abs($opening) < 0.005 && abs($dr) < 0.005 && abs($cr) < 0.005) {
                continue;
            }

            $rows[] = [
                'accode' => $a->accode,
                'name' => $a->name,
                'phone' => $a->phone,
                'address' => $a->address,
                'opening' => $opening,
                'opening_side' => $opening >= 0 ? 'Dr' : 'Cr',
                'debit' => round($dr, 2),
                'credit' => round($cr, 2),
                'closing' => $closing,
                'closing_side' => $closing >= 0 ? 'Dr' : 'Cr',
            ];

            $t['opening'] += $opening;
            $t['dr'] += $dr;
            $t['cr'] += $cr;
            $t['closing'] += $closing;
        }

        return [
            'grcode' => $grcode,
            'group' => $group?->name ?? $grcode,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => array_map(fn ($v) => round($v, 2), $t),
        ];
    }

    /**
     * Receivables & Payables summary - legacy d_rcblepayble_summary.
     *
     * Every party ledger that still carries a balance, split into what is owed
     * to the shop and what the shop owes.
     */
    public function receivablePayableSummary(string $asOn): array
    {
        $accounts = Accountm::with('group')
            ->whereIn('actype', ['CUSTOMER', 'AGENT', 'SUPPLIER'])
            ->where('status', true)
            ->where('print_in_reports', true)
            ->orderBy('name')
            ->get();

        $net = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->where('voucher_date', '<=', $asOn)
            ->groupBy('accode')
            ->pluck('net', 'accode');

        $receivable = [];
        $payable = [];

        foreach ($accounts as $a) {
            $bal = round($a->signed_opening + (float) ($net[$a->accode] ?? 0), 2);

            if (abs($bal) < 0.005) {
                continue;
            }

            $row = [
                'accode' => $a->accode,
                'name' => $a->name,
                'phone' => $a->phone,
                'actype' => $a->actype,
                'group' => $a->group?->name,
                'amount' => abs($bal),
            ];

            // Debit balance = they owe the shop.
            if ($bal > 0) {
                $receivable[] = $row;
            } else {
                $payable[] = $row;
            }
        }

        usort($receivable, fn ($x, $y) => $y['amount'] <=> $x['amount']);
        usort($payable, fn ($x, $y) => $y['amount'] <=> $x['amount']);

        $totalRecv = round(array_sum(array_column($receivable, 'amount')), 2);
        $totalPay = round(array_sum(array_column($payable, 'amount')), 2);

        return [
            'as_on' => $asOn,
            'receivable' => $receivable,
            'payable' => $payable,
            'total_receivable' => $totalRecv,
            'total_payable' => $totalPay,
            'net' => round($totalRecv - $totalPay, 2),
        ];
    }

    /**
     * Trial Balance rolled up to the group level - legacy d_trialbal_grp.
     *
     * Same figures as the ledger-wise trial balance, one line per account
     * group, which is what gets signed off at the year end.
     */
    public function trialBalanceByGroup(string $from, string $to): array
    {
        $detail = $this->trialBalance($from, $to, true);

        $groups = [];

        foreach ($detail['rows'] as $r) {
            $key = $r['grcode'] ?: 'UNGROUPED';

            $groups[$key] ??= [
                'grcode' => $key,
                'group' => $r['group'] ?? 'Ungrouped',
                'base' => $r['base'] ?? null,
                'bshead' => $r['bshead'],
                'opening' => 0.0,
                'debit' => 0.0,
                'credit' => 0.0,
                'closing' => 0.0,
                'ledgers' => 0,
            ];

            $groups[$key]['opening'] += $r['opening_dr'] - $r['opening_cr'];
            $groups[$key]['debit'] += $r['debit'];
            $groups[$key]['credit'] += $r['credit'];
            $groups[$key]['closing'] += $r['closing'];
            $groups[$key]['ledgers']++;
        }

        $rows = [];
        $t = ['debit' => 0.0, 'credit' => 0.0, 'closing_dr' => 0.0, 'closing_cr' => 0.0];

        foreach ($groups as $g) {
            $g = array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $g);
            $g['closing_dr'] = $g['closing'] > 0 ? $g['closing'] : 0;
            $g['closing_cr'] = $g['closing'] < 0 ? abs($g['closing']) : 0;

            $t['debit'] += $g['debit'];
            $t['credit'] += $g['credit'];
            $t['closing_dr'] += $g['closing_dr'];
            $t['closing_cr'] += $g['closing_cr'];

            $rows[] = $g;
        }

        $t = array_map(fn ($v) => round($v, 2), $t);

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => $t,
            'balanced' => abs($t['closing_dr'] - $t['closing_cr']) < 0.05,
            'difference' => round($t['closing_dr'] - $t['closing_cr'], 2),
        ];
    }

    /**
     * Journal register - legacy d_journrpt. Vouchers grouped as they were
     * entered, rather than as a flat list of ledger lines.
     */
    public function journalRegister(string $from, string $to, ?string $voucherType = null): array
    {
        $vouchers = Daybookpart::with(['lines.account:accode,name'])
            ->where('status', 'POSTED')
            ->whereBetween('voucher_date', [$from, $to])
            ->when($voucherType, fn ($q) => $q->where('voucher_type', $voucherType))
            ->orderBy('voucher_date')
            ->orderBy('sno')
            ->get();

        $rows = [];
        $total = 0.0;

        foreach ($vouchers as $v) {
            $lines = [];

            foreach ($v->lines as $l) {
                $lines[] = [
                    'accode' => $l->accode,
                    'name' => $l->account?->name ?? $l->accode,
                    'debit' => (float) $l->dr_amount,
                    'credit' => (float) $l->cr_amount,
                    'remarks' => $l->remarks,
                ];
            }

            $amount = round((float) $v->lines->sum('dr_amount'), 2);
            $total += $amount;

            $rows[] = [
                'sno' => $v->sno,
                'date' => $v->voucher_date->format('d-m-Y'),
                'voucher_type' => $v->voucher_type,
                'voucher_no' => $v->voucher_no,
                'narration' => $v->narration,
                'reference' => $v->reference,
                'amount' => $amount,
                'lines' => $lines,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'voucher_type' => $voucherType,
            'rows' => $rows,
            'total' => round($total, 2),
        ];
    }

    /** Every ledger that behaves like cash: the cash box and the bank accounts. */
    protected function cashAndBankAccodes(): array
    {
        return Accountm::whereIn('actype', ['CASH', 'BANK'])
            ->where('status', true)
            ->pluck('accode')
            ->all();
    }

    /** Flip credit-natured group totals to positive display figures. */
    protected function flipSign(Collection|array $groups): array
    {
        return collect($groups)->map(function ($g) {
            $g['total'] = round(-$g['total'], 2);
            $g['accounts'] = collect($g['accounts'] ?? [])
                ->map(function ($a) {
                    $a['balance'] = round(-$a['balance'], 2);

                    return $a;
                })->all();

            return $g;
        })->all();
    }
}
