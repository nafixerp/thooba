<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Point;
use App\Models\PointTransaction;
use App\Models\PrivilegeTier;

class PrivilegeService
{
    /** Current sales tier and progress towards the next card. */
    public function summary(Customer $customer): array
    {
        $approved = $customer->payments()->approved();
        $sales = (float) (clone $approved)->sum('amount');
        $grams = (float) (clone $approved)->sum('gram');
        $tiers = PrivilegeTier::active()->orderBy('sortorder')->orderBy('id')->get();

        $eligible = $tiers->filter(fn (PrivilegeTier $candidate) =>
            $this->metric($candidate, $sales, $grams) >= $this->threshold($candidate)
        );
        $tier = $eligible->sortByDesc('sortorder')->first();
        $next = $tiers
            ->filter(fn (PrivilegeTier $candidate) =>
                $this->metric($candidate, $sales, $grams) < $this->threshold($candidate)
                && (! $tier || $candidate->sortorder > $tier->sortorder)
            )
            ->sortBy('sortorder')
            ->first();

        $basis = $tier?->basis ?? 'AMOUNT';
        $currentValue = $basis === 'GRAM' ? $grams : $sales;
        $nextValue = $next ? $this->metric($next, $sales, $grams) : 0;

        return [
            'tier' => $tier?->name,
            'card_color' => $tier?->card_color,
            'benefits' => $tier?->benefits,
            'sales' => round($sales, 2),
            'grams' => round($grams, 3),
            'basis' => $basis,
            'points_per_100' => (float) ($tier?->points_per_100 ?? 0),
            'points_per_gram' => (float) ($tier?->points_per_gram ?? 0),
            'current_value' => round($currentValue, $basis === 'GRAM' ? 3 : 2),
            'next_tier' => $next?->name,
            'next_basis' => $next?->basis,
            'next_at' => $next ? $this->threshold($next) : null,
            'to_next' => $next ? max(0, round($this->threshold($next) - $nextValue, $next->basis === 'GRAM' ? 3 : 2)) : 0,
        ];
    }

    /** Award the tier rate once for an approved payment. */
    public function awardPayment(Payment $payment): ?PointTransaction
    {
        if (PointTransaction::where('source', 'PAYMENT')->where('source_id', $payment->id)->exists()) {
            return null;
        }

        $summary = $this->summary($payment->customer);
        $points = $summary['basis'] === 'GRAM'
            ? round((float) $payment->gram * $summary['points_per_gram'], 2)
            : round((float) $payment->amount / 100 * $summary['points_per_100'], 2);
        if ($points <= 0) {
            return null;
        }

        $wallet = Point::firstOrCreate(
            ['customer_id' => $payment->customer_id],
            ['referral_id' => $payment->customer->referral_code]
        );
        $wallet->increment('balance_point', $points);
        $wallet->increment('total_point', $points);

        return PointTransaction::create([
            'customer_id' => $payment->customer_id,
            'type' => 'EARN',
            'points' => $points,
            'amount' => $payment->amount,
            'gram' => $payment->gram,
            'source' => 'PAYMENT',
            'source_id' => $payment->id,
            'txn_date' => $payment->payment_date,
            'note' => ($summary['tier'] ?? 'Privilege').' card reward',
            'status' => 'APPROVED',
        ]);
    }

    protected function metric(PrivilegeTier $tier, float $sales, float $grams): float
    {
        return $tier->basis === 'GRAM' ? $grams : $sales;
    }

    protected function threshold(PrivilegeTier $tier): float
    {
        return $tier->basis === 'GRAM' ? (float) $tier->min_gram : (float) $tier->min_sales;
    }
}
