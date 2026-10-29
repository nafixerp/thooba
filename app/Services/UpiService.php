<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Setting;

/**
 * -----------------------------------------------------------------------------
 *  UpiService - pay straight into the shop's VPA, no aggregator
 * -----------------------------------------------------------------------------
 *
 * The app opens Google Pay / PhonePe / Paytm with a `upi://pay` link addressed
 * to the shop's own UPI id. Nothing sits in the middle: no Razorpay, no per
 * transaction fee, and the money reaches the shop account straight away.
 *
 *     upi://pay?pa=<vpa>&pn=<payee>&am=<amount>&cu=INR&tn=<note>&tr=<ref>
 *
 * What this CANNOT do, and it is the reason every such payment is booked
 * PENDING: prove the money arrived. The result a UPI app hands back is client
 * side and trivially forged, and with no payment service provider there is no
 * signed callback to check it against. The office confirms the UTR against the
 * bank statement before it reaches the books - the same gate an agent's cash
 * collection goes through.
 *
 * Razorpay, when it is configured, does not have that problem: its signature
 * and the capture check take a payment straight to APPROVED.
 */
class UpiService
{
    /** Shop VPA, from Settings, then .env, then the first active bank. */
    public function vpa(): ?string
    {
        $vpa = Setting::get('upi_id')
            ?: config('mobile.payment.upi.vpa')
            ?: Bank::where('status', true)->whereNotNull('upi_id')->value('upi_id');

        $vpa = trim((string) $vpa);

        return $this->looksLikeVpa($vpa) ? $vpa : null;
    }

    /** Name shown in the UPI app's confirmation screen. */
    public function payeeName(): string
    {
        $name = Setting::get('upi_payee_name')
            ?: config('mobile.payment.upi.payee_name')
            ?: Setting::get('company_name')
            ?: config('mobile.app_name');

        // The UPI spec is unhappy with most punctuation in pn.
        return trim(preg_replace('/[^A-Za-z0-9 .&-]/', '', (string) $name)) ?: 'Shop';
    }

    public function enabled(): bool
    {
        return (bool) config('mobile.payment.upi.enabled') && $this->vpa() !== null;
    }

    /**
     * Why it is off, when it is. Safe to show - it never contains anything
     * secret, a VPA being a public address.
     */
    public function disabledReason(): ?string
    {
        if (! config('mobile.payment.upi.enabled')) {
            return 'Direct UPI payment is switched off.';
        }

        if ($this->vpa() === null) {
            return 'No UPI id is set. Add one under Settings, or on a bank record.';
        }

        return null;
    }

    /**
     * The `upi://pay` link, plus the app-specific variants.
     *
     * `tr` carries our own order number, which is what the office matches
     * against the bank statement afterwards.
     *
     * @return array{upi: string, gpay: string, phonepe: string, paytm: string}
     */
    public function links(float $amount, string $orderNo, string $note): array
    {
        $query = http_build_query([
            'pa' => $this->vpa(),
            'pn' => $this->payeeName(),
            // UPI wants a plain two decimal figure, no thousands separators.
            'am' => number_format(round($amount, 2), 2, '.', ''),
            'cu' => 'INR',
            'tn' => $this->note($note),
            'tr' => $orderNo,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            // The generic one: Android shows a chooser of every installed UPI app.
            'upi' => 'upi://pay?'.$query,
            // Straight into one app, for the shortcut buttons.
            'gpay' => 'tez://upi/pay?'.$query,
            'phonepe' => 'phonepe://pay?'.$query,
            'paytm' => 'paytmmp://pay?'.$query,
        ];
    }

    /** Transaction note: UPI caps it, and rejects most punctuation. */
    protected function note(string $note): string
    {
        $clean = preg_replace('/[^A-Za-z0-9 ]/', ' ', $note);

        return trim(mb_substr(preg_replace('/\s+/', ' ', $clean), 0, 45)) ?: 'Scheme installment';
    }

    protected function looksLikeVpa(string $vpa): bool
    {
        return $vpa !== '' && (bool) preg_match('/^[A-Za-z0-9._-]{2,}@[A-Za-z]{2,}$/', $vpa);
    }
}
