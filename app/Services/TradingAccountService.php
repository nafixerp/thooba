<?php

namespace App\Services;

use App\Models\Accountm;
use App\Models\Daybook;
use App\Models\StockValue;
use Illuminate\Support\Carbon;

/**
 * -----------------------------------------------------------------------------
 *  TradingAccountService - Trading, Profit & Loss A/c in the dua soft shape
 * -----------------------------------------------------------------------------
 *
 * The legacy books (dua soft / kuri, PowerBuilder) do not print a flat income
 * versus expense statement. They print one vertical document made of up to
 * three T accounts, each closing into the next:
 *
 *     Trading A/c     (tplpos = 1)   direct income vs direct cost
 *          |                         + Opening Stock (Dr), Closing Stock (Cr)
 *          +--> Gross Profit c/d
 *
 *     Trading A/c II  (tplpos = 2)   the old KGST/VAT split kept taxable and
 *          |                         non-taxable goods in separate accounts
 *          +--> Gross Profit c/d
 *
 *     Profit & Loss A/c (tplpos = 0)
 *          Gross Profit b/d (the two c/d figures added) + indirect income
 *          less indirect expenses
 *          +--> Net Profit / Net Loss  --> Balance Sheet
 *
 * dua soft carries the running figures in accountm.amount and in the reserved
 * ledgers OPSTOCK / CLSTOCK / GPROFITN / GPROFITT / GPROFIT / PROFIT, rewriting
 * them on every run. Nothing ever posts to those ledgers, so this port computes
 * the same lines instead of storing them - the Trial Balance then stays purely
 * daybook driven and cannot drift.
 *
 * Sign convention is this project's, which is the opposite of the legacy one:
 *
 *     amount > 0  =>  Debit
 *     amount < 0  =>  Credit
 */
class TradingAccountService
{
    /** Reserved codes for the computed lines, named after the legacy ledgers. */
    public const OPENING_STOCK = 'OPSTOCK';

    public const CLOSING_STOCK = 'CLSTOCK';

    public const GROSS_PROFIT = 'GPROFIT';

    public const NET_PROFIT = 'PROFIT';

    /**
     * The whole statement.
     *
     * @param  string  $from  period start (ignored when $upto)
     * @param  string  $to  period end
     * @param  bool  $upto  legacy "Upto Date" mode: everything from the
     *                      beginning of the books, opening balances included,
     *                      rather than the movement inside one period.
     */
    public function statement(string $from, string $to, bool $upto = false): array
    {
        $balances = $this->ledgerBalances($from, $to, $upto);

        $sections = [];
        foreach ([1, 2] as $tplpos) {
            $section = $this->tradingSection($tplpos, $balances, $from, $to);
            // Section II only exists once a ledger has been put in it.
            if ($tplpos === 2 && $section['is_empty']) {
                continue;
            }
            $sections[] = $section;
        }

        $grossProfit = round(array_sum(array_column($sections, 'gross_profit')), 2);

        $profitLoss = $this->profitLossSection($balances, $grossProfit);

        return [
            'from' => $from,
            'to' => $to,
            'upto' => $upto,
            'title' => $upto
                ? 'Trading, Profit and Loss A/c Upto '.$this->d($to)
                : 'Trading, Profit and Loss A/c From '.$this->d($from).' To '.$this->d($to),
            'trading' => $sections,
            'gross_profit' => $grossProfit,
            'is_gross_profit' => $grossProfit >= 0,
            'profit_loss' => $profitLoss,
            'net_profit' => $profitLoss['net_profit'],
            'is_profit' => $profitLoss['net_profit'] >= 0,
        ];
    }

    /**
     * Net profit for a period - what the Balance Sheet carries to the
     * liabilities side. Same number the statement ends on.
     */
    public function netProfit(string $from, string $to, bool $upto = false): float
    {
        return $this->statement($from, $to, $upto)['net_profit'];
    }

    /* =========================================================================
     |  SECTIONS
     ========================================================================= */

