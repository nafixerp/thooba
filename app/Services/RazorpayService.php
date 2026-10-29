<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay orders over the plain REST API — no composer SDK needed.
 *
 * Flow:
 *   1. App calls POST /api/v1/payment/order  -> createOrder()
 *   2. App opens the Razorpay checkout with the returned order_id + key_id
 *   3. App posts the checkout result to POST /api/v1/payment/verify -> verifySignature()
 */
class RazorpayService
{
    protected const BASE = 'https://api.razorpay.com/v1';

    public function configured(): bool
    {
        return $this->status()['enabled'];
    }

    /**
     * Whether online payment is usable, and if not, why.
     *
     * The reason matters. Before this existed a shop testing with `rzp_test_`
     * keys while RAZORPAY_LIVE_MODE was still true (the default) saw the app
     * say "GPay / UPI is temporarily unavailable" with nothing anywhere
     * explaining that the mode flag was the cause. The string is safe to show:
     * it never contains a key or a secret.
     *
     * @return array{enabled: bool, reason: ?string, mode: string, key_type: ?string}
     */
    public function status(): array
    {
        $cfg = config('mobile.payment.razorpay');
        $live = (bool) $cfg['live_mode'];
        $keyId = (string) ($cfg['key_id'] ?? '');

        $keyType = match (true) {
            str_starts_with($keyId, 'rzp_live_') => 'live',
            str_starts_with($keyId, 'rzp_test_') => 'test',
            $keyId !== '' => 'unknown',
            default => null,
        };

        $fail = fn (string $reason) => [
            'enabled' => false,
            'reason' => $reason,
            'mode' => $live ? 'live' : 'test',
            'key_type' => $keyType,
        ];

        if ($keyId === '' || empty($cfg['key_secret'])) {
            return $fail('Razorpay keys are not set. Add RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.');
        }

        if ($keyType === 'unknown') {
            return $fail('RAZORPAY_KEY_ID does not look like a Razorpay key (it should start with rzp_live_ or rzp_test_).');
        }

        // Live mode must not run on test keys - that is the guard worth keeping.
        if ($live && $keyType === 'test') {
            return $fail('RAZORPAY_LIVE_MODE is on but the key is a test key. Use live keys, or set RAZORPAY_LIVE_MODE=false while testing.');
        }

        // The reverse is only worth a note; a live key in test mode still works.
        return [
            'enabled' => true,
            'reason' => $live || $keyType !== 'live'
                ? null
                : 'Running live keys with RAZORPAY_LIVE_MODE=false.',
            'mode' => $live ? 'live' : 'test',
            'key_type' => $keyType,
        ];
    }

    public function keyId(): ?string
    {
        return config('mobile.payment.razorpay.key_id');
    }

    /** Create a Razorpay order and the local PaymentOrder row that tracks it. */
    public function createOrder(Customer $customer, float $amount, array $meta = []): PaymentOrder
    {
        if (! $this->configured()) {
            throw new RuntimeException('Live GPay / UPI payment is not configured.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Invalid payment amount.');
        }

        $order = PaymentOrder::create([
            'order_no' => PaymentOrder::nextOrderNo(),
            'gateway' => 'razorpay',
            'customer_id' => $customer->id,
            'customer_scheme_id' => $meta['customer_scheme_id'] ?? null,
            'amount' => round($amount, 2),
            'currency' => config('mobile.payment.razorpay.currency', 'INR'),
            'status' => 'CREATED',
            'meta' => $meta,
        ]);

        $res = $this->client()->post(self::BASE.'/orders', [
            // Razorpay works in the smallest currency unit (paise).
            'amount' => (int) round($amount * 100),
            'currency' => $order->currency,
            'receipt' => $order->order_no,
            'notes' => [
                'customer_id' => (string) $customer->id,
                'reg_no' => (string) $customer->reg_no,
                'scheme_id' => (string) ($meta['customer_scheme_id'] ?? ''),
            ],
        ]);

        if (! $res->successful()) {
            $order->update([
                'status' => 'FAILED',
                'failure_reason' => $res->json('error.description') ?? 'Gateway order creation failed',
            ]);

            throw new RuntimeException($order->failure_reason);
        }

        $order->update(['gateway_order_id' => $res->json('id')]);

        return $order->fresh();
    }

    /**
     * Verify the checkout callback signature.
     * HMAC-SHA256 of "{order_id}|{payment_id}" keyed with the API secret.
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac(
            'sha256',
            $orderId.'|'.$paymentId,
            (string) config('mobile.payment.razorpay.key_secret')
        );

        return hash_equals($expected, $signature);
    }

    /** Verify a webhook body against the webhook secret. */
    public function verifyWebhook(string $payload, string $signature, string $secret): bool
    {
        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /** Pull the authoritative payment record from Razorpay. */
    public function fetchPayment(string $paymentId): array
    {
        $res = $this->client()->get(self::BASE.'/payments/'.$paymentId);

        if (! $res->successful()) {
            throw new RuntimeException('Could not fetch the payment from Razorpay.');
        }

        return $res->json();
    }

    protected function client()
    {
        $cfg = config('mobile.payment.razorpay');

        return Http::withBasicAuth($cfg['key_id'], $cfg['key_secret'])
            ->asJson()
            ->timeout(30);
    }
}
