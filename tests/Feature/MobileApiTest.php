<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Scheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The payloads the NAFO app reads, and the account checks behind them.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Scheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->customer = Customer::create([
            'reg_no' => 'CUS000001',
            'name' => 'Schedule Test',
            'phone' => '9000000001',
            'password' => 'secret123',
            'status' => true,
        ]);

        $this->scheme = Scheme::create([
            'name' => 'Gold Plus 12',
            'code' => 'GP12',
            'amount' => 5000,
            'total_installments' => 12,
            'duration_months' => 12,
            'payment_interval' => 'MONTHLY',
            'status' => true,
        ]);
    }

    /** A scheme with a 12 month schedule, the first three paid. */
    protected function enrol(): CustomerScheme
    {
        $cs = CustomerScheme::create([
            'customer_id' => $this->customer->id,
            'scheme_id' => $this->scheme->id,
            'account_no' => 'SCH26000001',
            'installment_amount' => 5000,
            'total_installments' => 12,
            'start_date' => '2026-07-01',
            'maturity_date' => '2027-06-01',
            'status' => 'ACTIVE',
            'paid_installments' => 3,
            'total_paid' => 15000,
        ]);

        for ($n = 1; $n <= 12; $n++) {
            Installment::create([
                'customer_scheme_id' => $cs->id,
                'installment_no' => $n,
                'due_date' => date('Y-m-d', strtotime("2026-07-01 +".($n - 1)." month")),
                'amount' => 5000,
                'paid_amount' => $n <= 3 ? 5000 : 0,
                'status' => $n <= 3 ? 'PAID' : 'PENDING',
            ]);
        }

        // Touching the first rows is what used to shuffle them to the end.
        Installment::where('customer_scheme_id', $cs->id)
            ->whereIn('installment_no', [1, 2, 3])
            ->update(['status' => 'PAID']);

        return $cs;
    }

    public function test_scheme_detail_carries_the_fields_the_app_shows(): void
    {
        $cs = $this->enrol();
        Sanctum::actingAs($this->customer);

        $this->getJson("/api/v1/my/schemes/{$cs->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cs->id)
            ->assertJsonPath('data.installment_amount', 5000)
            ->assertJsonPath('data.paid_installments', 3)
            ->assertJsonPath('data.total_installments', 12)
            ->assertJsonPath('data.pending_installments', 9)
            ->assertJsonPath('data.maturity_date', '2027-06-01')
            ->assertJsonPath('data.start_date', '2026-07-01');
    }

    public function test_the_installment_schedule_comes_back_in_order(): void
    {
        $cs = $this->enrol();
        Sanctum::actingAs($this->customer);

        $numbers = $this->getJson("/api/v1/my/schemes/{$cs->id}")
            ->assertOk()
            ->json('data.installments.*.no');

        $this->assertSame(range(1, 12), $numbers);
    }

    public function test_payment_history_pages_and_reports_lifetime_totals(): void
    {
        $cs = $this->enrol();

        foreach (range(1, 30) as $n) {
            Payment::create([
                'customer_id' => $this->customer->id,
                'customer_scheme_id' => $cs->id,
                'receipt_no' => 'RCT'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                'amount' => 1000,
                'total' => 1000,
                'payment_date' => '2026-08-01',
                'payment_mode' => 'CASH',
                'status' => $n <= 25 ? 'APPROVED' : 'PENDING',
            ]);
        }

        Sanctum::actingAs($this->customer);

        $first = $this->getJson('/api/v1/my/payments')->assertOk();
        $this->assertCount(25, $first->json('data.data'));
        $this->assertSame(2, $first->json('data.last_page'));
        // Lifetime figures, not a sum of the page.
        $this->assertSame(30, $first->json('data.summary.count'));
        $this->assertEqualsWithDelta(25000, $first->json('data.summary.approved'), 0.05);
        $this->assertEqualsWithDelta(5000, $first->json('data.summary.pending'), 0.05);

        // The rest of the history is reachable.
        $second = $this->getJson('/api/v1/my/payments?page=2')->assertOk();
        $this->assertCount(5, $second->json('data.data'));

        // And a client may ask for a bigger page.
        $this->getJson('/api/v1/my/payments?per_page=100')
            ->assertOk()
            ->assertJsonCount(30, 'data.data');
    }

    public function test_a_blocked_customer_cannot_use_an_old_token(): void
    {
        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/auth/me')->assertOk();

        $this->customer->update(['status' => false]);
        Sanctum::actingAs($this->customer->fresh());

        $this->getJson('/api/v1/auth/me')->assertForbidden();
        $this->getJson('/api/v1/my/schemes')->assertForbidden();
    }

    public function test_the_agent_customer_book_pages_and_is_searchable(): void
    {
        $agent = Agent::create([
            'agent_code' => 'AGT00001',
            'name' => 'Book Agent',
            'phone' => '9847000001',
            'password' => 'secret123',
            'status' => true,
        ]);

        foreach (range(1, 30) as $n) {
            Customer::create([
                'reg_no' => 'CUSB'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'name' => 'Book Customer '.$n,
                'phone' => '98000000'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'agent_id' => $agent->id,
                'status' => true,
            ]);
        }

        Sanctum::actingAs($agent, ['agent'], 'agent');

        $first = $this->getJson('/api/v1/agent/customers')->assertOk();
        $this->assertCount(25, $first->json('data.data'));
        $this->assertSame(30, $first->json('data.total'));

        // Customer 26 onwards used to be unreachable from the app.
        $this->getJson('/api/v1/agent/customers?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data.data');

        $this->getJson('/api/v1/agent/customers?per_page=100')
            ->assertOk()
            ->assertJsonCount(30, 'data.data');

        $this->getJson('/api/v1/agent/customers?search=Book Customer 17')
            ->assertOk()
            ->assertJsonPath('data.data.0.name', 'Book Customer 17');
    }

    public function test_agent_collections_report_totals_for_the_whole_filter(): void
    {
        $agent = Agent::create([
            'agent_code' => 'AGT00002',
            'name' => 'Collector',
            'phone' => '9847000002',
            'password' => 'secret123',
            'status' => true,
        ]);

        foreach (range(1, 30) as $n) {
            Payment::create([
                'customer_id' => $this->customer->id,
                'agent_id' => $agent->id,
                'receipt_no' => 'AGC'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                'amount' => 100,
                'total' => 100,
                'payment_date' => '2026-08-01',
                'payment_mode' => $n <= 25 ? 'CASH' : 'BANK',
                'status' => $n <= 20 ? 'APPROVED' : 'PENDING',
            ]);
        }

        $this->customer->update([
            'agent_id' => $agent->id,
            'join_date' => '2026-08-01',
        ]);
        $customerScheme = CustomerScheme::create([
            'customer_id' => $this->customer->id,
            'scheme_id' => $this->scheme->id,
            'agent_id' => $agent->id,
            'account_no' => 'SCH26000999',
            'installment_amount' => 5000,
            'total_installments' => 12,
            'start_date' => '2026-08-01',
            'status' => 'ACTIVE',
        ]);
        Installment::create([
            'customer_scheme_id' => $customerScheme->id,
            'installment_no' => 1,
            'due_date' => '2026-08-01',
            'amount' => 5000,
            'paid_amount' => 1000,
            'status' => 'PARTIAL',
        ]);

        Sanctum::actingAs($agent, ['agent'], 'agent');

        $all = $this->getJson('/api/v1/agent/collections')->assertOk();
        $this->assertCount(25, $all->json('data.data'));
        $this->assertSame(30, $all->json('data.summary.count'));
        $this->assertEqualsWithDelta(3000, $all->json('data.summary.amount'), 0.05);
        $this->assertEqualsWithDelta(2500, $all->json('data.summary.by_mode.CASH'), 0.05);
        $this->assertEqualsWithDelta(500, $all->json('data.summary.by_mode.BANK'), 0.05);

        $pending = $this->getJson('/api/v1/agent/collections?status=PENDING')->assertOk();
        $this->assertSame(10, $pending->json('data.summary.count'));
        $this->assertEqualsWithDelta(1000, $pending->json('data.summary.amount'), 0.05);

        $dated = $this->getJson(
            '/api/v1/agent/collections?from=2026-08-01&to=2026-08-01&mode=BANK'
        )->assertOk();
        $this->assertSame(5, $dated->json('data.summary.count'));
        $this->assertEqualsWithDelta(500, $dated->json('data.summary.amount'), 0.05);
        $this->assertSame(1, $dated->json('data.summary.joined_count'));
        $this->assertSame(1, $dated->json('data.summary.due_count'));
        $this->assertEqualsWithDelta(4000, $dated->json('data.summary.due_amount'), 0.05);

        $this->getJson('/api/v1/agent/due-list?from=2026-08-01&to=2026-08-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.balance', 4000);
    }
}
