<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Notification;
use App\Models\PointTransaction;
use App\Models\PrivilegeTier;
use App\Models\Scheme;
use App\Models\Setting;
use App\Models\User;
use App\Services\DueNotificationService;
use App\Services\PrivilegeService;
use App\Services\SchemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationsEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->admin = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.test',
            'password' => 'secret123', 'role' => 'SUPERADMIN', 'status' => true,
        ]);
    }

    public function test_admin_can_reset_an_agent_login_password(): void
    {
        $agent = Agent::create([
            'agent_code' => 'AGT00001', 'name' => 'Collector', 'phone' => '9876543210',
            'password' => 'oldpass', 'join_date' => now(), 'status' => true,
        ]);

        $this->actingAs($this->admin)->put('/admin/agents/'.$agent->id, [
            'name' => $agent->name,
            'phone' => $agent->phone,
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
            'status' => '1',
        ])->assertRedirect('/admin/agents');

        $agent->refresh();
        $this->assertTrue(Hash::check('newpass123', $agent->password));
        $this->assertFalse($agent->must_change_password);
    }

    public function test_due_reminders_are_created_once_and_reach_the_customer_api(): void
    {
        [$customer, , $installment] = $this->enrolment();

        $service = app(DueNotificationService::class);
        $this->assertSame(1, $service->generate(3));
        $this->assertSame(0, $service->generate(3));
        $this->assertSame(1, Notification::count());

        Sanctum::actingAs($customer, ['customer']);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.data.installment_id', $installment->id)
            ->assertJsonPath('data.data.0.type', 'REMINDER');
    }

    public function test_approved_sales_award_points_at_the_configured_privilege_tier(): void
    {
        [$customer, $customerScheme] = $this->enrolment();

        $payment = app(SchemeService::class)->recordPayment([
            'customer_id' => $customer->id,
            'customer_scheme_id' => $customerScheme->id,
            'amount' => 1000,
            'payment_mode' => 'CASH',
            'status' => 'APPROVED',
        ]);

        $reward = PointTransaction::where('source', 'PAYMENT')->where('source_id', $payment->id)->firstOrFail();
        $this->assertSame(10.0, (float) $reward->points);
        $this->assertSame('Bronze', app(PrivilegeService::class)->summary($customer)['tier']);
    }

    public function test_privilege_tier_can_calculate_rewards_from_gold_grams(): void
    {
        PrivilegeTier::query()->update(['status' => false]);
        PrivilegeTier::create([
            'name' => 'Gram Gold',
            'basis' => 'GRAM',
            'min_gram' => 0,
            'points_per_gram' => 25,
            'card_color' => '#D69E2E',
            'sortorder' => 1,
            'status' => true,
        ]);
        [$customer, $customerScheme] = $this->enrolment();

        $payment = app(SchemeService::class)->recordPayment([
            'customer_id' => $customer->id,
            'customer_scheme_id' => $customerScheme->id,
            'amount' => 1000,
            'rate' => 5000,
            'payment_mode' => 'CASH',
            'status' => 'APPROVED',
        ]);

        $reward = PointTransaction::where('source', 'PAYMENT')->where('source_id', $payment->id)->firstOrFail();
        $this->assertSame(0.2, (float) $payment->gram);
        $this->assertSame(5.0, (float) $reward->points);
        $this->assertSame('GRAM', app(PrivilegeService::class)->summary($customer)['basis']);
    }

    public function test_dashboard_shows_today_joiners_enrolments_and_dues(): void
    {
        $this->enrolment();

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee("Today's Joiners")
            ->assertSee("Today's Enrolments")
            ->assertSee('Payments Due Today');
    }

    public function test_admin_can_send_a_jewellery_wish_to_a_customer_app(): void
    {
        $customer = Customer::create([
            'reg_no' => 'CUS000010', 'name' => 'Wish Customer', 'phone' => '9000000010',
            'password' => 'secret123', 'join_date' => now(), 'status' => true,
        ]);
        Setting::put('company_name', 'THOOBA', 'company');

        $this->actingAs($this->admin)->post('/admin/wishes', [
            'customer_id' => $customer->id,
            'occasion' => 'Birthday Wishes',
            'message' => 'Wishing you a wonderful birthday.',
        ])->assertRedirect();

        $wish = Notification::firstOrFail();
        $this->assertSame('wish', $wish->data['kind']);
        $this->assertSame('THOOBA', $wish->data['sender_name']);

        Sanctum::actingAs($customer, ['customer']);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Birthday Wishes')
            ->assertJsonPath('data.data.0.data.sender_name', 'THOOBA');
    }

    protected function enrolment(): array
    {
        $customer = Customer::create([
            'reg_no' => 'CUS000001', 'name' => 'Test Customer', 'phone' => '9000000001',
            'password' => 'secret123', 'join_date' => now(), 'status' => true,
        ]);
        $scheme = Scheme::create([
            'name' => 'Monthly Gold', 'code' => 'MG12', 'amount' => 1000,
            'total_installments' => 12, 'duration_months' => 12,
            'payment_interval' => 'MONTHLY', 'status' => true,
        ]);
        $customerScheme = app(SchemeService::class)->enroll($customer, $scheme, [
            'start_date' => now()->toDateString(),
        ]);
        $installment = Installment::where('customer_scheme_id', $customerScheme->id)->firstOrFail();

        return [$customer, $customerScheme, $installment];
    }
}
