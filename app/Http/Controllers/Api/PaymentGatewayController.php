<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerScheme;
use App\Models\PaymentWebhookLog;
use App\Models\PaymentOrder;
use App\Services\RazorpayService;
use App\Services\SchemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Online payments through Razorpay.
 *
 *   POST /api/v1/payment/order   -> create a gateway order
 *   POST /api/v1/payment/verify  -> confirm the checkout result, book the payment
 *   POST /api/v1/payment/failed  -> record an abandoned/failed attempt
 *   POST /api/v1/payment/webhook -> Razorpay server-to-server confirmation
 *
 * When the Razorpay keys are blank the whole flow is reported as disabled.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpay,
        protected SchemeService $service,
        protected \App\Services\GstService $gst,
        protected \App\Services\UpiService $upi,
    ) {}

    /** GET /api/v1/payment/methods — what the app should offer right now. */
    public function methods(Request $request)
    {
        $status = $this->razorpay->status();

        return $this->ok([
            'gateway_enabled' => $status['enabled'],
            // Why it is off, so the app can say something useful instead of
            // "temporarily unavailable". Never contains a key or a secret.
            'disabled_reason' => $status['enabled'] ? null : $status['reason'],
            'gateway' => 'razorpay',
            'razorpay_key' => $status['enabled'] ? $this->razorpay->keyId() : null,
            'theme_color' => config('mobile.payment.razorpay.theme_color'),
            'currency' => config('mobile.payment.razorpay.currency', 'INR'),
            // Whether checkout is pinned to UPI only. Off by default: forcing a
            // single block can leave a customer with no usable method, and it
            // is not worth a support call to save one tap.
            'upi_only' => (bool) config('mobile.payment.razorpay.upi_only'),
            'live_mode' => (bool) config('mobile.payment.razorpay.live_mode'),
            // So the payment screen can show the breakup before charging.
            'gst_mode' => $this->gst->mode(),
            'gst_percent' => $this->gst->percent(),

            /*
             | Pay straight into the shop's VPA with no aggregator in between.
             | Offered alongside Razorpay, and the only option when Razorpay is
             | off. Always lands PENDING - see UpiService for why.
             */
            'upi_direct' => [
                'enabled' => $this->upi->enabled(),
                'disabled_reason' => $this->upi->disabledReason(),
                'vpa' => $this->upi->vpa(),
                'payee_name' => $this->upi->payeeName(),
                'needs_confirmation' => true,
            ],
        ]);
    }

    /**
     * POST /api/v1/payment/order
     * Body: amount, customer_scheme_id
     */
    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'customer_scheme_id' => ['required', 'integer', 'exists:customer_schemes,id'],
        ]);

        $customer = $request->user();
        $scheme = CustomerScheme::with('scheme:id,name')
            ->whereKey((int) $data['customer_scheme_id'])
            ->where('customer_id', $customer->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $scheme) {
            return $this->fail('An active scheme is required before making a payment.', 422);
        }

        if ($scheme->pending_installments < 1) {
            return $this->fail('This scheme has no installments remaining.', 422);
        }

        $remainingAmount = (float) $scheme->installment_amount * $scheme->pending_installments;
        if ((float) $data['amount'] > $remainingAmount) {
            return $this->fail('The amount is more than the remaining scheme balance.', 422);
        }

        /*
         | Charge what the books will record. The gateway used to be handed the
         | installment while the receipt was written for installment + GST, so
         | every online collection debited the bank with money that never
         | arrived. GstService settles the split for whichever mode is on.
         */
        $quote = $this->gst->quote((float) $data['amount'], $scheme);

        try {
            $order = $this->razorpay->createOrder($customer, $quote['total'], [
                'customer_scheme_id' => $scheme->id,
                'base' => $quote['base'],
                'tax' => $quote['tax'],
                'gst_mode' => $quote['mode'],
                'gst_percent' => $quote['percent'],
            ]);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'order_no' => $order->order_no,
            'razorpay_order_id' => $order->gateway_order_id,
            'razorpay_key' => $this->razorpay->keyId(),
            // Razorpay checkout expects paise.
            'amount_paise' => (int) round((float) $order->amount * 100),
            'amount' => (float) $order->amount,
            // The breakdown the app shows above the Pay button.
            'installment' => $quote['base'],
            'tax' => $quote['tax'],
            'total' => $quote['total'],
            'gst_mode' => $quote['mode'],
            'gst_percent' => $quote['percent'],
            'currency' => $order->currency,
            'name' => $this->shopName(),
            'description' => $scheme->scheme->name.' - '.$scheme->account_no,
            'theme_color' => config('mobile.payment.razorpay.theme_color'),
            'prefill' => [
                'name' => $customer->name,
                'contact' => $customer->phone,
                'email' => $customer->email,
            ],
        ], 'Order created');
    }

    /** What the shop calls itself, so the checkout sheet is branded from the admin panel. */
    protected function shopName(): string
    {
        return (string) (\App\Models\Setting::get('company_name') ?: config('mobile.app_name'));
    }

    /**
     * POST /api/v1/payment/verify
     * Body: razorpay_order_id, razorpay_payment_id, razorpay_signature
     */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $order = PaymentOrder::where('gateway_order_id', $data['razorpay_order_id'])
            ->where('customer_id', $request->user()->id)
            ->first();

        if (! $order) {
            return $this->fail('Payment order not found.', 404);
        }

        if ($order->status === 'PAID' && $order->payment_id) {
            // Checkout callback and webhook can both arrive; only book it once.
            return $this->ok($this->orderPayload($order->fresh()), 'Payment already recorded');
        }

        if (! $this->razorpay->verifySignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature']
        )) {
            $order->update([
                'status' => 'FAILED',
                'failure_reason' => 'Signature verification failed',
                'gateway_payment_id' => $data['razorpay_payment_id'],
            ]);

            return $this->fail('Payment could not be verified. If money was debited it will be refunded.', 422);
        }

        /*
         | The signature only proves the order/payment pair came from Razorpay.
         | It says nothing about whether the money was actually captured, or how
         | much of it. Ask Razorpay before writing anything to the books.
         |
         | A transient failure to reach them is not treated as a rejection - the
         | signature is still good and the webhook reconciles the order - but a
         | definite answer of "not captured" or a different amount is.
         */
        $captured = null;

        try {
            $entity = $this->razorpay->fetchPayment($data['razorpay_payment_id']);
            $status = $entity['status'] ?? '';
            $captured = isset($entity['amount']) ? round(((int) $entity['amount']) / 100, 2) : null;

            if (! in_array($status, ['captured', 'authorized'], true)) {
                $order->update([
                    'status' => 'FAILED',
                    'failure_reason' => 'Gateway reports the payment as '.($status ?: 'unknown'),
                    'gateway_payment_id' => $data['razorpay_payment_id'],
                ]);

                return $this->fail('The payment was not completed. Nothing has been charged.', 422);
            }

            if ($captured !== null && abs($captured - (float) $order->amount) > 0.01) {
                Log::warning('Razorpay amount mismatch', [
                    'order' => $order->order_no,
                    'expected' => (float) $order->amount,
                    'captured' => $captured,
                ]);

                $order->update([
                    'status' => 'FAILED',
                    'failure_reason' => 'Captured amount did not match the order',
                    'gateway_payment_id' => $data['razorpay_payment_id'],
                ]);

                return $this->fail('The amount charged did not match this order. Please contact support.', 422);
            }
        } catch (RuntimeException $e) {
            Log::warning('Could not confirm the Razorpay payment, booking on the signature: '.$e->getMessage());
        }

        $payment = $this->bookPayment(
            $order,
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
            $captured,
        );

        return $this->ok(
            $this->orderPayload($order->fresh()) + ['payment' => $this->paymentRow($payment)],
            'Payment successful'
        );
    }

    /**
     * POST /api/v1/payment/upi/intent
     * Body: amount, customer_scheme_id
     *
     * Builds a `upi://pay` link straight to the shop's own VPA - no Razorpay,
     * no fee. The app opens Google Pay (or whichever UPI app) with it.
     *
     * Nothing here can prove the money arrives, so the order is only CREATED;
     * it becomes SUBMITTED when the customer comes back and confirms, and the
     * payment behind it stays PENDING until the office matches the UTR against
     * the bank statement.
     */
    public function upiIntent(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'customer_scheme_id' => ['required', 'integer', 'exists:customer_schemes,id'],
        ]);

        if (! $this->upi->enabled()) {
            return $this->fail($this->upi->disabledReason() ?? 'Direct UPI payment is not available.', 422);
        }

        $customer = $request->user();
        $scheme = $this->payableScheme($customer, (int) $data['customer_scheme_id'], (float) $data['amount']);

        if (is_string($scheme)) {
            return $this->fail($scheme, 422);
        }

        // Same split the books will use, so what the UPI app asks for is what
        // the receipt ends up saying.
        $quote = $this->gst->quote((float) $data['amount'], $scheme);

        $order = PaymentOrder::create([
            'order_no' => PaymentOrder::nextOrderNo(),
            'gateway' => 'upi',
            'customer_id' => $customer->id,
            'customer_scheme_id' => $scheme->id,
            'amount' => $quote['total'],
            'currency' => 'INR',
            'status' => 'CREATED',
            'meta' => [
                'base' => $quote['base'],
                'tax' => $quote['tax'],
                'gst_mode' => $quote['mode'],
                'gst_percent' => $quote['percent'],
                'vpa' => $this->upi->vpa(),
            ],
        ]);

        $note = $scheme->scheme->name.' '.$scheme->account_no;
        $links = $this->upi->links($quote['total'], $order->order_no, $note);

        return $this->ok([
            'order_no' => $order->order_no,
            'amount' => $quote['total'],
            'installment' => $quote['base'],
            'tax' => $quote['tax'],
            'gst_mode' => $quote['mode'],
            'gst_percent' => $quote['percent'],
            'vpa' => $this->upi->vpa(),
            'payee_name' => $this->upi->payeeName(),
            'note' => $note,
            'links' => $links,
        ], 'UPI link ready');
    }

    /**
     * POST /api/v1/payment/upi/confirm
     * Body: order_no, status (SUCCESS|FAILURE|PENDING), txn_ref?, screenshot?
     *
     * What the customer tells us happened. Treated as a claim, not a fact: a
     * SUCCESS records a PENDING payment for the office to confirm.
     */
    public function upiConfirm(Request $request)
    {
        $data = $request->validate([
            'order_no' => ['required', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(['SUCCESS', 'FAILURE', 'PENDING'])],
            'txn_ref' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'screenshot' => ['nullable', 'image', 'max:4096'],
        ]);

        $order = PaymentOrder::where('order_no', $data['order_no'])
            ->where('customer_id', $request->user()->id)
            ->where('gateway', 'upi')
            ->first();

        if (! $order) {
            return $this->fail('Payment order not found.', 404);
        }

        if ($order->payment_id) {
            return $this->ok(
                $this->orderPayload($order) + ['payment' => $this->paymentRow($order->payment)],
                'Already recorded'
            );
        }

        if ($data['status'] !== 'SUCCESS') {
            $order->update([
                'status' => $data['status'] === 'FAILURE' ? 'FAILED' : 'CREATED',
                'failure_reason' => $data['remarks'] ?? 'Reported by the customer',
            ]);

            return $this->ok($this->orderPayload($order->fresh()), 'Recorded');
        }

        $screenshot = $request->hasFile('screenshot')
            ? $request->file('screenshot')->store('payments', 'public')
            : null;

        $payment = DB::transaction(function () use ($order, $data, $screenshot) {
            $locked = PaymentOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_id) {
                return $locked->payment()->firstOrFail();
            }

            $meta = $locked->meta ?? [];

            $payment = $this->service->recordPayment([
                'customer_id' => $locked->customer_id,
                'customer_scheme_id' => $locked->customer_scheme_id,
                // The money the customer says they sent, split the same way.
                'received' => (float) $locked->amount,
                'amount' => (float) $locked->amount,
                'tax' => $meta['tax'] ?? null,
                'payment_mode' => 'UPI',
                'payment_type' => 'INSTALLMENT',
                'payment_date' => now()->toDateString(),
                'txn_ref' => $data['txn_ref'] ?? null,
                'screenshot' => $screenshot,
                'remarks' => trim('Direct UPI '.$locked->order_no.' '.($data['remarks'] ?? '')),
                // Never auto approved: nothing has proved the money arrived.
                'status' => 'PENDING',
            ]);

            $locked->update([
                'status' => 'SUBMITTED',
                'gateway_payment_id' => $data['txn_ref'] ?? null,
                'payment_id' => $payment->id,
            ]);

            return $payment;
        });

        return $this->ok(
            $this->orderPayload($order->fresh()) + ['payment' => $this->paymentRow($payment)],
            'Payment recorded. The shop will confirm it shortly.'
        );
    }

    /**
     * The scheme a payment may go against, or a message saying why not.
     */
    protected function payableScheme($customer, int $schemeId, float $amount): CustomerScheme|string
    {
        $scheme = CustomerScheme::with('scheme:id,name')
            ->whereKey($schemeId)
            ->where('customer_id', $customer->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $scheme) {
            return 'An active scheme is required before making a payment.';
        }

        if ($scheme->pending_installments < 1) {
            return 'This scheme has no installments remaining.';
        }

        if ($amount > (float) $scheme->installment_amount * $scheme->pending_installments) {
            return 'The amount is more than the remaining scheme balance.';
        }

        return $scheme;
    }

    /**
     * GET /api/v1/payment/order/{orderNo}
     *
     * Where an order got to. The app asks for this when it comes back from a
     * UPI app: no checkout callback fires for that hand-off, so without it the
     * customer sits looking at "Continue in Google Pay" while the webhook has
     * already booked the money.
     */
    public function orderStatus(Request $request, string $orderNo)
    {
        $order = PaymentOrder::with('payment')
            ->where('order_no', $orderNo)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (! $order) {
            return $this->fail('Payment order not found.', 404);
        }

        return $this->ok(
            $this->orderPayload($order) + [
                'paid' => $order->status === 'PAID',
                'failure_reason' => $order->failure_reason,
                'payment' => $order->payment ? $this->paymentRow($order->payment) : null,
            ]
        );
    }

    /** POST /api/v1/payment/failed — the app reports a cancelled/failed checkout. */
    public function failed(Request $request)
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        PaymentOrder::where('gateway_order_id', $data['razorpay_order_id'])
            ->where('customer_id', $request->user()->id)
            ->where('status', 'CREATED')
            ->update([
                'status' => 'FAILED',
                'failure_reason' => $data['reason'] ?? 'Cancelled by user',
            ]);

        return $this->ok(null, 'Recorded');
    }

    /**
     * POST /api/v1/payment/webhook — Razorpay server callback.
     * Books the payment even if the app was killed before it could verify.
     */
    public function webhook(Request $request)
    {
        $secret = config('mobile.payment.razorpay.webhook_secret');
        $signature = $request->header('X-Razorpay-Signature');
        $log = PaymentWebhookLog::create([
            'gateway' => 'razorpay',
            'event' => $request->input('event'),
            'signature' => $signature,
            'payload' => $request->all(),
        ]);

        if (blank($secret) || blank($signature)
            || ! $this->razorpay->verifyWebhook($request->getContent(), $signature, $secret)) {
            Log::warning('Razorpay webhook rejected: bad signature');
            $log->update(['status' => 'REJECTED', 'error' => 'Bad signature', 'processed_at' => now()]);

            return response()->json(['success' => false], 400);
        }

        $event = $request->input('event');
        $entity = $request->input('payload.payment.entity', []);

        if ($event === 'payment.captured' && ! empty($entity['order_id'])) {
            $order = PaymentOrder::where('gateway_order_id', $entity['order_id'])->first();

            if ($order && $order->status !== 'PAID') {
                // Razorpay quotes paise; book what it says was captured.
                $captured = isset($entity['amount'])
                    ? round(((int) $entity['amount']) / 100, 2)
                    : null;

                $payment = $this->bookPayment($order, $entity['id'], 'webhook', $captured);
                $log->update([
                    'payment_order_id' => $order->id,
                    'payment_id' => $payment->id,
                ]);
            }
        }

        if ($event === 'payment.failed' && ! empty($entity['order_id'])) {
            $failedOrder = PaymentOrder::where('gateway_order_id', $entity['order_id'])->first();

            if ($failedOrder) {
                $log->update(['payment_order_id' => $failedOrder->id]);
            }

            PaymentOrder::where('gateway_order_id', $entity['order_id'])
                ->where('status', 'CREATED')
                ->update([
                    'status' => 'FAILED',
                    'failure_reason' => $entity['error_description'] ?? 'Failed at gateway',
                ]);
        }

        $log->update(['status' => 'PROCESSED', 'processed_at' => now()]);

        return response()->json(['success' => true]);
    }

    /* =====================================================================
     |  helpers
     ===================================================================== */

    /**
     * Turn a verified gateway order into a Payment row.
     * The money is already collected, so it is booked APPROVED (and posted to
     * the daybook) unless PAYMENT_AUTO_APPROVE_GATEWAY is switched off.
     */
    protected function bookPayment(
        PaymentOrder $order,
        string $paymentId,
        string $signature,
        ?float $capturedAmount = null,
    ) {
        return DB::transaction(function () use ($order, $paymentId, $signature, $capturedAmount) {
            $lockedOrder = PaymentOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === 'PAID' && $lockedOrder->payment_id) {
                return $lockedOrder->payment()->firstOrFail();
            }

            if (! $lockedOrder->customer_scheme_id) {
                throw new RuntimeException('A scheme is required for this payment.');
            }

            $lockedOrder->update([
                'status' => 'PAID',
                'gateway_payment_id' => $paymentId,
                'gateway_signature' => $signature,
            ]);

            $payment = $this->service->recordPayment([
                'customer_id' => $lockedOrder->customer_id,
                'customer_scheme_id' => $lockedOrder->customer_scheme_id,
                // The money that actually changed hands. recordPayment takes the
                // GST out of this rather than adding it on top, so the bank is
                // debited with exactly what the gateway collected.
                'received' => $capturedAmount ?? (float) $lockedOrder->amount,
                'amount' => $capturedAmount ?? (float) $lockedOrder->amount,
                'payment_mode' => 'UPI',
                'payment_type' => 'INSTALLMENT',
                'payment_date' => now()->toDateString(),
                'txn_ref' => $paymentId,
                'remarks' => 'Razorpay '.$lockedOrder->order_no,
                'status' => config('mobile.payment.auto_approve_gateway') ? 'APPROVED' : 'PENDING',
            ]);

            $lockedOrder->update(['payment_id' => $payment->id]);

            return $payment;
        });
    }

    protected function orderPayload(PaymentOrder $o): array
    {
        return [
            'order_no' => $o->order_no,
            'status' => $o->status,
            'amount' => (float) $o->amount,
            'razorpay_payment_id' => $o->gateway_payment_id,
        ];
    }

    protected function paymentRow($p): array
    {
        return [
            'id' => $p->id,
            'receipt_no' => $p->receipt_no,
            'amount' => (float) $p->amount,
            'tax' => (float) $p->tax,
            'total' => (float) $p->total,
            'gram' => (float) $p->gram,
            'date' => $p->payment_date->toDateString(),
            'mode' => $p->payment_mode,
            'status' => $p->status,
            'txn_ref' => $p->txn_ref,
        ];
    }

    protected function ok($data, string $message = 'OK')
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $code = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }
}
