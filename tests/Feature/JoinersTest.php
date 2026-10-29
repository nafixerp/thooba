<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Scheme;
use App\Services\SchemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Where a join came from, and the number on the paper book.
 *
 * A customer signed up at the counter, one an agent took on their round and
 * one who downloaded the app used to be indistinguishable once saved, so the
 * shop had no way to see what the app was bringing in.
 */
class JoinersTest extends TestCase
{
    use RefreshDatabase;

    protected Scheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->scheme = Scheme::create([
            'name' => 'Gold Plus 12',
            'code' => 'GP12',
            'amount' => 5000,
            'total_installments' => 12,
            'duration_months' => 12,
            'payment_interval' => 'MONTHLY',
            'gst_percent' => 3,
            'status' => true,
        ]);
    }

    protected function customer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'reg_no' => 'CUS'.str_pad((string) (Customer::count() + 1), 6, '0', STR_PAD_LEFT),
            'name' => 'Book Test',
            'phone' => '90000100'.str_pad((string) (Customer::count() + 10), 2, '0', STR_PAD_LEFT),
            'password' => 'secret123',
            'status' => true,
        ], $attrs));
    }

    /* ===================================================================== */
    /*  Book number                                                          */
    /* ===================================================================== */

    public function test_the_first_enrolment_gets_book_one(): void
    {
        $cs = app(SchemeService::class)->enroll($this->customer(), $this->scheme);

        $this->assertSame('1', $cs->book_no);
    }

    public function test_the_next_book_follows_the_highest_in_use(): void
    {
        app(SchemeService::class)->enroll($this->customer(), $this->scheme, ['book_no' => '250']);

        $this->assertSame('251', CustomerScheme::nextBookNo());

        $next = app(SchemeService::class)->enroll($this->customer(), $this->scheme);
        $this->assertSame('251', $next->book_no);
    }

    /**
     * The reason the proposal reads the highest number rather than counting
     * rows.
     *
     * Books 1, 2 and 3 are out. Book 1 is closed and its row removed. Counting
     * rows now gives 2 remaining, so the next proposal would be 3 - a number
     * the customer holding book 3 is still using. Reading the maximum gives 4,
     * which is free.
     */
    public function test_it_never_proposes_a_book_somebody_is_still_holding(): void
    {
        $service = app(SchemeService::class);
        $first = $service->enroll($this->customer(), $this->scheme, ['book_no' => '1']);
        $service->enroll($this->customer(), $this->scheme, ['book_no' => '2']);
        $service->enroll($this->customer(), $this->scheme, ['book_no' => '3']);

        $first->delete();

        $this->assertSame(2, CustomerScheme::whereNotNull('book_no')->count());
        $this->assertSame(
            '4',
            CustomerScheme::nextBookNo(),
            'counting the 2 surviving rows would have proposed 3, which is still in use',
        );
    }

    /** A shop with its own series - "A-142" - is left alone, not guessed at. */
    public function test_a_non_numeric_book_series_is_ignored_when_proposing(): void
    {
        app(SchemeService::class)->enroll($this->customer(), $this->scheme, ['book_no' => 'A-142']);

        $this->assertSame('1', CustomerScheme::nextBookNo());
    }

    public function test_a_typed_book_number_wins_over_the_proposal(): void
    {
        $cs = app(SchemeService::class)->enroll($this->customer(), $this->scheme, ['book_no' => '  77 ']);

        $this->assertSame('77', $cs->book_no, 'it should be trimmed and kept as typed');
    }

    /* ===================================================================== */
    /*  Source                                                               */
    /* ===================================================================== */

    public function test_a_counter_enrolment_is_marked_admin(): void
    {
        $cs = app(SchemeService::class)->enroll($this->customer(), $this->scheme);

        $this->assertSame('ADMIN', $cs->source);
    }

    public function test_a_customer_who_signs_up_in_the_app_is_marked_app(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'App Signup',
            'phone' => '9012340001',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertOk();

        $this->assertSame('APP', Customer::where('phone', '9012340001')->value('source'));
    }

    public function test_a_scheme_taken_in_the_app_is_marked_app(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer, ['*']);

        $this->postJson('/api/v1/my/schemes/join', [
            'scheme_id' => $this->scheme->id,
        ])->assertOk();

        $this->assertSame('APP', CustomerScheme::where('customer_id', $customer->id)->value('source'));
    }

    public function test_an_agent_signing_someone_up_is_marked_agent(): void
    {
        $agent = Agent::create([
            'agent_code' => 'AGT00001',
            'name' => 'Round Agent',
            'phone' => '9847011111',
            'password' => 'secret123',
            'status' => true,
        ]);

        Sanctum::actingAs($agent, ['agent'], 'agent');

        $this->postJson('/api/v1/agent/customers', [
            'name' => 'Round Customer',
            'phone' => '9012340002',
        ])->assertOk();

        $this->assertSame('AGENT', Customer::where('phone', '9012340002')->value('source'));
    }

    public function test_an_agent_can_register_nominee_and_start_the_first_scheme(): void
    {
        $agent = Agent::create([
            'agent_code' => 'AGT00002',
            'name' => 'Scheme Agent',
            'phone' => '9847011112',
            'password' => 'secret123',
            'status' => true,
        ]);

        Sanctum::actingAs($agent, ['agent'], 'agent');

        $this->postJson('/api/v1/agent/customers', [
            'name' => 'Nominee Customer',
            'phone' => '9012340003',
            'nominee_name' => 'Family Nominee',
            'nominee_relation' => 'Spouse',
            'nominee_phone' => '9012340099',
            'scheme_id' => $this->scheme->id,
            'book_no' => 'BOOK-501',
        ])->assertOk()
            ->assertJsonPath('data.scheme.book_no', 'BOOK-501')
            ->assertJsonPath('data.scheme.scheme_id', $this->scheme->id);

        $customer = Customer::where('phone', '9012340003')->firstOrFail();
        $this->assertSame('Family Nominee', $customer->nominee_name);
        $this->assertSame('Spouse', $customer->nominee_relation);
        $this->assertSame('9012340099', $customer->nominee_phone);

        $this->assertDatabaseHas('customer_schemes', [
            'customer_id' => $customer->id,
            'scheme_id' => $this->scheme->id,
            'agent_id' => $agent->id,
            'book_no' => 'BOOK-501',
            'source' => 'AGENT',
            'status' => 'ACTIVE',
        ]);
    }

    /* ===================================================================== */
    /*  OTP is only offered when it can actually arrive                      */
    /* ===================================================================== */

    /**
     * Both SMS drivers fall back to writing the message into the log when
     * their credentials are incomplete, and the API still answers "OTP sent".
     * So the app has to be told not to offer OTP at all, or customers sit
     * waiting for a code that was never going anywhere.
     */
    public function test_otp_is_not_offered_when_no_sms_gateway_can_deliver(): void
    {
        config(['mobile.sms.driver' => 'twilio', 'mobile.sms.twilio' => [
            'sid' => null, 'token' => null, 'from' => null,
        ]]);

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.features.otp_login', false);
    }

    public function test_otp_is_offered_once_the_gateway_is_real(): void
    {
        config(['mobile.sms.driver' => 'twilio', 'mobile.sms.twilio' => [
            'sid' => 'AC000', 'token' => 'tok', 'from' => '+15550001111',
        ]]);

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.features.otp_login', true);
    }

    /** The log driver delivers to nobody, however convenient it is locally. */
    public function test_the_log_driver_does_not_count_as_a_gateway(): void
    {
        config(['mobile.sms.driver' => 'log']);

        $this->assertFalse(app(\App\Services\SmsService::class)->configured());
    }

    /** A shop with working SMS can still switch OTP off by hand. */
    public function test_a_shop_can_turn_otp_off_in_settings(): void
    {
        config(['mobile.sms.driver' => 'msg91', 'mobile.sms.msg91.auth_key' => 'key']);
        \App\Models\Setting::put('otp_login', '0');

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.features.otp_login', false);
    }

    /* ===================================================================== */
    /*  The report                                                           */
    /* ===================================================================== */

    public function test_the_joiners_report_separates_the_three_doors(): void
    {
        $service = app(SchemeService::class);
        $service->enroll($this->customer(), $this->scheme);
        $service->enroll($this->customer(), $this->scheme, ['source' => 'APP']);
        $service->enroll($this->customer(), $this->scheme, ['source' => 'AGENT']);

        $admin = \App\Models\User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports/joiners')
            ->assertOk()
            ->assertSee('Mobile app')
            ->assertSee('Agent')
            ->assertSee('Counter');
    }

    public function test_the_report_can_be_narrowed_to_app_joins(): void
    {
        $service = app(SchemeService::class);
        $counter = $this->customer(['name' => 'Walked In']);
        $service->enroll($counter, $this->scheme);

        $inApp = $this->customer(['name' => 'Downloaded It', 'source' => 'APP']);
        $service->enroll($inApp, $this->scheme, ['source' => 'APP']);

        $admin = \App\Models\User::create([
            'name' => 'Owner',
            'username' => 'owner2',
            'email' => 'owner2@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports/joiners?source=APP')
            ->assertOk()
            ->assertSee('Downloaded It')
            ->assertDontSee('Walked In');
    }

    /* ===================================================================== */
    /*  Creating a customer and starting their scheme in one go              */
    /* ===================================================================== */

    protected function admin(string $suffix = ''): \App\Models\User
    {
        return \App\Models\User::create([
            'name' => 'Owner',
            'username' => 'owner'.$suffix,
            'email' => "owner{$suffix}@example.test",
            'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);
    }

    /**
     * The counter used to need two screens: save the customer, then find them
     * again and enrol them. The second trip is where book numbers got skipped.
     */
    public function test_a_customer_and_their_first_scheme_are_created_together(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/customers', [
                'name' => 'Counter Walk In',
                'phone' => '9012349901',
                'status' => 1,
                'scheme_id' => $this->scheme->id,
                'book_no' => '207',
                'installment_amount' => 5000,
                'scheme_start_date' => '2026-09-28',
            ])
            ->assertRedirect();

        $customer = Customer::where('phone', '9012349901')->first();
        $this->assertNotNull($customer);

        $cs = CustomerScheme::where('customer_id', $customer->id)->first();
        $this->assertNotNull($cs, 'the scheme should have been started in the same submit');
        $this->assertSame('207', $cs->book_no);
        $this->assertSame(12, $cs->installments()->count(), 'the schedule should be built too');
    }

    public function test_leaving_the_scheme_blank_just_creates_the_customer(): void
    {
        $this->actingAs($this->admin('2'))
            ->post('/admin/customers', [
                'name' => 'No Scheme Yet',
                'phone' => '9012349902',
                'status' => 1,
            ])
            ->assertRedirect();

        $customer = Customer::where('phone', '9012349902')->first();
        $this->assertNotNull($customer);
        $this->assertSame(0, CustomerScheme::where('customer_id', $customer->id)->count());
    }

    /** Editing must never quietly hand an existing customer a second scheme. */
    public function test_editing_a_customer_does_not_start_another_scheme(): void
    {
        $customer = $this->customer();
        app(SchemeService::class)->enroll($customer, $this->scheme, ['book_no' => '5']);

        $this->actingAs($this->admin('3'))
            ->put("/admin/customers/{$customer->id}", [
                'name' => 'Renamed',
                'phone' => $customer->phone,
                'status' => 1,
                'scheme_id' => $this->scheme->id,
                'book_no' => '999',
            ])
            ->assertRedirect();

        $this->assertSame(
            1,
            CustomerScheme::where('customer_id', $customer->id)->count(),
            'an edit must not enrol them again',
        );
    }

    /** A book number is what a customer quotes on the phone. */
    public function test_a_customer_can_be_found_by_their_book_number(): void
    {
        $customer = $this->customer(['name' => 'Passbook Holder']);
        app(SchemeService::class)->enroll($customer, $this->scheme, ['book_no' => '312']);

        $this->actingAs($this->admin('4'))
            ->get('/admin/customers?q=312')
            ->assertOk()
            ->assertSee('Passbook Holder');
    }

    public function test_the_scheme_report_can_be_filtered_by_book_number(): void
    {
        $wanted = $this->customer(['name' => 'Has Book 400']);
        $other = $this->customer(['name' => 'Has Book 401']);
        app(SchemeService::class)->enroll($wanted, $this->scheme, ['book_no' => '400']);
        app(SchemeService::class)->enroll($other, $this->scheme, ['book_no' => '401']);

        $this->actingAs($this->admin('5'))
            ->get('/admin/reports/schemes?book_no=400')
            ->assertOk()
            ->assertSee('Has Book 400')
            ->assertDontSee('Has Book 401');
    }
}