    /**
     * One trading section: its ledgers on the correct side, the stock lines,
     * and the Gross Profit / Loss c/d that squares it off.
     */
    protected function tradingSection(int $tplpos, array $balances, string $from, string $to): array
    {
        $debit = [];
        $credit = [];

        foreach ($balances as $row) {
            if ((int) $row['tplpos'] !== $tplpos) {
                continue;
            }
            // + = Debit (cost), - = Credit (income)
            if ($row['balance'] > 0) {
                $debit[] = $row + ['amount' => round($row['balance'], 2)];
            } else {
                $credit[] = $row + ['amount' => round(-$row['balance'], 2)];
            }
        }

        $hasLedgers = $debit || $credit;

        // Stock only belongs to the first trading section, as in the legacy books.
        $opening = 0.0;
        $closing = 0.0;

        if ($tplpos === 1) {
            [$opening, $closing] = $this->stockFigures($to);

            if (abs($opening) > 0.005) {
                array_unshift($debit, $this->computedRow(self::OPENING_STOCK, 'Opening Stock', $opening));
            }
            if (abs($closing) > 0.005) {
                array_unshift($credit, $this->computedRow(self::CLOSING_STOCK, 'Closing Stock', $closing));
            }
        }

        $totalDebit = round(array_sum(array_column($debit, 'amount')), 2);
        $totalCredit = round(array_sum(array_column($credit, 'amount')), 2);

        // Credit side larger => gross profit; it squares the account off on the
        // debit side, exactly like "To Gross Profit c/d".
        $gross = round($totalCredit - $totalDebit, 2);

        if (abs($gross) > 0.005) {
            $label = ($gross >= 0 ? 'Gross Profit c/d' : 'Gross Loss c/d')
                .($tplpos === 2 ? ' (II)' : '');

            if ($gross >= 0) {
                $debit[] = $this->computedRow(self::GROSS_PROFIT.$tplpos, $label, $gross, true);
            } else {
                $credit[] = $this->computedRow(self::GROSS_PROFIT.$tplpos, $label, -$gross, true);
            }
        }

        return [
            'tplpos' => $tplpos,
            'title' => config('scheme.tpl_sections')[$tplpos] ?? 'Trading A/c',
            'debit' => $debit,
            'credit' => $credit,
            'opening_stock' => $opening,
            'closing_stock' => $closing,
            'total_debit' => round($totalDebit + max($gross, 0), 2),
            'total_credit' => round($totalCredit + max(-$gross, 0), 2),
            'gross_profit' => $gross,
            'is_gross_profit' => $gross >= 0,
            'is_empty' => ! $hasLedgers && abs($opening) < 0.005 && abs($closing) < 0.005,
        ];
    }

    /** The Profit & Loss A/c proper: gross profit b/d, indirect income, indirect expense. */
    protected function profitLossSection(array $balances, float $grossProfit): array
    {
        $debit = [];
        $credit = [];

        // Gross profit is brought down on the credit side, a gross loss on the debit.
        if (abs($grossProfit) > 0.005) {
            if ($grossProfit >= 0) {
                $credit[] = $this->computedRow(self::GROSS_PROFIT, 'Gross Profit b/d', $grossProfit, true);
            } else {
                $debit[] = $this->computedRow(self::GROSS_PROFIT, 'Gross Loss b/d', -$grossProfit, true);
            }
        }

        foreach ($balances as $row) {
            if ((int) $row['tplpos'] !== 0) {
                continue;
            }
            if ($row['balance'] > 0) {
                $debit[] = $row + ['amount' => round($row['balance'], 2)];
            } else {
                $credit[] = $row + ['amount' => round(-$row['balance'], 2)];
            }
        }

        $totalDebit = round(array_sum(array_column($debit, 'amount')), 2);
        $totalCredit = round(array_sum(array_column($credit, 'amount')), 2);

        $net = round($totalCredit - $totalDebit, 2);

        if (abs($net) > 0.005) {
            $label = $net >= 0 ? 'Net Profit' : 'Net Loss';
            if ($net >= 0) {
                $debit[] = $this->computedRow(self::NET_PROFIT, $label, $net, true);
            } else {
                $credit[] = $this->computedRow(self::NET_PROFIT, $label, -$net, true);
            }
        }

        return [
            'title' => config('scheme.tpl_sections')[0] ?? 'Profit & Loss A/c',
            'debit' => $debit,
            'credit' => $credit,
            'total_debit' => round($totalDebit + max($net, 0), 2),
            'total_credit' => round($totalCredit + max(-$net, 0), 2),
            'net_profit' => $net,
            'is_profit' => $net >= 0,
        ];
    }

