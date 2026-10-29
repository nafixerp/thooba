<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OtpCode;
use App\Services\OtpService;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * OTP login, and whether online payment reports itself honestly.
 */
class OtpAndGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');

        $this->customer = Customer::create([
            'reg_no' => 'CUS000001',
            'name' => 'Otp Test',
            'phone' => '9000000001',
            'password' => 'secret123',
            'status' => true,
        ]);
    }

    /** Issue a code and hand back the plain text. */
    protected function issue(string $purpose = 'LOGIN'): string
    {
        config()->set('mobile.otp.expose_in_response', true);
        config()->set('mobile.otp.resend_after', 0);

        return app(OtpService::class)->issue($this->customer->phone, $purpose)['code'];
    }

    /* ===================================================================== */
    /*  OTP                                                                  */
    /* ===================================================================== */

    /**
     * The bug this guards against: `$table->timestamp('expires_at')` gets
     * `ON UPDATE CURRENT_TIMESTAMP` from MySQL, so incrementing the attempt
     * counter rewrote the expiry to now() and killed the code. One mistyped
     * digit and the customer was told their fresh OTP had expired.
     */
    public function test_a_wrong_attempt_does_not_kill_the_code(): void
    {
        $code = $this->issue();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => '000000',
        ])->assertStatus(422)->assertJsonPath('message', 'The OTP you entered is incorrect.');

        // The real code still works.
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $code,
        ])->assertOk()->assertJsonPath('data.phone', $this->customer->phone);
    }

    public function test_the_code_survives_up_to_the_attempt_limit(): void
    {
        config()->set('mobile.otp.max_attempts', 5);
        $code = $this->issue();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => $this->customer->phone,
                'otp' => '00000'.$i,
            ])->assertStatus(422);
        }

        $otp = OtpCode::where('phone', $this->customer->phone)->latest('id')->first();
        $this->assertSame(4, (int) $otp->attempts);
        $this->assertTrue($otp->expires_at->isFuture(), 'the expiry was rewritten by an update');

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $code,
        ])->assertOk();
    }

    public function test_too_many_wrong_attempts_locks_the_code(): void
    {
        config()->set('mobile.otp.max_attempts', 5);
        $code = $this->issue();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => $this->customer->phone,
                'otp' => '11111'.$i,
            ])->assertStatus(422);
        }

        // Even the right code is refused once the cap is hit.
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Too many wrong attempts. Please request a new OTP.');
    }

    public function test_a_code_is_single_use(): void
    {
        $code = $this->issue();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $code,
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $code,
        ])->assertStatus(422);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->issue();

        OtpCode::where('phone', $this->customer->phone)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $code,
        ])->assertStatus(422);
    }

    public function test_issuing_a_new_code_retires_the_old_one(): void
    {
        $first = $this->issue();
        $second = $this->issue();

        $this->assertNotSame($first, $second);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $first,
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $second,
        ])->assertOk();
    }

    public function test_login_otp_is_refused_for_an_unknown_number(): void
    {
        $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '9111100000',
            'purpose' => 'LOGIN',
        ])->assertStatus(404)->assertJsonPath('registered', false);
    }

    public function test_a_blocked_customer_cannot_log_in_with_an_otp(): void
    {
        $code = $this->issue();
        $this->customer->update(['status' => false]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $this->customer->phone,
            'otp' => $code,
        ])->assertForbidden();
    }

    /**
     * The logic tests above run on SQLite, which has no such behaviour, so
     * they would pass even with the broken column. This is the guard that
     * actually catches the cause, and it only means anything on MySQL.
     */
    public function test_no_column_rewrites_itself_on_update(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('ON UPDATE CURRENT_TIMESTAMP is a MySQL behaviour.');
        }

        $rows = \Illuminate\Support\Facades\DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND EXTRA LIKE '%on update%'
               AND COLUMN_NAME <> 'updated_at'"
        );

        $offenders = array_map(
            fn ($r) => $r->TABLE_NAME.'.'.$r->COLUMN_NAME,
            $rows,
        );

        $this->assertSame(
            [],
            $offenders,
            "MySQL gives the first non-nullable TIMESTAMP column in a table
".
            "ON UPDATE CURRENT_TIMESTAMP, so any update rewrites it. Declare
".
            "these as dateTime() instead: ".implode(', ', $offenders),
        );
    }

    /* ===================================================================== */
    /*  GATEWAY STATUS                                                       */
    /* ===================================================================== */

    /**
     * @return array{enabled: bool, reason: ?string, mode: string, key_type: ?string}
     */
    protected function gatewayStatus(?string $keyId, ?string $secret, bool $live): array
    {
        config()->set('mobile.payment.razorpay.key_id', $keyId);
        config()->set('mobile.payment.razorpay.key_secret', $secret);
        config()->set('mobile.payment.razorpay.live_mode', $live);

        return app(RazorpayService::class)->status();
    }

    public function test_missing_keys_say_so(): void
    {
        $s = $this->gatewayStatus('', '', true);

        $this->assertFalse($s['enabled']);
        $this->assertStringContainsString('keys are not set', $s['reason']);
    }

    /**
     * A shop testing with rzp_test_ keys used to get a silently disabled
     * gateway and an app that said "temporarily unavailable", with nothing
     * anywhere pointing at RAZORPAY_LIVE_MODE.
     */
    public function test_test_keys_work_in_test_mode(): void
    {
        $s = $this->gatewayStatus('rzp_test_abc', 'secret', false);

        $this->assertTrue($s['enabled']);
        $this->assertSame('test', $s['key_type']);
    }

    public function test_test_keys_are_refused_in_live_mode_with_a_reason(): void
    {
        $s = $this->gatewayStatus('rzp_test_abc', 'secret', true);

        $this->assertFalse($s['enabled']);
        $this->assertStringContainsString('RAZORPAY_LIVE_MODE', $s['reason']);
    }

    public function test_live_keys_work(): void
    {
        $this->assertTrue($this->gatewayStatus('rzp_live_abc', 'secret', true)['enabled']);
    }

    public function test_a_malformed_key_is_caught(): void
    {
        $s = $this->gatewayStatus('not-a-key', 'secret', false);

        $this->assertFalse($s['enabled']);
        $this->assertStringContainsString('does not look like a Razorpay key', $s['reason']);
    }

    public function test_the_app_is_told_why_the_gateway_is_off(): void
    {
        config()->set('mobile.payment.razorpay.key_id', '');
        config()->set('mobile.payment.razorpay.key_secret', '');

        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/payment/methods')
            ->assertOk()
            ->assertJsonPath('data.gateway_enabled', false)
            ->assertJsonPath('data.razorpay_key', null)
            ->assertJsonStructure(['data' => ['disabled_reason', 'upi_only', 'gst_mode', 'gst_percent']]);
    }

    /**
     * The UPI hand-off never fires a checkout callback, so the app asks the
     * server where the order got to when it comes back to the foreground.
     */
    public function test_an_order_reports_where_it_got_to(): void
    {
        $order = \App\Models\PaymentOrder::create([
            'order_no' => 'ORD000001',
            'gateway' => 'razorpay',
            'customer_id' => $this->customer->id,
            'amount' => 5150,
            'currency' => 'INR',
            'status' => 'CREATED',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/payment/order/'.$order->order_no)
            ->assertOk()
            ->assertJsonPath('data.paid', false)
            ->assertJsonPath('data.status', 'CREATED');

        $order->update(['status' => 'PAID']);

        $this->getJson('/api/v1/payment/order/'.$order->order_no)
            ->assertOk()
            ->assertJsonPath('data.paid', true);
    }

    public function test_an_order_belonging_to_someone_else_is_not_visible(): void
    {
        $other = Customer::create([
            'reg_no' => 'CUS000002',
            'name' => 'Someone Else',
            'phone' => '9000000002',
            'status' => true,
        ]);

        \App\Models\PaymentOrder::create([
            'order_no' => 'ORD000002',
            'gateway' => 'razorpay',
            'customer_id' => $other->id,
            'amount' => 1000,
            'currency' => 'INR',
            'status' => 'PAID',
        ]);

        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/payment/order/ORD000002')->assertNotFound();
    }

    public function test_no_reason_is_sent_when_the_gateway_is_on(): void
    {
        config()->set('mobile.payment.razorpay.key_id', 'rzp_live_abc');
        config()->set('mobile.payment.razorpay.key_secret', 'secret');
        config()->set('mobile.payment.razorpay.live_mode', true);

        \Laravel\Sanctum\Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/payment/methods')
            ->assertOk()
            ->assertJsonPath('data.gateway_enabled', true)
            ->assertJsonPath('data.disabled_reason', null)
            ->assertJsonPath('data.razorpay_key', 'rzp_live_abc');
    }
}
