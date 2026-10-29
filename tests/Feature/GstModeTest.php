<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Daybook;
use App\Models\Installment;
use App\Models\Scheme;
use App\Models\Setting;
use App\Services\GstService;
use App\Services\SchemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * How GST sits on a scheme collection, in each of the three modes.
 *
 * The invariant every mode has to hold, because it is what the receipt voucher
 * posts:
 *
 *      money received (Dr Cash/Bank) == scheme credit (Cr Customer) + GST
 *
 * The online gateway used to charge the installment while the books recorded
 * installment + GST, so the bank was debited with money that never arrived.
 */
class GstModeTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Scheme $scheme;

    protected SchemeService $schemes;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->schemes = app(SchemeService::class);

        $this->customer = Customer::create([
            'reg_no' => 'CUS000001',
            'name' => 'Gst Test',
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
            'gst_percent' => 3,
            'status' => true,
        ]);
    }

    protected function mode(string $mode): GstService
    {
        Setting::put('gst_mode', $mode);

        // A fresh instance, since the service reads the setting once per call.
        return app(GstService::class);
    }

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
        ]);

        Installment::create([
            'customer_scheme_id' => $cs->id,
            'installment_no' => 1,
            'due_date' => '2026-07-01',
            'amount' => 5000,
            'paid_amount' => 0,
            'status' => 'PENDING',
        ]);

        return $cs;
    }

    /* ================= the quote the customer is shown ================= */

    public function test_exclusive_adds_the_tax_on_top(): void
    {
        $q = $this->mode(GstService::EXCLUSIVE)->quote(5000, $this->scheme);

        $this->assertEqualsWithDelta(5000, $q['base'], 0.005);
        $this->assertEqualsWithDelta(150, $q['tax'], 0.005);
        $this->assertEqualsWithDelta(5150, $q['total'], 0.005);
    }

    public function test_inclusive_takes_the_tax_out_of_the_installment(): void
    {
        $q = $this->mode(GstService::INCLUSIVE)->quote(5000, $this->scheme);

        $this->assertEqualsWithDelta(4854.37, $q['base'], 0.01);
        $this->assertEqualsWithDelta(145.63, $q['tax'], 0.01);
        $this->assertEqualsWithDelta(5000, $q['total'], 0.005);
    }

    public function test_none_charges_no_tax(): void
    {
        $q = $this->mode(GstService::NONE)->quote(5000, $this->scheme);

        $this->assertEqualsWithDelta(5000, $q['base'], 0.005);
        $this->assertEqualsWithDelta(0, $q['tax'], 0.005);
        $this->assertEqualsWithDelta(5000, $q['total'], 0.005);
    }

    public function test_a_quote_and_a_split_are_the_inverse_of_each_other(): void
    {
        foreach ([GstService::EXCLUSIVE, GstService::INCLUSIVE, GstService::NONE] as $mode) {
            $gst = $this->mode($mode);

            $quote = $gst->quote(5000, $this->scheme);
            $split = $gst->split($quote['total'], $this->scheme);

            $this->assertEqualsWithDelta($quote['base'], $split['base'], 0.02, $mode);
            $this->assertEqualsWithDelta($quote['tax'], $split['tax'], 0.02, $mode);
            $this->assertEqualsWithDelta($quote['total'], $split['total'], 0.02, $mode);
        }
    }

    /* ================= what the books end up with ================= */

    /**
     * The whole point: the bank is debited with exactly the money that arrived,
     * and that money splits into scheme credit + GST.
     */
    protected function assertBooksMatchTheMoney(string $mode, float $expectedReceived): void
    {
        $gst = $this->mode($mode);
        $cs = $this->enrol();
        $quote = $gst->quote(5000, $cs);

        $this->assertEqualsWithDelta($expectedReceived, $quote['total'], 0.01, $mode);

        // Book it the way the gateway does: from the money received.
        $payment = $this->schemes->recordPayment([
            'customer_id' => $this->customer->id,
            'customer_scheme_id' => $cs->id,
            'received' => $quote['total'],
            'amount' => $quote['total'],
            'payment_mode' => 'UPI',
            'payment_date' => '2026-07-01',
            'status' => 'APPROVED',
        ]);

        $this->assertEqualsWithDelta(
            $expectedReceived,
            (float) $payment->total,
            0.01,
            "$mode: receipt total should be the money received",
        );

        $bankDr = (float) Daybook::where('sno', $payment->daybook_sno)
            ->where('drcr', 'D')
            ->sum('dr_amount');

        $this->assertEqualsWithDelta(
            $expectedReceived,
            $bankDr,
            0.01,
            "$mode: the bank was debited with money that never arrived",
        );

        $this->assertEqualsWithDelta(
            $bankDr,
            (float) $payment->amount + (float) $payment->tax,
            0.01,
            "$mode: credit + GST does not add up to what came in",
        );

        $this->assertEqualsWithDelta(
            0,
            (float) Daybook::where('sno', $payment->daybook_sno)->sum('amount'),
            0.005,
            "$mode: the voucher does not balance",
        );

        // The customer paid the installment in full, so the row has to close.
        $this->assertSame(
            'PAID',
            Installment::where('customer_scheme_id', $cs->id)->value('status'),
            "$mode: a fully paid installment did not close",
        );
    }

    public function test_exclusive_books_the_money_that_arrived(): void
    {
        $this->assertBooksMatchTheMoney(GstService::EXCLUSIVE, 5150.0);
    }

    public function test_inclusive_books_the_money_that_arrived(): void
    {
        $this->assertBooksMatchTheMoney(GstService::INCLUSIVE, 5000.0);
    }

    public function test_none_books_the_money_that_arrived(): void
    {
        $this->assertBooksMatchTheMoney(GstService::NONE, 5000.0);
    }

    public function test_a_scheme_rate_beats_the_shop_default(): void
    {
        Setting::put('gst_percent', 3);
        $gst = $this->mode(GstService::EXCLUSIVE);

        $this->scheme->update(['gst_percent' => 5]);

        $this->assertEqualsWithDelta(5, $gst->percent($this->scheme->fresh()), 0.005);
        $this->assertEqualsWithDelta(250, $gst->quote(5000, $this->scheme->fresh())['tax'], 0.01);
    }

    public function test_none_mode_ignores_the_scheme_rate(): void
    {
        $this->scheme->update(['gst_percent' => 5]);
        $gst = $this->mode(GstService::NONE);

        $this->assertEqualsWithDelta(0, $gst->percent($this->scheme->fresh()), 0.005);
        $this->assertEqualsWithDelta(0, $gst->quote(5000, $this->scheme->fresh())['tax'], 0.005);
    }

    public function test_an_unknown_mode_falls_back_to_exclusive(): void
    {
        Setting::put('gst_mode', 'NONSENSE');

        $this->assertSame(GstService::EXCLUSIVE, app(GstService::class)->mode());
    }
}
