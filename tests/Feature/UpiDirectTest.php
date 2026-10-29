<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Scheme;
use App\Models\Setting;
use App\Services\UpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Paying straight into the shop's UPI id, with no aggregator.
 *
 * The rule that shapes all of it: nothing here can prove the money arrived, so
 * a payment lands PENDING and never touches the books until the office
 * approves it against the bank statement.
 */
class UpiDirectTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected CustomerScheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        Setting::put('upi_id', 'ishqjewellery@sbi');
        Setting::put('company_name', 'Ishq Jewellery');

        $this->customer = Customer::create([
            'reg_no' => 'CUS000001',
            'name' => 'Upi Test',
            'phone' => '9000000001',
            'password' => 'secret123',
            'status' => true,
        ]);

        $scheme = Scheme::create([
            'name' => 'Gold Plus 12',
            'code' => 'GP12',
            'amount' => 5000,
            'total_installments' => 12,
            'duration_months' => 12,
            'payment_interval' => 'MONTHLY',
            'gst_percent' => 3,
            'status' => true,
        ]);

        $this->scheme = CustomerScheme::create([
            'customer_id' => $this->customer->id,
            'scheme_id' => $scheme->id,
            'account_no' => 'SCH26000001',
            'installment_amount' => 5000,
            'total_installments' => 12,
            'start_date' => '2026-07-01',
            'maturity_date' => '2027-06-01',
            'status' => 'ACTIVE',
        ]);

        Installment::create([
            'customer_scheme_id' => $this->scheme->id,
            'installment_no' => 1,
            'due_date' => '2026-07-01',
            'amount' => 5000,
            'paid_amount' => 0,
            'status' => 'PENDING',
        ]);

        Sanctum::actingAs($this->customer);
    }

    /* ===================================================================== */
    /*  The link                                                             */
    /* ===================================================================== */

    public function test_the_intent_link_carries_the_shop_the_amount_and_our_reference(): void
    {
        $data = $this->postJson('/api/v1/payment/upi/intent', [
            'amount' => 5000,
            'customer_scheme_id' => $this->scheme->id,
        ])->assertOk()->json('data');

        // EXCLUSIVE GST by default, so the UPI app asks for installment + tax.
        $this->assertEqualsWithDelta(5150, $data['amount'], 0.01);
        $this->assertEqualsWithDelta(5000, $data['installment'], 0.01);
        $this->assertEqualsWithDelta(150, $data['tax'], 0.01);

        $link = $data['links']['upi'];

        $this->assertStringStartsWith('upi://pay?', $link);
        parse_str(parse_url($link, PHP_URL_QUERY), $q);

        $this->assertSame('ishqjewellery@sbi', $q['pa']);
        $this->assertSame('Ishq Jewellery', $q['pn']);
        $this->assertSame('INR', $q['cu']);
        // Plain two decimals - UPI rejects thousands separators.
        $this->assertSame('5150.00', $q['am']);
        // Our own order number, which is what the office matches on.
        $this->assertSame($data['order_no'], $q['tr']);

        // And the per-app shortcuts point at the same thing.
        $this->assertStringStartsWith('tez://upi/pay?', $data['links']['gpay']);
    }

    public function test_the_order_starts_unpaid(): void
    {
        $orderNo = $this->postJson('/api/v1/payment/upi/intent', [
            'amount' => 5000,
            'customer_scheme_id' => $this->scheme->id,
        ])->json('data.order_no');

        $order = PaymentOrder::where('order_no', $orderNo)->firstOrFail();

        $this->assertSame('upi', $order->gateway);
        $this->assertSame('CREATED', $order->status);
        $this->assertNull($order->payment_id);
        $this->assertSame(0, Payment::count());
    }

    public function test_an_intent_is_refused_without_an_active_scheme(): void
    {
        $this->scheme->update(['status' => 'CLOSED']);

        $this->postJson('/api/v1/payment/upi/intent', [
            'amount' => 5000,
            'customer_scheme_id' => $this->scheme->id,
        ])->assertStatus(422);
    }

    public function test_an_intent_is_refused_above_the_remaining_balance(): void
    {
        $this->postJson('/api/v1/payment/upi/intent', [
            'amount' => 999999,
            'customer_scheme_id' => $this->scheme->id,
        ])->assertStatus(422);
    }

    public function test_it_is_off_without_a_upi_id(): void
    {
        Setting::put('upi_id', '');
        config()->set('mobile.payment.upi.vpa', null);

        $this->postJson('/api/v1/payment/upi/intent', [
            'amount' => 5000,
            'customer_scheme_id' => $this->scheme->id,
        ])->assertStatus(422);

        $this->getJson('/api/v1/payment/methods')
            ->assertOk()
            ->assertJsonPath('data.upi_direct.enabled', false)
            ->assertJsonPath('data.upi_direct.disabled_reason', 'No UPI id is set. Add one under Settings, or on a bank record.');
    }

    public function test_a_malformed_upi_id_is_not_used(): void
    {
        Setting::put('upi_id', 'not a vpa');
        config()->set('mobile.payment.upi.vpa', null);

        $this->assertNull(app(UpiService::class)->vpa());
    }

    /* ===================================================================== */
    /*  The confirmation                                                     */
    /* ===================================================================== */

    protected function intent(): string
    {
        return $this->postJson('/api/v1/payment/upi/intent', [
            'amount' => 5000,
            'customer_scheme_id' => $this->scheme->id,
        ])->json('data.order_no');
    }

    /**
     * The whole safety model: a customer saying "I paid" records a PENDING
     * payment and nothing else. No voucher, no scheme credit, no wallet.
     */
    public function test_a_success_is_recorded_pending_and_never_posted(): void
    {
        $orderNo = $this->intent();

        $this->postJson('/api/v1/payment/upi/confirm', [
            'order_no' => $orderNo,
            'status' => 'SUCCESS',
            'txn_ref' => 'UTR523401998877',
        ])->assertOk()->assertJsonPath('data.payment.status', 'PENDING');

        $payment = Payment::firstOrFail();

        $this->assertSame('PENDING', $payment->status);
        $this->assertNull($payment->daybook_sno, 'a claimed payment reached the books');
        $this->assertSame('UTR523401998877', $payment->txn_ref);

        // Charged total splits the same way the gateway's would.
        $this->assertEqualsWithDelta(5000, (float) $payment->amount, 0.01);
        $this->assertEqualsWithDelta(150, (float) $payment->tax, 0.01);
        $this->assertEqualsWithDelta(5150, (float) $payment->total, 0.01);

        // Nothing has moved yet.
        $this->assertEqualsWithDelta(0, (float) $this->customer->fresh()->wallet_amount, 0.01);
        $this->assertSame('PENDING', Installment::firstOrFail()->status);

        $this->assertSame('SUBMITTED', PaymentOrder::firstOrFail()->status);
    }

    public function test_confirming_twice_does_not_double_book(): void
    {
        $orderNo = $this->intent();

        $body = [
            'order_no' => $orderNo,
            'status' => 'SUCCESS',
            'txn_ref' => 'UTR1',
        ];

        $first = $this->postJson('/api/v1/payment/upi/confirm', $body)->json('data.payment.id');
        $second = $this->postJson('/api/v1/payment/upi/confirm', $body)->json('data.payment.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Payment::count());
    }

    public function test_a_reported_failure_records_nothing(): void
    {
        $orderNo = $this->intent();

        $this->postJson('/api/v1/payment/upi/confirm', [
            'order_no' => $orderNo,
            'status' => 'FAILURE',
        ])->assertOk();

        $this->assertSame(0, Payment::count());
        $this->assertSame('FAILED', PaymentOrder::firstOrFail()->status);
    }

    public function test_someone_elses_order_cannot_be_confirmed(): void
    {
        $orderNo = $this->intent();

        $other = Customer::create([
            'reg_no' => 'CUS000002',
            'name' => 'Someone Else',
            'phone' => '9000000002',
            'status' => true,
        ]);

        Sanctum::actingAs($other);

        $this->postJson('/api/v1/payment/upi/confirm', [
            'order_no' => $orderNo,
            'status' => 'SUCCESS',
        ])->assertNotFound();

        $this->assertSame(0, Payment::count());
    }

    /** Approval is the moment it becomes real, and it still balances. */
    public function test_the_office_approving_it_posts_the_voucher(): void
    {
        $orderNo = $this->intent();

        $this->postJson('/api/v1/payment/upi/confirm', [
            'order_no' => $orderNo,
            'status' => 'SUCCESS',
            'txn_ref' => 'UTR1',
        ])->assertOk();

        $payment = Payment::firstOrFail();
        app(\App\Services\SchemeService::class)->approvePayment($payment);

        $payment->refresh();
        $this->assertSame('APPROVED', $payment->status);
        $this->assertNotNull($payment->daybook_sno);

        // Dr Cash/Bank 5150 = Cr Customer 5000 + Cr GST 150.
        $lines = \App\Models\Daybook::where('sno', $payment->daybook_sno)->get();
        $this->assertEqualsWithDelta(0, (float) $lines->sum('amount'), 0.005);
        $this->assertEqualsWithDelta(5150, (float) $lines->sum('dr_amount'), 0.01);

        $this->assertSame('PAID', Installment::firstOrFail()->status);
    }

    /* ===================================================================== */
    /*  The note and payee name UPI will accept                              */
    /* ===================================================================== */

    public function test_the_note_is_trimmed_to_what_upi_accepts(): void
    {
        $upi = app(UpiService::class);

        $links = $upi->links(100, 'ORD1', str_repeat('Gold Scheme #1 / ', 10));
        parse_str(parse_url($links['upi'], PHP_URL_QUERY), $q);

        $this->assertLessThanOrEqual(45, mb_strlen($q['tn']));
        $this->assertDoesNotMatchRegularExpression('/[^A-Za-z0-9 ]/', $q['tn']);
    }

    public function test_the_payee_name_is_cleaned(): void
    {
        Setting::put('upi_payee_name', 'Ishq Jewellery (Pvt) Ltd #1');

        $this->assertSame('Ishq Jewellery Pvt Ltd 1', app(UpiService::class)->payeeName());
    }

    public function test_the_payee_name_falls_back_to_the_shop_name(): void
    {
        Setting::put('upi_payee_name', '');

        $this->assertSame('Ishq Jewellery', app(UpiService::class)->payeeName());
    }
}