    /* =========================================================================
     |  BALANCES
     ========================================================================= */

    /**
     * Signed closing balance of every P&L ledger, with its group and section.
     *
     * In "Upto Date" mode the ledger's own opening balance counts too, which is
     * what the legacy does when its report type is not 'T'.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function ledgerBalances(string $from, string $to, bool $upto): array
    {
        $accounts = Accountm::with('group')
            ->where('bshead', 'PL')
            ->where('status', true)
            ->where('print_in_reports', true)
            ->orderBy('grcode')
            ->orderBy('name')
            ->get();

        $q = Daybook::selectRaw('accode, SUM(amount) as net')
            ->where('status', 'POSTED')
            ->where('voucher_date', '<=', $to);

        if (! $upto) {
            $q->where('voucher_date', '>=', $from);
        }

        $net = $q->groupBy('accode')->pluck('net', 'accode');

        $rows = [];

        foreach ($accounts as $a) {
            $balance = (float) ($net[$a->accode] ?? 0);

            if ($upto) {
                $balance += $a->signed_opening;
            }

            $balance = round($balance, 2);

            if (abs($balance) < 0.005) {
                continue;
            }

            $rows[] = [
                'accode' => $a->accode,
                'name' => $a->name,
                'grcode' => $a->grcode,
                'group' => $a->group?->name ?? 'Ungrouped',
                'group_pos' => $a->group?->sortorder ?? 999,
                'tplpos' => (int) $a->tplpos,
                'balance' => $balance,
                'computed' => false,
                'bold' => false,
            ];
        }

        usort($rows, fn ($x, $y) => [$x['group_pos'], $x['name']] <=> [$y['group_pos'], $y['name']]);

        return $rows;
    }

    /**
     * Opening and closing stock for the Trading A/c.
     *
     * Opening is the stock ledger's own book value carried in; closing is the
     * figure entered under Accounts -> Closing Stock. With nothing entered the
     * two are equal, so the statement behaves exactly as it did before anyone
     * started valuing stock, and the Balance Sheet needs no adjustment.
     *
     * @return array{0: float, 1: float}
     */
    public function stockFigures(string $to): array
    {
        $stock = Accountm::find(config('scheme.accounts.stock'));

        if (! $stock) {
            return [0.0, 0.0];
        }

        $opening = $stock->balanceAsOn($to);
        $valued = StockValue::asOn($to);

        return [round($opening, 2), round($valued ? (float) $valued->amount : $opening, 2)];
    }

    /**
     * What the Balance Sheet has to add to the stock ledger so that assets
     * still equal liabilities plus the net profit this statement produced.
     */
    public function stockAdjustment(string $to): float
    {
        [$opening, $closing] = $this->stockFigures($to);

        return round($closing - $opening, 2);
    }

    /* =========================================================================
     |  HELPERS
     ========================================================================= */

    /**
     * @return array<string, mixed>
     */
    protected function computedRow(string $code, string $name, float $amount, bool $bold = false): array
    {
        return [
            'accode' => $code,
            'name' => $name,
            'grcode' => null,
            'group' => null,
            'group_pos' => 0,
            'tplpos' => 0,
            'balance' => $amount,
            'amount' => round($amount, 2),
            'computed' => true,
            'bold' => $bold,
        ];
    }

    protected function d(string $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }
}
