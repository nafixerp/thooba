<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\MasterController;
use App\Models\Bank;
use App\Models\BankStatementLine;
use App\Models\Customer;
use App\Models\Category;
use App\Models\CustomerScheme;
use App\Models\Daybook;
use App\Models\Installment;
use App\Models\OtpCode;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Scheme;
use App\Models\Setting;
use App\Models\StockValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Settings -> Clear all data.
 *
 * Two things have to hold. The shop's entries and the people they belong to
 * all go, leaving nothing pointing at a customer who no longer exists; and the
 * shop's own setup - schemes, products, banks, settings - survives.
 */
class ResetDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->admin = User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);
    }

    /** A shop with a customer, a scheme, a payment and its paper trail. */
    protected function seedBusinessData(): void
    {
        $customer = Customer::create([
            'reg_no' => 'CUS000001',
            'name' => 'Reset Test',
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
            'status' => true,
        ]);

        $cs = CustomerScheme::create([
            'customer_id' => $customer->id,
            'scheme_id' => $scheme->id,
            'account_no' => 'SCH26000001',
            'installment_amount' => 5000,
            'total_installments' => 12,
            'start_date' => '2026-07-01',
            'maturity_date' => '2027-06-01',
            'status' => 'ACTIVE',
        ]);

        Installment::create([
            'customer_scheme_id' => $cs->id,
            'installment_no' => 1,
            'due_date' => '2026-07-01',
            'amount' => 5000,
            'status' => 'PENDING',
        ]);

        $payment = Payment::create([
            'receipt_no' => 'RCT000001',
            'customer_id' => $customer->id,
            'customer_scheme_id' => $cs->id,
            'amount' => 5000,
            'tax' => 150,
            'total' => 5150,
            'payment_date' => '2026-07-01',
            'payment_mode' => 'UPI',
            'status' => 'PENDING',
        ]);

        PaymentOrder::create([
            'order_no' => 'ORD000001',
            'gateway' => 'upi',
            'customer_id' => $customer->id,
            'customer_scheme_id' => $cs->id,
            'payment_id' => $payment->id,
            'amount' => 5150,
            'currency' => 'INR',
            'status' => 'SUBMITTED',
        ]);

        BankStatementLine::create([
            'txn_date' => '2026-07-01',
            'narration' => 'UPI-ORD000001',
            'credit' => 5150,
            'debit' => 0,
            'fingerprint' => 'test-fingerprint-1',
            'payment_id' => $payment->id,
            'match_type' => 'ORDER_NO',
            'status' => 'MATCHED',
        ]);

        StockValue::create(['as_on' => '2026-07-01', 'amount' => 80000]);

        OtpCode::create([
            'phone' => '9000000001',
            'code_hash' => Hash::make('123456'),
            'purpose' => 'LOGIN',
            'expires_at' => now()->addHour(),
        ]);

        app(\App\Services\AccountingService::class)->postSimple(
            '1001', '4001', 5000,
            ['voucher_type' => 'SALES', 'voucher_date' => '2026-07-01'],
        );
    }

    protected function reset(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post('/admin/settings/reset-data', array_merge([
            'password' => 'secret123',
            'confirm_phrase' => 'RESET DATA',
        ], $overrides));
    }

    /* ===================================================================== */

    /**
     * It used to throw a 500 on SQLite: SET FOREIGN_KEY_CHECKS is MySQL only,
     * and this project supports SQLite.
     */
    public function test_it_runs_on_this_database(): void
    {
        $this->seedBusinessData();

        $this->reset()
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');
    }

    public function test_every_business_table_is_emptied(): void
    {
        $this->seedBusinessData();

        $this->assertGreaterThan(0, Customer::count());
        $this->assertGreaterThan(0, Daybook::count());

        $this->reset();

        foreach (MasterController::CLEARED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // accountm / accountg / accountgbs are wiped then re-seeded, so
            // what matters is that the old rows are gone, not that it is empty.
            if (in_array($table, ['accountm', 'accountg', 'accountgbs'], true)) {
                continue;
            }

            $this->assertSame(
                0,
                DB::table($table)->count(),
                "$table still has rows after a reset",
            );
        }
    }

    /**
     * The bug that actually bit: payment_orders, bank_statement_lines,
     * otp_codes and stock_values were not on the list, so they survived a
     * reset holding rows that pointed at customers and payments which had just
     * been deleted.
     */
    public function test_nothing_is_left_pointing_at_deleted_records(): void
    {
        $this->seedBusinessData();
        $this->reset();

        $this->assertSame(0, PaymentOrder::count(), 'gateway orders outlived their customers');
        $this->assertSame(0, BankStatementLine::count(), 'statement lines outlived their payments');
        $this->assertSame(0, OtpCode::count(), 'login codes outlived the numbers they belong to');
        $this->assertSame(0, StockValue::count(), 'closing stock outlived the books');
    }

    public function test_the_chart_of_accounts_comes_back(): void
    {
        $this->seedBusinessData();
        $this->reset();

        // Wiped and re-seeded: the control ledgers auto posting needs exist,
        // and carry no balance.
        $this->assertGreaterThan(0, \App\Models\Accountm::count());
        $this->assertNotNull(\App\Models\Accountm::find(config('scheme.accounts.cash')));
        $this->assertSame(0, Daybook::count());
    }

    public function test_the_shops_own_setup_survives(): void
    {
        $this->seedBusinessData();

        Category::create(['name' => 'Bangles', 'sortorder' => 1, 'status' => true]);
        Bank::create([
            'bank_name' => 'Federal Bank',
            'beneficiary_name' => 'Nafix Gold',
            'ifsc' => 'FDRL0001234',
            'account_no' => '9999999999999',
            'upi_id' => 'nafixgold@fbl',
            'accode' => '1002',
            'status' => true,
        ]);

        $this->reset();

        $this->assertTrue(
            Scheme::where('code', 'GP12')->exists(),
            'the scheme master should not be wiped',
        );
        $this->assertTrue(Bank::where('account_no', '9999999999999')->exists());
        $this->assertTrue(Category::where('name', 'Bangles')->exists(), 'the catalogue should survive');
    }

    /**
     * The shop's own name, prefixes and GST percent are settings. A data reset
     * clears entries, so it has no business handing the shop back somebody
     * else's name.
     */
    public function test_the_shops_settings_are_not_overwritten(): void
    {
        Setting::put('company_name', 'Nafix Gold', 'company');
        Setting::put('receipt_code_prefix', 'NG', 'company');
        Setting::put('gst_percent', '1.5', 'company');

        $this->reset();

        $this->assertSame('Nafix Gold', Setting::get('company_name'));
        $this->assertSame('NG', Setting::get('receipt_code_prefix'));
        $this->assertSame('1.5', Setting::get('gst_percent'));
    }

    public function test_the_signed_in_super_admin_is_kept_and_everyone_else_goes(): void
    {
        User::create([
            'name' => 'Staff',
            'username' => 'staff',
            'email' => 'staff@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'ACCOUNTANT',
            'status' => true,
        ]);

        $this->reset();

        $this->assertSame(1, User::count());
        $this->assertSame($this->admin->id, User::first()->id);
        $this->assertSame('SUPERADMIN', User::first()->role);
    }

    /* ---------------- the guards in front of it ---------------- */

    public function test_the_wrong_phrase_clears_nothing(): void
    {
        $this->seedBusinessData();

        $this->reset(['confirm_phrase' => 'reset data'])->assertSessionHasErrors('reset');

        $this->assertGreaterThan(0, Customer::count());
    }

    public function test_the_wrong_password_clears_nothing(): void
    {
        $this->seedBusinessData();

        $this->reset(['password' => 'not-my-password'])->assertForbidden();

        $this->assertGreaterThan(0, Customer::count());
    }

    public function test_a_non_super_admin_cannot_do_it(): void
    {
        $this->seedBusinessData();

        $accountant = User::create([
            'name' => 'Accounts',
            'username' => 'accounts',
            'email' => 'accounts@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'ACCOUNTANT',
            'status' => true,
        ]);

        $this->actingAs($accountant)->post('/admin/settings/reset-data', [
            'password' => 'secret123',
            'confirm_phrase' => 'RESET DATA',
        ])->assertForbidden();

        $this->assertGreaterThan(0, Customer::count());
    }

    /**
     * The guard that keeps this honest. A table added later belongs on one
     * list or the other; forgetting it is how rows survive a reset pointing
     * at records that are gone.
     */
    public function test_every_table_is_accounted_for(): void
    {
        // Laravel's own plumbing, which a data reset has no business touching.
        $framework = [
            'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches',
            'failed_jobs', 'password_reset_tokens',
        ];

        $known = array_merge(
            MasterController::CLEARED_TABLES,
            MasterController::KEPT_TABLES,
            $framework,
        );

        $unaccounted = array_values(array_diff($this->tableNames(), $known));

        $this->assertSame(
            [],
            $unaccounted,
            "These tables are on neither MasterController::CLEARED_TABLES nor KEPT_TABLES, "
            ."so a data reset silently leaves them alone: ".implode(', ', $unaccounted),
        );
    }

    public function test_the_two_lists_do_not_overlap(): void
    {
        $this->assertSame(
            [],
            array_values(array_intersect(
                MasterController::CLEARED_TABLES,
                MasterController::KEPT_TABLES,
            )),
        );
    }

    /**
     * This connection's tables only.
     *
     * On MySQL, Schema::getTables() reports every table on the server - on a
     * shared XAMPP that is a thousand tables belonging to other projects - so
     * the rows have to be filtered down to this database first.
     *
     * @return array<int, string>
     */
    protected function tableNames(): array
    {
        $database = DB::connection()->getDatabaseName();

        return collect(Schema::getTables())
            ->filter(fn ($t) => ! isset($t['schema']) || $t['schema'] === $database)
            ->pluck('name')
            ->reject(fn ($n) => str_starts_with($n, 'sqlite_'))
            ->values()
            ->all();
    }
}
