<?php

namespace App\Services;

use App\Models\Accountm;
use App\Models\Daybook;
use App\Models\Daybookpart;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * -----------------------------------------------------------------------------
 *  AccountingService  -  ella double entry posting-um ivide koodi mathrame pokan paadullu.
 * -----------------------------------------------------------------------------
 *
 *  post(header, lines)
 *      lines = [ ['accode' => '1001', 'amount' =>  1000, 'opaccode' => '2001'],
 *                ['accode' => '2001', 'amount' => -1000, 'opaccode' => '1001'] ]
 *
 *  Rule: SUM(amount) === 0.00  illenkil exception.
 */
class AccountingService
{
    /* =========================================================================
     |  CORE POSTING
     ========================================================================= */

    /**
     * Post a balanced voucher into daybookpart + daybook.
     *
     * @param  array  $header  voucher_type, voucher_date, narration, reference,
     *                         source_type, source_id, branch, voucher_no (optional)
     * @param  array  $lines   each: accode, amount (signed), opaccode?, remarks?
     */
    public function post(array $header, array $lines): Daybookpart
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A voucher needs at least two lines.');
        }

        $type = strtoupper($header['voucher_type'] ?? 'JOURNAL');
        $date = Carbon::parse($header['voucher_date'] ?? now())->toDateString();
        $fy = $this->financialYear($date);

        // Normalise + validate
        $normalised = [];
        $sum = 0.0;
        $debit = 0.0;
        $i = 1;

        foreach ($lines as $line) {
            $accode = (string) ($line['accode'] ?? '');
            $amount = round((float) ($line['amount'] ?? 0), 2);

            if ($accode === '') {
                throw new InvalidArgumentException('Line '.$i.': accode is required.');
            }
            if (abs($amount) < 0.005) {
                continue; // zero line - skip
            }

            $normalised[] = array_merge(
                Daybook::makeLine($accode, $amount),
                [
                    'sno' => 0, // filled after header insert
                    'line_no' => $i,
                    'opaccode' => $line['opaccode'] ?? null,
                    'voucher_type' => $type,
                    'voucher_no' => '',
                    'voucher_date' => $date,
                    'fy' => $fy,
                    'remarks' => $line['remarks'] ?? ($header['narration'] ?? null),
                    'narration' => $header['narration'] ?? null,
                    'ref_no' => $line['ref_no'] ?? ($header['reference'] ?? null),
                    'branch' => $header['branch'] ?? null,
                    'status' => $header['status'] ?? 'POSTED',
                    'is_opening' => (bool) ($header['is_opening'] ?? false),
                    'created_by' => $header['created_by'] ?? auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $sum += $amount;
            if ($amount > 0) {
                $debit += $amount;
            }
            $i++;
        }

        if (count($normalised) < 2) {
            throw new InvalidArgumentException('A voucher needs at least two non-zero lines.');
        }

        if (abs(round($sum, 2)) > 0.005) {
            throw new InvalidArgumentException(
                'Voucher is not balanced. Difference = '.number_format($sum, 2)
            );
        }

        // Every accode must exist
        $codes = array_unique(array_column($normalised, 'accode'));
        $found = Accountm::whereIn('accode', $codes)->pluck('accode')->all();
        if ($missing = array_diff($codes, $found)) {
            throw new InvalidArgumentException('Unknown ledger account(s): '.implode(', ', $missing));
        }

        return DB::transaction(function () use ($header, $normalised, $type, $date, $fy, $debit) {
            $voucherNo = $header['voucher_no'] ?? $this->nextVoucherNo($type, $fy);

            $part = Daybookpart::create([
                'voucher_type' => $type,
                'voucher_no' => $voucherNo,
                'voucher_date' => $date,
                'fy' => $fy,
                'total_amount' => round($debit, 2),
                'reference' => $header['reference'] ?? null,
                'narration' => $header['narration'] ?? null,
                'branch' => $header['branch'] ?? null,
                'source_type' => $header['source_type'] ?? 'manual',
                'source_id' => $header['source_id'] ?? null,
                'status' => $header['status'] ?? 'POSTED',
                'is_opening' => (bool) ($header['is_opening'] ?? false),
                'created_by' => $header['created_by'] ?? auth()->id(),
            ]);

            foreach ($normalised as &$row) {
                $row['sno'] = $part->sno;
                $row['voucher_no'] = $voucherNo;
            }
            unset($row);

            Daybook::insert($normalised);

            return $part->load('lines');
        });
    }

    /**
     * Shortcut for a simple 2-line voucher.
     * Dr $drAccode / Cr $crAccode.
     */
    public function postSimple(
        string $drAccode,
        string $crAccode,
        float $amount,
        array $header = []
    ): Daybookpart {
        $amount = round(abs($amount), 2);

        return $this->post($header, [
            ['accode' => $drAccode, 'opaccode' => $crAccode, 'amount' => $amount],
            ['accode' => $crAccode, 'opaccode' => $drAccode, 'amount' => -$amount],
        ]);
    }

    /** Cancel a voucher (keeps the audit trail, removes it from reports). */
    public function cancel(int $sno, ?string $reason = null): Daybookpart
    {
        return DB::transaction(function () use ($sno, $reason) {
            $part = Daybookpart::findOrFail($sno);
            $part->update([
                'status' => 'CANCELLED',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'narration' => trim(($part->narration ?? '').' | CANCELLED: '.$reason),
            ]);
            Daybook::where('sno', $sno)->update(['status' => 'CANCELLED']);

            return $part;
        });
    }

    /** Delete + re-post (used when an admin edits a voucher). */
    public function repost(int $sno, array $header, array $lines): Daybookpart
    {
        return DB::transaction(function () use ($sno, $header, $lines) {
            Daybook::where('sno', $sno)->delete();
            Daybookpart::where('sno', $sno)->delete();

            return $this->post($header, $lines);
        });
    }

    /* =========================================================================
     |  DOMAIN POSTINGS  (Scheme app -> Accounts)
     ========================================================================= */

    /**
     * Customer scheme installment received.
     *
     *   Dr  Cash / Bank            (total received)
     *       Cr  Customer ledger    (base amount  - company owes gold)
     *       Cr  GST Payable        (tax portion)
     */
    public function postSchemeReceipt(
        string $customerAccode,
        float $amount,
        float $tax,
        string $mode,
        string $date,
        array $meta = []
    ): Daybookpart {
        $cashAccode = $this->modeAccode($mode);
        $gstAccode = config('scheme.accounts.gst_payable');
        $total = round($amount + $tax, 2);

        $lines = [
            ['accode' => $cashAccode, 'opaccode' => $customerAccode, 'amount' => $total,
                'remarks' => 'Scheme collection'],
            ['accode' => $customerAccode, 'opaccode' => $cashAccode, 'amount' => -round($amount, 2),
                'remarks' => 'Scheme deposit'],
        ];

        if (round($tax, 2) > 0) {
            $lines[] = ['accode' => $gstAccode, 'opaccode' => $cashAccode, 'amount' => -round($tax, 2),
                'remarks' => 'GST on scheme collection'];
        }

        return $this->post(array_merge([
            'voucher_type' => 'RECEIPT',
            'voucher_date' => $date,
            'narration' => $meta['narration'] ?? 'Scheme installment received',
            'reference' => $meta['reference'] ?? null,
            'source_type' => 'payment',
            'source_id' => $meta['source_id'] ?? null,
        ], $meta['header'] ?? []), $lines);
    }

    /**
     * Agent commission payout.
     *
     *   Dr  Commission Expense
     *       Cr  Cash / Bank
     */
    public function postAgentCommission(
        float $amount,
        string $mode,
        string $date,
        array $meta = []
    ): Daybookpart {
        return $this->postSimple(
            config('scheme.accounts.agent_commission'),
            $this->modeAccode($mode),
            $amount,
            [
                'voucher_type' => 'PAYMENT',
                'voucher_date' => $date,
                'narration' => $meta['narration'] ?? 'Agent commission paid',
                'source_type' => 'agent_payout',
                'source_id' => $meta['source_id'] ?? null,
            ]
        );
    }

    /**
     * Scheme maturity / redemption - customer takes gold.
     *
     *   Dr  Customer ledger   (clears the deposit liability)
     *       Cr  Sales
     */
    public function postRedemption(
        string $customerAccode,
        float $amount,
        string $date,
        array $meta = []
    ): Daybookpart {
        return $this->postSimple(
            $customerAccode,
            config('scheme.accounts.sales'),
            $amount,
            [
                'voucher_type' => 'SALES',
                'voucher_date' => $date,
                'narration' => $meta['narration'] ?? 'Scheme maturity redemption',
                'source_type' => 'redeem',
                'source_id' => $meta['source_id'] ?? null,
            ]
        );
    }

    /**
     * Scheme maturity refund - customer receives money back.
     *
     *   Dr  Customer ledger
     *       Cr  Cash / Bank
     */
    public function postSchemeRefund(
        string $customerAccode,
        float $amount,
        string $mode,
        string $date,
        array $meta = []
    ): Daybookpart {
        return $this->postSimple(
            $customerAccode,
            $this->modeAccode($mode),
            $amount,
            [
                'voucher_type' => 'PAYMENT',
                'voucher_date' => $date,
                'narration' => $meta['narration'] ?? 'Scheme maturity refund',
                'source_type' => 'scheme_refund',
                'source_id' => $meta['source_id'] ?? null,
            ]
        );
    }

    /* =========================================================================
     |  LEDGER AUTO CREATION
     ========================================================================= */

    /** Create (or fetch) the ledger for a customer under Sundry Debtors. */
    public function ledgerForCustomer($customer): Accountm
    {
        if ($customer->accode && ($acc = Accountm::find($customer->accode))) {
            return $acc;
        }

        $grcode = 'G2010'; // Scheme Customers group (seeded)
        $accode = 'C'.str_pad((string) $customer->id, 6, '0', STR_PAD_LEFT);

        $acc = Accountm::updateOrCreate(['accode' => $accode], [
            'name' => $customer->name.' ('.$customer->reg_no.')',
            'grcode' => $grcode,
            'bshead' => 'BS',
            'actype' => 'CUSTOMER',
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'customer_id' => $customer->id,
            'opening_balance' => 0,
            'opening_drcr' => 'C',
            'status' => true,
        ]);

        $customer->forceFill(['accode' => $accode])->save();

        return $acc;
    }

    /** Create (or fetch) the ledger for an agent under Sundry Creditors. */
    public function ledgerForAgent($agent): Accountm
    {
        if ($agent->accode && ($acc = Accountm::find($agent->accode))) {
            return $acc;
        }

        $accode = 'A'.str_pad((string) $agent->id, 6, '0', STR_PAD_LEFT);

        $acc = Accountm::updateOrCreate(['accode' => $accode], [
            'name' => $agent->name.' ('.$agent->agent_code.')',
            'grcode' => 'G2020',   // Agents group
            'bshead' => 'BS',
            'actype' => 'AGENT',
            'phone' => $agent->phone,
            'email' => $agent->email,
            'address' => $agent->address,
            'agent_id' => $agent->id,
            'opening_balance' => 0,
            'opening_drcr' => 'C',
            'status' => true,
        ]);

        $agent->forceFill(['accode' => $accode])->save();

        return $acc;
    }

    /* =========================================================================
     |  HELPERS
     ========================================================================= */

    public function modeAccode(string $mode): string
    {
        return match (strtoupper($mode)) {
            'CASH' => config('scheme.accounts.cash'),
            'WALLET' => config('scheme.accounts.scheme_deposit'),
            default => config('scheme.accounts.bank'),   // UPI / CARD / BANK / CHEQUE
        };
    }

    /** Financial year label for a date, e.g. 2026-2027 */
    public function financialYear(string $date): string
    {
        [$m, $d] = explode('-', config('scheme.fy_start', '04-01'));
        $c = Carbon::parse($date);
        $startYear = ($c->month < (int) $m || ($c->month === (int) $m && $c->day < (int) $d))
            ? $c->year - 1
            : $c->year;

        return $startYear.'-'.($startYear + 1);
    }

    /** [from, to] Carbon dates of the FY containing $date */
    public function financialYearRange(string $date): array
    {
        [$m, $d] = explode('-', config('scheme.fy_start', '04-01'));
        $fy = $this->financialYear($date);
        $startYear = (int) explode('-', $fy)[0];

        $from = Carbon::create($startYear, (int) $m, (int) $d)->startOfDay();
        $to = $from->copy()->addYear()->subDay()->endOfDay();

        return [$from, $to];
    }

    public function nextVoucherNo(string $type, string $fy): string
    {
        $settingKey = 'voucher_prefix_'.strtolower($type);
        $prefix = \App\Models\Setting::get($settingKey) ?: match ($type) {
            'RECEIPT' => 'RCP',
            'PAYMENT' => 'PMT',
            'JOURNAL' => 'JV',
            'CONTRA' => 'CON',
            'SALES' => 'SAL',
            'PURCHASE' => 'PUR',
            'CREDITNOTE' => 'CRN',
            'DEBITNOTE' => 'DBN',
            'OPENING' => 'OPN',
            default => 'VCH',
        };

        $count = Daybookpart::where('voucher_type', $type)->where('fy', $fy)->count();

        return sprintf('%s/%s/%05d', $prefix, substr($fy, 2, 2).substr($fy, -2), $count + 1);
    }
}
