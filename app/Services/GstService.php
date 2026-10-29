<?php

namespace App\Services;

use App\Models\CustomerScheme;
use App\Models\Scheme;
use App\Models\Setting;

/**
 * -----------------------------------------------------------------------------
 *  GstService - one place that decides how GST sits on a scheme collection
 * -----------------------------------------------------------------------------
 *
 * Three treatments, chosen from Settings -> GST mode:
 *
 *   EXCLUSIVE   GST is added on top. A 5,000 installment at 3% means the
 *               customer hands over 5,150; 5,000 is credited to their scheme
 *               and 150 is GST payable.
 *
 *   INCLUSIVE   The installment already contains the GST. The customer hands
 *               over 5,000; 4,854.37 is credited to their scheme and 145.63 is
 *               GST payable. The installment still counts as settled, because
 *               they paid the amount they agreed to.
 *
 *   NONE        No GST on scheme collections. Amount received = amount
 *               credited. (Usual for a savings scheme where the tax falls on
 *               the gold at maturity, not on the deposit.)
 *
 * The rule that matters, whichever mode is on:
 *
 *      money actually received  ==  scheme credit + GST
 *
 * which is exactly the voucher AccountingService posts:
 *
 *      Dr Cash/Bank  total
 *          Cr Customer        base
 *          Cr GST Payable     tax
 *
 * Before this existed the online gateway charged the base while the books
 * recorded base + tax, so every card/UPI collection overstated the bank by the
 * GST.
 */
class GstService
{
    public const EXCLUSIVE = 'EXCLUSIVE';

    public const INCLUSIVE = 'INCLUSIVE';

    public const NONE = 'NONE';

    /** The configured mode, falling back to the .env default. */
    public function mode(): string
    {
        $mode = strtoupper((string) Setting::get('gst_mode', config('scheme.gst_mode', self::EXCLUSIVE)));

        return in_array($mode, [self::EXCLUSIVE, self::INCLUSIVE, self::NONE], true)
            ? $mode
            : self::EXCLUSIVE;
    }

    /** Rate for a scheme, falling back to the shop-wide percentage. */
    public function percent(Scheme|CustomerScheme|null $scheme = null): float
    {
        if ($this->mode() === self::NONE) {
            return 0.0;
        }

        $scheme = $scheme instanceof CustomerScheme ? $scheme->scheme : $scheme;

        $percent = $scheme?->gst_percent;

        if ($percent === null || $percent === '') {
            $percent = Setting::get('gst_percent', config('scheme.gst_percent', 0));
        }

        return max(0.0, (float) $percent);
    }

    /**
     * What a customer has to hand over to settle an installment, and how that
     * splits. $installment is the scheme's installment amount.
     *
     * @return array{base: float, tax: float, total: float, mode: string, percent: float}
     */
    public function quote(float $installment, Scheme|CustomerScheme|null $scheme = null): array
    {
        $installment = round($installment, 2);
        $mode = $this->mode();
        $percent = $this->percent($scheme);

        if ($percent <= 0 || $mode === self::NONE) {
            return $this->row($installment, 0.0, $installment, self::NONE, 0.0);
        }

        if ($mode === self::INCLUSIVE) {
            // The installment already contains the tax, so work backwards.
            return $this->splitInclusive($installment, $percent);
        }

        $tax = round($installment * $percent / 100, 2);

        return $this->row($installment, $tax, round($installment + $tax, 2), self::EXCLUSIVE, $percent);
    }

    /**
     * Split an amount that has already been received - a cash collection, or a
     * captured gateway payment - into scheme credit and GST.
     *
     * @return array{base: float, tax: float, total: float, mode: string, percent: float}
     */
    public function split(float $received, Scheme|CustomerScheme|null $scheme = null): array
    {
        $received = round($received, 2);
        $mode = $this->mode();
        $percent = $this->percent($scheme);

        if ($percent <= 0 || $mode === self::NONE) {
            return $this->row($received, 0.0, $received, self::NONE, 0.0);
        }

        if ($mode === self::INCLUSIVE) {
            return $this->splitInclusive($received, $percent);
        }

        // Exclusive: what came in is base + tax, so take the tax back out of it.
        $base = round($received * 100 / (100 + $percent), 2);

        return $this->row($base, round($received - $base, 2), $received, self::EXCLUSIVE, $percent);
    }

    /**
     * Does a payment settle its installment by the money received rather than
     * by the scheme credit?
     *
     * Under INCLUSIVE the customer paid the full installment - the GST simply
     * came out of it - so the schedule has to close, otherwise every row would
     * sit at PARTIAL forever.
     */
    public function settlesByTotal(): bool
    {
        return $this->mode() === self::INCLUSIVE;
    }

    /** Label for the admin screen and the app. */
    public function label(): string
    {
        return config('scheme.gst_modes')[$this->mode()] ?? $this->mode();
    }

    /* ===================================================================== */

    /**
     * @return array{base: float, tax: float, total: float, mode: string, percent: float}
     */
    protected function splitInclusive(float $total, float $percent): array
    {
        $base = round($total * 100 / (100 + $percent), 2);

        return $this->row($base, round($total - $base, 2), $total, self::INCLUSIVE, $percent);
    }

    /**
     * @return array{base: float, tax: float, total: float, mode: string, percent: float}
     */
    protected function row(float $base, float $tax, float $total, string $mode, float $percent): array
    {
        return [
            'base' => round($base, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'mode' => $mode,
            'percent' => $percent,
        ];
    }
}
