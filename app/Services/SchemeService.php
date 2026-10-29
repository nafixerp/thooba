<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Installment;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\Point;
use App\Models\PointTransaction;
use App\Models\Scheme;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Scheme business logic. Ella money movement-um AccountingService vazhi
 * daybook-il post aakunnu.
 */
class SchemeService
{
    public function __construct(
        protected AccountingService $accounts,
        protected GstService $gst,
        protected PrivilegeService $privileges,
    ) {}

    /* =========================================================================
     |  ENROLL
     ========================================================================= */

    public function enroll(Customer $customer, Scheme $scheme, array $data = []): CustomerScheme
    {
        return DB::transaction(function () use ($customer, $scheme, $data) {
            $start = Carbon::parse($data['start_date'] ?? now());
            $amount = (float) ($data['installment_amount'] ?? $scheme->amount);
            $count = (int) ($data['total_installments'] ?? $scheme->total_installments);

            if ($scheme->flexible_amount) {
                if ($scheme->min_amount > 0 && $amount < (float) $scheme->min_amount) {
                    throw new RuntimeException('Amount is below the scheme minimum.');
                }
                if ($scheme->max_amount > 0 && $amount > (float) $scheme->max_amount) {
                    throw new RuntimeException('Amount is above the scheme maximum.');
                }
            }

            $cs = CustomerScheme::create([
                'account_no' => CustomerScheme::nextAccountNo(),
                // The paper passbook number. Proposed, not imposed: staff
                // overwrite it when the book in hand says something else.
                'book_no' => filled($data['book_no'] ?? null)
                    ? trim((string) $data['book_no'])
                    : CustomerScheme::nextBookNo(),
                'source' => $data['source'] ?? 'ADMIN',
                'customer_id' => $customer->id,
                'scheme_id' => $scheme->id,
                'agent_id' => $data['agent_id'] ?? $customer->agent_id,
                'installment_amount' => $amount,
                'total_installments' => $count,
                'payment_interval' => $scheme->payment_interval,
                'start_date' => $start->toDateString(),
                'status' => 'ACTIVE',
            ]);

            // Build the installment schedule
            $rows = [];
            for ($i = 1; $i <= $count; $i++) {
                $rows[] = [
                    'customer_scheme_id' => $cs->id,
                    'installment_no' => $i,
                    'due_date' => $cs->nextDue($start, $i - 1)->toDateString(),
                    'amount' => $amount,
                    'status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Installment::insert($rows);

            $end = Carbon::parse(end($rows)['due_date']);
            $cs->update([
                'end_date' => $end->toDateString(),
                'maturity_date' => $end->toDateString(),
            ]);

            // Make sure the customer has a ledger
            $this->accounts->ledgerForCustomer($customer);

            return $cs->fresh();
        });
    }

    /* =========================================================================
     |  PAYMENT
     ========================================================================= */

    /**
     * Record a payment. Status PENDING aanenkil accounting post cheyyilla;
     * approve cheyyumbol mathram daybook-il varum.
     */
    public function recordPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);
            $cs = ! empty($data['customer_scheme_id'])
                ? CustomerScheme::find($data['customer_scheme_id'])
                : null;

            /*
             | GstService decides how the money splits, so the receipt always
             | satisfies: money received = scheme credit + GST.
             |
             |   'amount'   the installment the customer is settling
             |   'received' set instead when the caller knows the exact sum that
             |              changed hands - a captured gateway payment - so the
             |              tax is taken out of it rather than added to it.
             */
            $quote = isset($data['received'])
                ? $this->gst->split(round((float) $data['received'], 2), $cs)
                : $this->gst->quote(round((float) $data['amount'], 2), $cs);

            $amount = $quote['base'];
            $tax = isset($data['tax']) ? round((float) $data['tax'], 2) : $quote['tax'];
            $total = round($amount + $tax, 2);
            $date = $data['payment_date'] ?? now()->toDateString();

            $rate = (float) ($data['rate'] ?? MetalRate::latest_rate($cs?->scheme?->metal_id, $date));
            $gram = $rate > 0 ? round($amount / $rate, 3) : 0;

            // Which installment does this settle?
            $installment = null;
            if ($cs) {
                $installment = ! empty($data['installment_id'])
                    ? Installment::find($data['installment_id'])
                    : $cs->installments()->pending()->orderBy('installment_no')->first();
            }

            $payment = Payment::create([
                'receipt_no' => Payment::nextReceiptNo(),
                'customer_id' => $customer->id,
                'customer_scheme_id' => $cs?->id,
                'installment_id' => $installment?->id,
                'agent_id' => $data['agent_id'] ?? $customer->agent_id,
                'amount' => $amount,
                'tax' => $tax,
                'total' => $total,
                'rate' => $rate,
                'gram' => $gram,
                'payment_date' => $date,
                'payment_type' => $data['payment_type'] ?? 'INSTALLMENT',
                'payment_mode' => $data['payment_mode'] ?? 'CASH',
                'txn_ref' => $data['txn_ref'] ?? null,
                'screenshot' => $data['screenshot'] ?? null,
                'bank_id' => $data['bank_id'] ?? null,
                'status' => $data['status'] ?? 'PENDING',
                'remarks' => $data['remarks'] ?? null,
            ]);

            if ($payment->status === 'APPROVED') {
                $this->approvePayment($payment);
            }

            return $payment->fresh();
        });
    }

    /**
     * Approve a payment:
     *   1. post the double entry
     *   2. update the installment
     *   3. update scheme totals + customer wallet/gram
     */
    public function approvePayment(Payment $payment, ?int $userId = null): Payment
    {
        if ($payment->status === 'APPROVED' && $payment->daybook_sno) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $userId) {
            $customer = $payment->customer;
            $ledger = $this->accounts->ledgerForCustomer($customer);

            $voucher = $this->accounts->postSchemeReceipt(
                $ledger->accode,
                (float) $payment->amount,
                (float) $payment->tax,
                $payment->payment_mode,
                $payment->payment_date->toDateString(),
                [
                    'narration' => 'Scheme collection - '.$customer->name.' ('.$customer->reg_no.') Receipt '.$payment->receipt_no,
                    'reference' => $payment->receipt_no,
                    'source_id' => $payment->id,
                ]
            );

            $payment->update([
                'status' => 'APPROVED',
                'approved_by' => $userId ?? auth()->id(),
                'approved_at' => now(),
                'daybook_sno' => $voucher->sno,
            ]);

            // Installment. Under INCLUSIVE the customer handed over the whole
            // installment and the GST came out of it, so the row is settled by
            // what was received - otherwise every one would stay PARTIAL.
            if ($inst = $payment->installment) {
                $settles = $this->gst->settlesByTotal()
                    ? (float) $payment->total
                    : (float) $payment->amount;

                $paid = round((float) $inst->paid_amount + $settles, 2);
                $inst->update([
                    'paid_amount' => $paid,
                    'gram' => (float) $inst->gram + (float) $payment->gram,
                    'paid_date' => $payment->payment_date,
                    'payment_id' => $payment->id,
                    'status' => $paid + 0.005 >= (float) $inst->amount ? 'PAID' : 'PARTIAL',
                ]);
            }

            // Scheme totals
            if ($cs = $payment->customerScheme) {
                $cs->update([
                    'total_paid' => round((float) $cs->total_paid + (float) $payment->amount, 2),
                    'total_gst' => round((float) $cs->total_gst + (float) $payment->tax, 2),
                    'total_gram' => round((float) $cs->total_gram + (float) $payment->gram, 3),
                    'paid_installments' => $cs->installments()->where('status', 'PAID')->count(),
                ]);

                if ($cs->paid_installments >= $cs->total_installments) {
                    $cs->update(['status' => 'MATURED']);
                }
            }

            // Customer running totals
            $customer->increment('wallet_amount', (float) $payment->amount);
            $customer->increment('total_gram', (float) $payment->gram);

            // Reward points follow the customer's sales tier. The source_id
            // makes this safe when a gateway callback is retried.
            $this->privileges->awardPayment($payment->fresh(['customer']));

            return $payment->fresh();
        });
    }

    public function rejectPayment(Payment $payment, ?string $reason = null): Payment
    {
        $payment->update([
            'status' => 'REJECTED',
            'reject_reason' => $reason,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $payment;
    }

    /* =========================================================================
     |  MATURITY / REDEEM
     ========================================================================= */

    public function redeem(CustomerScheme $cs, array $data = []): array
    {
        return DB::transaction(function () use ($cs, $data) {
            if (in_array($cs->status, ['CLOSED', 'CANCELLED'], true)) {
                throw new RuntimeException('This scheme is already closed.');
            }

            $customer = $cs->customer;
            $ledger = $this->accounts->ledgerForCustomer($customer);
            $amount = round((float) ($data['amount'] ?? $cs->total_paid + $cs->bonus_amount), 2);
            $date = $data['date'] ?? now()->toDateString();
            $type = strtoupper($data['closure_type'] ?? 'GOLD_REDEEM');
            if ($type === 'NO_PAYOUT') {
                $amount = 0;
            }

            $voucher = null;
            if ($amount > 0 && $type === 'REFUND') {
                $voucher = $this->accounts->postSchemeRefund(
                    $ledger->accode,
                    $amount,
                    $data['payment_mode'] ?? 'CASH',
                    $date,
                    [
                        'narration' => 'Scheme refund - '.$cs->account_no.' - '.$customer->name,
                        'source_id' => $cs->id,
                    ]
                );
            } elseif ($amount > 0 && $type === 'GOLD_REDEEM') {
                $voucher = $this->accounts->postRedemption($ledger->accode, $amount, $date, [
                    'narration' => 'Scheme maturity - '.$cs->account_no.' - '.$customer->name,
                    'source_id' => $cs->id,
                ]);
            }

            $cs->update([
                'status' => 'CLOSED',
                'closed_date' => $date,
                'closure_type' => $type,
                'closure_amount' => $amount,
                'closure_mode' => $type === 'REFUND' ? ($data['payment_mode'] ?? 'CASH') : null,
                'closure_daybook_sno' => $voucher?->sno,
                'remarks' => trim(($cs->remarks ?? '').' Closed on '.$date.' ('.$type.'). '.($data['note'] ?? '')),
            ]);

            if ($amount > 0) {
                $customer->decrement('wallet_amount', $amount);
            }

            $newScheme = null;
            if (! empty($data['create_new'])) {
                $newScheme = $this->enroll($customer, $cs->scheme, [
                    'agent_id' => $cs->agent_id,
                    'installment_amount' => (float) $cs->installment_amount,
                    'start_date' => $data['new_start_date'] ?? now()->toDateString(),
                    'total_installments' => $cs->total_installments,
                ]);
                $newScheme->update(['renewed_from_id' => $cs->id]);
            }

            return ['scheme' => $cs->fresh(), 'voucher' => $voucher, 'new_scheme' => $newScheme];
        });
    }

    public function block(CustomerScheme $cs, array $data = []): CustomerScheme
    {
        if (in_array($cs->status, ['CLOSED', 'CANCELLED'], true)) {
            throw new RuntimeException('This scheme is already closed.');
        }

        $date = $data['date'] ?? now()->toDateString();
        $note = trim((string) ($data['note'] ?? ''));

        $cs->update([
            'status' => 'CANCELLED',
            'closed_date' => $date,
            'closure_type' => 'NO_PAYOUT',
            'closure_amount' => 0,
            'closure_mode' => null,
            'remarks' => trim(($cs->remarks ?? '').' Blocked/Cancelled on '.$date.'. '.$note),
        ]);

        return $cs->fresh();
    }

    public function markMaturityNotified(CustomerScheme $cs, array $data = []): CustomerScheme
    {
        $note = trim((string) ($data['note'] ?? ''));

        $cs->update([
            'maturity_notified_at' => now(),
            'maturity_notification_note' => $note !== '' ? $note : null,
        ]);

        return $cs->fresh();
    }

    /* =========================================================================
     |  POINTS
     ========================================================================= */

    public function addPoints(Customer $customer, float $points, string $source = 'MANUAL', ?string $note = null): PointTransaction
    {
        return DB::transaction(function () use ($customer, $points, $source, $note) {
            $p = Point::firstOrCreate(
                ['customer_id' => $customer->id],
                ['referral_id' => $customer->referral_code]
            );

            $p->increment('balance_point', $points);
            $p->increment('total_point', $points);

            return PointTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'EARN',
                'points' => $points,
                'source' => $source,
                'txn_date' => now()->toDateString(),
                'note' => $note,
                'status' => 'APPROVED',
            ]);
        });
    }

    public function redeemPoints(Customer $customer, float $points, array $data = []): PointTransaction
    {
        return DB::transaction(function () use ($customer, $points, $data) {
            $p = Point::where('customer_id', $customer->id)->lockForUpdate()->first();

            if (! $p || (float) $p->balance_point < $points) {
                throw new RuntimeException('Not enough points to redeem.');
            }

            $p->decrement('balance_point', $points);
            $p->increment('redeemed_point', $points);

            return PointTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'REDEEM',
                'points' => $points,
                'amount' => $data['amount'] ?? 0,
                'gram' => $data['gram'] ?? 0,
                'source' => 'REDEEM',
                'txn_date' => $data['date'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
                'status' => 'APPROVED',
            ]);
        });
    }

    /* =========================================================================
     |  MAINTENANCE
     ========================================================================= */

    /** Mark overdue installments (run daily via scheduler). */
    public function markOverdue(): int
    {
        return Installment::whereIn('status', ['PENDING', 'PARTIAL'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'OVERDUE']);
    }

    public function expiringSoon(int $days = 7)
    {
        return Installment::with('customerScheme.customer')
            ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('due_date')
            ->get();
    }
}
