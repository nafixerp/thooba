<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Scheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');

        // status is set explicitly: Sanctum::actingAs signs in this very
        // instance rather than reloading it, so a column left to its database
        // default would read as null and customer.active would refuse it.
        $this->customer = Customer::create([
            'reg_no' => 'CUS000001',
            'name' => 'Payment Test',
            'phone' => '9000000001',
            'password' => 'secret123',
            'status' => true,
        ]);

        Sanctum::actingAs($this->customer);
    }

    public function test_gateway_order_requires_a_scheme(): void
    {
        $this->postJson('/api/v1/payment/order', ['amount' => 2000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_scheme_id');
    }

    public function test_gateway_order_rejects_an_inactive_scheme(): void
    {
        $scheme = Scheme::create([
            'name' => 'Test Gold Scheme',
            'code' => 'TEST11',
            'amount' => 2000,
            'min_amount' => 1000,
            'max_amount' => 50000,
            'total_installments' => 11,
            'duration_months' => 11,
        ]);

        $enrolment = CustomerScheme::create([
            'account_no' => 'SCH26000001',
            'customer_id' => $this->customer->id,
            'scheme_id' => $scheme->id,
            'installment_amount' => 2000,
            'total_installments' => 11,
            'paid_installments' => 0,
            'payment_interval' => 'MONTHLY',
            'start_date' => now()->toDateString(),
            'status' => 'CANCELLED',
        ]);

        $this->postJson('/api/v1/payment/order', [
            'amount' => 2000,
            'customer_scheme_id' => $enrolment->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'An active scheme is required before making a payment.');
    }

    public function test_manual_customer_payment_endpoint_is_disabled(): void
    {
        $this->postJson('/api/v1/my/payments', [
            'amount' => 2000,
            'payment_mode' => 'BANK',
        ])->assertMethodNotAllowed();
    }
}
