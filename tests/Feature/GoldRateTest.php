<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Metal;
use App\Models\MetalRate;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Setting today's gold rate from the dashboard, and telling customers.
 *
 * The rate moves every morning and the whole app quotes it. There was no
 * screen for it at all, so it could only be changed by editing the database.
 */
class GoldRateTest extends TestCase
{
    use RefreshDatabase;

    protected Metal $gold;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->gold = Metal::create([
            'code' => 'GOLD22', 'name' => 'Gold 22K',
            'purity' => '916', 'unit' => 'GRAM', 'status' => true,
        ]);
    }

    protected function admin(string $n = ''): User
    {
        return User::create([
            'name' => 'Owner', 'username' => 'owner'.$n,
            'email' => "owner{$n}@example.test",
            'password' => Hash::make('secret123'),
            'role' => 'SUPERADMIN', 'status' => true,
        ]);
    }

    protected function customer(string $phone): Customer
    {
        return Customer::create([
            'reg_no' => 'CUS'.$phone, 'name' => 'Rate Watcher',
            'phone' => $phone, 'password' => 'secret123', 'status' => true,
        ]);
    }

    public function test_the_dashboard_saves_todays_rate(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/rates', ['rates' => [$this->gold->id => 7480]])
            ->assertRedirect();

        $this->assertSame(7480.0, MetalRate::latest_rate($this->gold->id));
    }

    public function test_every_active_customer_is_told(): void
    {
        $this->customer('9000000001');
        $this->customer('9000000002');

        $this->actingAs($this->admin())
            ->post('/admin/rates', ['rates' => [$this->gold->id => 7480], 'notify' => 1]);

        $this->assertSame(2, Notification::where('type', 'GOLD_RATE')->count());
        $this->assertStringContainsString('7,480', Notification::value('message'));
    }

    public function test_it_can_be_saved_without_telling_anyone(): void
    {
        $this->customer('9000000003');

        $this->actingAs($this->admin())
            ->post('/admin/rates', ['rates' => [$this->gold->id => 7480], 'notify' => 0]);

        $this->assertSame(7480.0, MetalRate::latest_rate($this->gold->id));
        $this->assertSame(0, Notification::count());
    }

    /** Re-saving the same figure is not news; nobody should be pinged twice. */
    public function test_an_unchanged_rate_is_not_announced(): void
    {
        $this->customer('9000000004');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/rates', ['rates' => [$this->gold->id => 7480]]);
        $this->assertSame(1, Notification::count());

        $this->actingAs($admin)->post('/admin/rates', ['rates' => [$this->gold->id => 7480]]);
        $this->assertSame(1, Notification::count(), 'the same rate must not notify again');
    }

    /**
     * A morning rate corrected at noon replaces the earlier message instead of
     * stacking a second one in the customer's list - and resurfaces as unread.
     */
    public function test_a_correction_replaces_the_mornings_message(): void
    {
        $customer = $this->customer('9000000005');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/rates', ['rates' => [$this->gold->id => 7480]]);
        Notification::query()->update(['read_at' => now()]);

        $this->actingAs($admin)->post('/admin/rates', ['rates' => [$this->gold->id => 7500]]);

        $this->assertSame(1, Notification::count(), 'one rate message per customer per day');
        $note = Notification::first();
        $this->assertStringContainsString('7,500', $note->message);
        $this->assertNull($note->read_at, 'a corrected rate should be unread again');
    }

    /** One row per metal per day, or two "latest" rates fight over the date. */
    public function test_correcting_the_rate_does_not_leave_two_rows_for_today(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/rates', ['rates' => [$this->gold->id => 7480]]);
        $this->actingAs($admin)->post('/admin/rates', ['rates' => [$this->gold->id => 7500]]);

        $this->assertSame(1, MetalRate::whereDate('rate_date', today())->count());
        $this->assertSame(7500.0, MetalRate::latest_rate($this->gold->id));
    }

    public function test_the_app_is_served_the_new_rate(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/rates', ['rates' => [$this->gold->id => 7480]]);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('data.rates.0.rate', 7480);
    }

    public function test_the_customer_can_read_the_alert_in_the_app(): void
    {
        $customer = $this->customer('9000000006');

        $this->actingAs($this->admin())
            ->post('/admin/rates', ['rates' => [$this->gold->id => 7480]]);

        \Laravel\Sanctum\Sanctum::actingAs($customer, ['*']);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.type', 'GOLD_RATE')
            ->assertJsonPath('data.data.0.title', "Today's gold rate");
    }
}
