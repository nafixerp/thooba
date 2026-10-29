<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accountg;
use App\Models\Accountm;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use App\Services\TradingAccountService;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function __construct(
        protected FinancialReportService $reports,
        protected AccountingService $accounts,
        protected TradingAccountService $trading
    ) {}

    /** Default period = current financial year up to today */
    protected function period(Request $request): array
    {
        [$fyFrom, $fyTo] = $this->accounts->financialYearRange(now()->toDateString());

        return [
            $request->from ?: $fyFrom->toDateString(),
            $request->to ?: now()->toDateString(),
        ];
    }

    /* ---------------- 1. GENERAL LEDGER ---------------- */
    public function generalLedger(Request $request)
    {
        [$from, $to] = $this->period($request);

        $ledgers = Accountm::active()->orderBy('name')->get(['accode', 'name']);
        $accode = $request->accode ?: config('scheme.accounts.cash');

        $data = $this->reports->generalLedger($accode, $from, $to);

        return view('accounts.reports.general-ledger', compact('data', 'ledgers', 'accode', 'from', 'to'));
    }

    /* ---------------- 2. TRIAL BALANCE ---------------- */
    public function trialBalance(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->trialBalance($from, $to, ! $request->boolean('show_zero'));

        return view('accounts.reports.trial-balance', compact('data', 'from', 'to'));
    }

    /* ---------------- 3. PROFIT & LOSS ---------------- */
    public function profitLoss(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->profitAndLoss($from, $to);

        return view('accounts.reports.profit-loss', compact('data', 'from', 'to'));
    }

    /* ---------------- 4. BALANCE SHEET ---------------- */
    public function balanceSheet(Request $request)
    {
        $asOn = $request->as_on ?: now()->toDateString();
        $data = $this->reports->balanceSheet($asOn);

        return view('accounts.reports.balance-sheet', compact('data', 'asOn'));
    }

    /* ---------------- 5. DAY BOOK ---------------- */
    public function dayBook(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $rows = $this->reports->dayBook($from, $to, $request->voucher_type);
        $types = config('scheme.voucher_types');

        return view('accounts.reports.daybook', compact('rows', 'from', 'to', 'types'));
    }

    /* ---------------- 6. CASH / BANK BOOK ---------------- */
    public function cashBook(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->cashBook($from, $to);

        return view('accounts.reports.general-ledger', [
            'data' => $data,
            'ledgers' => Accountm::active()->orderBy('name')->get(['accode', 'name']),
            'accode' => config('scheme.accounts.cash'),
            'from' => $from,
            'to' => $to,
            'title' => 'Cash Book',
        ]);
    }

    public function bankBook(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->bankBook($from, $to);

        return view('accounts.reports.general-ledger', [
            'data' => $data,
            'ledgers' => Accountm::active()->orderBy('name')->get(['accode', 'name']),
            'accode' => config('scheme.accounts.bank'),
            'from' => $from,
            'to' => $to,
            'title' => 'Bank Book',
        ]);
    }

    /* ---------------- 7. OUTSTANDING ---------------- */
    public function outstanding(Request $request)
    {
        $actype = strtoupper($request->type ?: 'CUSTOMER');
        $asOn = $request->as_on ?: now()->toDateString();
        $rows = $this->reports->outstanding($actype, $asOn);

        return view('accounts.reports.outstanding', compact('rows', 'actype', 'asOn'));
    }

    /* =====================================================================
     |  REPORTS PORTED FROM dua soft
     ===================================================================== */

    /**
     * Trading, Profit & Loss A/c - the vertical statement the legacy books
     * print: Trading A/c closing into Gross Profit, then the P&L A/c closing
     * into Net Profit.
     */
    public function tradingProfitLoss(Request $request)
    {
        [$from, $to] = $this->period($request);
        $upto = $request->boolean('upto');

        $data = $this->trading->statement($from, $to, $upto);

        return view('accounts.reports.trading-profit-loss', compact('data', 'from', 'to', 'upto'));
    }

    /** Schedules backing each Balance Sheet line. */
    public function balanceSheetSchedules(Request $request)
    {
        $asOn = $request->as_on ?: now()->toDateString();
        $data = $this->reports->balanceSheetSchedules($asOn);

        return view('accounts.reports.balance-sheet-schedules', compact('data', 'asOn'));
    }

    /** Day by day cash and bank movement with the reason for each. */
    public function cashFlow(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->cashFlow($from, $to);

        return view('accounts.reports.cash-flow', compact('data', 'from', 'to'));
    }

    /** Every cash / bank voucher for the period, split into in and out. */
    public function receiptsPayments(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->receiptsAndPayments($from, $to);

        return view('accounts.reports.receipts-payments', compact('data', 'from', 'to'));
    }

    /** Opening / debit / credit / closing for every ledger in one group. */
    public function groupSummary(Request $request)
    {
        [$from, $to] = $this->period($request);

        $groups = Accountg::active()->orderBy('sortorder')->get(['grcode', 'name']);
        $grcode = $request->grcode ?: ($groups->first()->grcode ?? '');

        $data = $grcode
            ? $this->reports->groupAccountSummary($grcode, $from, $to)
            : ['grcode' => '', 'group' => '', 'from' => $from, 'to' => $to, 'rows' => [],
                'totals' => ['opening' => 0, 'dr' => 0, 'cr' => 0, 'closing' => 0]];

        return view('accounts.reports.group-summary', compact('data', 'groups', 'grcode', 'from', 'to'));
    }

    /** Who owes the shop and who the shop owes, on one date. */
    public function receivablePayable(Request $request)
    {
        $asOn = $request->as_on ?: now()->toDateString();
        $data = $this->reports->receivablePayableSummary($asOn);

        return view('accounts.reports.receivable-payable', compact('data', 'asOn'));
    }

    /** Trial balance rolled up to the account group. */
    public function trialBalanceGroup(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->trialBalanceByGroup($from, $to);

        return view('accounts.reports.trial-balance-group', compact('data', 'from', 'to'));
    }

    /** Vouchers as they were entered, rather than as loose ledger lines. */
    public function journalRegister(Request $request)
    {
        [$from, $to] = $this->period($request);
        $data = $this->reports->journalRegister($from, $to, $request->voucher_type);
        $types = config('scheme.voucher_types');

        return view('accounts.reports.journal-register', compact('data', 'from', 'to', 'types'));
    }

    /* ---------------- CSV EXPORT ---------------- */
    public function export(Request $request, string $report)
    {
        [$from, $to] = $this->period($request);

        [$filename, $rows] = match ($report) {
            'trial-balance' => ['trial-balance', $this->tbRows($from, $to)],
            'profit-loss' => ['profit-loss', $this->plRows($from, $to)],
            'trading-profit-loss' => ['trading-profit-loss', $this->tplRows($from, $to, $request->boolean('upto'))],
            'balance-sheet' => ['balance-sheet', $this->bsRows($request->as_on ?: $to)],
            'general-ledger' => ['general-ledger', $this->glRows($request->accode ?: config('scheme.accounts.cash'), $from, $to)],
            'cash-flow' => ['cash-flow', $this->cashFlowRows($from, $to)],
            'receipts-payments' => ['receipts-payments', $this->rpRows($from, $to)],
            'group-summary' => ['group-summary', $this->groupRows($request->grcode, $from, $to)],
            'receivable-payable' => ['receivable-payable', $this->rpSummaryRows($request->as_on ?: $to)],
            'trial-balance-group' => ['trial-balance-group', $this->tbGroupRows($from, $to)],
            default => abort(404),
        };

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename.'-'.$from.'-to-'.$to.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function tbRows(string $from, string $to): array
    {
        $d = $this->reports->trialBalance($from, $to);
        $rows = [['Code', 'Ledger', 'Group', 'Opening Dr', 'Opening Cr', 'Debit', 'Credit', 'Closing Dr', 'Closing Cr']];
        foreach ($d['rows'] as $r) {
            $rows[] = [$r['accode'], $r['name'], $r['group'], $r['opening_dr'], $r['opening_cr'],
                $r['debit'], $r['credit'], $r['closing_dr'], $r['closing_cr']];
        }
        $t = $d['totals'];
        $rows[] = ['', 'TOTAL', '', $t['opening_dr'], $t['opening_cr'], $t['dr'], $t['cr'], $t['closing_dr'], $t['closing_cr']];

        return $rows;
    }

    protected function plRows(string $from, string $to): array
    {
        $d = $this->reports->profitAndLoss($from, $to);
        $rows = [['Section', 'Group', 'Ledger', 'Amount']];
        foreach (['expense' => 'EXPENSE', 'income' => 'INCOME'] as $key => $label) {
            foreach ($d[$key] as $g) {
                $rows[] = [$label, $g['group'], '', $g['total']];
                foreach ($g['accounts'] as $a) {
                    $rows[] = [$label, $g['group'], $a['name'], $a['balance']];
                }
            }
        }
        $rows[] = ['TOTAL', 'Income', '', $d['total_income']];
        $rows[] = ['TOTAL', 'Expense', '', $d['total_expense']];
        $rows[] = ['RESULT', $d['is_profit'] ? 'Net Profit' : 'Net Loss', '', abs($d['net_profit'])];

        return $rows;
    }

    protected function bsRows(string $asOn): array
    {
        $d = $this->reports->balanceSheet($asOn);
        $rows = [['Side', 'Group', 'Ledger', 'Amount']];
        foreach ([['liabilities', 'LIABILITY'], ['assets', 'ASSET']] as [$key, $label]) {
            foreach ($d[$key] as $g) {
                $rows[] = [$label, $g['group'], '', $g['total']];
                foreach ($g['accounts'] as $a) {
                    $rows[] = [$label, $g['group'], $a['name'], $a['balance']];
                }
            }
        }
        $rows[] = ['TOTAL', 'Liabilities', '', $d['total_liabilities']];
        $rows[] = ['TOTAL', 'Assets', '', $d['total_assets']];

        return $rows;
    }

    protected function tplRows(string $from, string $to, bool $upto): array
    {
        $d = $this->trading->statement($from, $to, $upto);
        $rows = [[$d['title']], ['Section', 'Side', 'Particulars', 'Amount']];

        foreach ($d['trading'] as $s) {
            foreach ($s['debit'] as $l) {
                $rows[] = [$s['title'], 'Dr', $l['name'], $l['amount']];
            }
            foreach ($s['credit'] as $l) {
                $rows[] = [$s['title'], 'Cr', $l['name'], $l['amount']];
            }
            $rows[] = [$s['title'], 'TOTAL', '', $s['total_debit']];
        }

        $p = $d['profit_loss'];
        foreach ($p['debit'] as $l) {
            $rows[] = [$p['title'], 'Dr', $l['name'], $l['amount']];
        }
        foreach ($p['credit'] as $l) {
            $rows[] = [$p['title'], 'Cr', $l['name'], $l['amount']];
        }

        $rows[] = ['RESULT', '', $d['is_gross_profit'] ? 'Gross Profit' : 'Gross Loss', abs($d['gross_profit'])];
        $rows[] = ['RESULT', '', $d['is_profit'] ? 'Net Profit' : 'Net Loss', abs($d['net_profit'])];

        return $rows;
    }

    protected function cashFlowRows(string $from, string $to): array
    {
        $d = $this->reports->cashFlow($from, $to);
        $rows = [['Date', 'Inflow details', 'Inflow', 'Outflow details', 'Outflow', 'Closing']];
        $rows[] = ['Opening', '', '', '', '', $d['opening']];

        foreach ($d['days'] as $day) {
            $rows[] = [
                $day['date'],
                implode('; ', array_map(fn ($k, $v) => "$k $v", array_keys($day['inflow']), $day['inflow'])),
                $day['in_total'],
                implode('; ', array_map(fn ($k, $v) => "$k $v", array_keys($day['outflow']), $day['outflow'])),
                $day['out_total'],
                $day['closing'],
            ];
        }

        $rows[] = ['TOTAL', '', $d['total_inflow'], '', $d['total_outflow'], $d['closing']];

        return $rows;
    }

    protected function rpRows(string $from, string $to): array
    {
        $d = $this->reports->receiptsAndPayments($from, $to);
        $rows = [['Side', 'Date', 'Voucher', 'No', 'Account', 'Party', 'Narration', 'Amount']];

        foreach ([['receipts', 'RECEIPT'], ['payments', 'PAYMENT']] as [$key, $label]) {
            foreach ($d[$key] as $r) {
                $rows[] = [$label, $r['date'], $r['voucher_type'], $r['voucher_no'],
                    $r['account'], $r['party'], $r['narration'], $r['amount']];
            }
        }

        $rows[] = ['TOTAL', 'Opening', '', '', '', '', '', $d['opening']];
        $rows[] = ['TOTAL', 'Receipts', '', '', '', '', '', $d['total_receipts']];
        $rows[] = ['TOTAL', 'Payments', '', '', '', '', '', $d['total_payments']];
        $rows[] = ['TOTAL', 'Closing', '', '', '', '', '', $d['closing']];

        return $rows;
    }

    protected function groupRows(?string $grcode, string $from, string $to): array
    {
        $grcode = $grcode ?: (Accountg::active()->orderBy('sortorder')->value('grcode') ?? '');
        $d = $this->reports->groupAccountSummary($grcode, $from, $to);

        $rows = [[$d['group']], ['Code', 'Ledger', 'Phone', 'Opening', 'Debit', 'Credit', 'Closing', 'Side']];
        foreach ($d['rows'] as $r) {
            $rows[] = [$r['accode'], $r['name'], $r['phone'], $r['opening'],
                $r['debit'], $r['credit'], abs($r['closing']), $r['closing_side']];
        }
        $t = $d['totals'];
        $rows[] = ['', 'TOTAL', '', $t['opening'], $t['dr'], $t['cr'], $t['closing'], ''];

        return $rows;
    }

    protected function rpSummaryRows(string $asOn): array
    {
        $d = $this->reports->receivablePayableSummary($asOn);
        $rows = [['Side', 'Code', 'Name', 'Phone', 'Group', 'Amount']];

        foreach ([['receivable', 'RECEIVABLE'], ['payable', 'PAYABLE']] as [$key, $label]) {
            foreach ($d[$key] as $r) {
                $rows[] = [$label, $r['accode'], $r['name'], $r['phone'], $r['group'], $r['amount']];
            }
        }

        $rows[] = ['TOTAL', '', 'Receivable', '', '', $d['total_receivable']];
        $rows[] = ['TOTAL', '', 'Payable', '', '', $d['total_payable']];
        $rows[] = ['TOTAL', '', 'Net', '', '', $d['net']];

        return $rows;
    }

    protected function tbGroupRows(string $from, string $to): array
    {
        $d = $this->reports->trialBalanceByGroup($from, $to);
        $rows = [['Code', 'Group', 'Head', 'Ledgers', 'Debit', 'Credit', 'Closing Dr', 'Closing Cr']];

        foreach ($d['rows'] as $r) {
            $rows[] = [$r['grcode'], $r['group'], $r['bshead'], $r['ledgers'],
                $r['debit'], $r['credit'], $r['closing_dr'], $r['closing_cr']];
        }

        $t = $d['totals'];
        $rows[] = ['', 'TOTAL', '', '', $t['debit'], $t['credit'], $t['closing_dr'], $t['closing_cr']];

        return $rows;
    }

    protected function glRows(string $accode, string $from, string $to): array
    {
        $d = $this->reports->generalLedger($accode, $from, $to);
        $rows = [['Date', 'Voucher', 'No', 'Particulars', 'Remarks', 'Debit', 'Credit', 'Balance']];
        $rows[] = ['', '', '', 'Opening Balance', '', '', '', $d['opening'].' '.$d['opening_side']];
        foreach ($d['lines'] as $l) {
            $rows[] = [$l['date'], $l['voucher_type'], $l['voucher_no'], $l['particulars'],
                $l['remarks'], $l['debit'], $l['credit'], $l['balance']];
        }
        $rows[] = ['', '', '', 'TOTAL', '', $d['total_debit'], $d['total_credit'], $d['closing'].' '.$d['closing_side']];

        return $rows;
    }
}
