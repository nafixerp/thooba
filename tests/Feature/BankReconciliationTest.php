<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\Customer;
use App\Models\CustomerScheme;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Scheme;
use App\Models\User;
use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Proving a direct UPI payment actually arrived.
 *
 * The app's "Status=SUCCESS" is the customer's device talking. The bank
 * statement is the evidence, and this is what matches one against the other.
 */
class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected BankReconciliationService $reconcile;

    protected Customer $customer;

    protected CustomerScheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->reconcile = app(BankReconciliationService::class);

        $this->customer = Customer::create([
            'reg_no' => 'CUS000001',
            'name' => 'Recon Test',
            'phone' => '9000000001',
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
    }

    /** A claimed UPI payment plus the order behind it. */
    protected function claim(
        float $total = 5150,
        string $date = '2026-07-01',
        ?string $utr = null,
        string $orderNo = 'ORD260925000001',
    ): Payment {
        $payment = Payment::create([
            'receipt_no' => 'RCT'.uniqid(),
            'customer_id' => $this->customer->id,
            'customer_scheme_id' => $this->scheme->id,
            'installment_id' => Installment::value('id'),
            'amount' => round($total / 1.03, 2),
            'tax' => round($total - round($total / 1.03, 2), 2),
            'total' => $total,
            'payment_date' => $date,
            'payment_mode' => 'UPI',
            'txn_ref' => $utr,
            'status' => 'PENDING',
        ]);

        PaymentOrder::create([
            'order_no' => $orderNo,
            'gateway' => 'upi',
            'customer_id' => $this->customer->id,
            'customer_scheme_id' => $this->scheme->id,
            'payment_id' => $payment->id,
            'amount' => $total,
            'currency' => 'INR',
            'status' => 'SUBMITTED',
        ]);

        return $payment;
    }

    protected function csv(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt').'.csv';
        file_put_contents($path, $body);

        return $path;
    }

    /* ===================================================================== */
    /*  Parsing                                                              */
    /* ===================================================================== */

    public function test_it_reads_a_typical_bank_export(): void
    {
        // Account blurb above the header, as every net banking export has.
        $path = $this->csv(<<<CSV
        Account Statement for 1234567890
        Period: 01/07/2026 to 31/07/2026

        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.,Closing Balance
        01/07/2026,UPI-RECON TEST-ORD260925000001,UPI/518123456789,,"5,150.00","1,05,150.00"
        02/07/2026,ATM CASH,,"2,000.00",,"1,03,150.00"
        ,Total,,,,
        CSV);

        $rows = $this->reconcile->parse($path);

        $this->assertCount(2, $rows, 'the totals row should be dropped');

        $this->assertSame('2026-07-01', $rows[0]['txn_date']);
        $this->assertEqualsWithDelta(5150, $rows[0]['credit'], 0.01);
        $this->assertEqualsWithDelta(0, $rows[0]['debit'], 0.01);
        $this->assertEqualsWithDelta(105150, $rows[0]['balance'], 0.01);

        $this->assertEqualsWithDelta(2000, $rows[1]['debit'], 0.01);
    }

    public function test_it_accepts_the_other_common_column_names(): void
    {
        $path = $this->csv(<<<CSV
        Transaction Date,Description,Reference,Debit,Credit,Balance
        05-07-2026,UPI IN,UTR99887766,0,5150,50000
        CSV);

        $rows = $this->reconcile->parse($path);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-07-05', $rows[0]['txn_date']);
        $this->assertEqualsWithDelta(5150, $rows[0]['credit'], 0.01);
    }

    public function test_a_file_with_no_header_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->reconcile->parse($this->csv("some,random,file\n1,2,3\n"));
    }

    public function test_the_same_statement_twice_does_not_duplicate(): void
    {
        $body = <<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.,Closing Balance
        01/07/2026,UPI-RECON,UPI/1,,5150.00,105150.00
        CSV;

        $first = $this->reconcile->import($this->reconcile->parse($this->csv($body)));
        $second = $this->reconcile->import($this->reconcile->parse($this->csv($body)));

        $this->assertSame(1, $first['imported']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(1, $second['duplicates']);
        $this->assertSame(1, BankStatementLine::count());
    }

    /* ===================================================================== */
    /*  Matching                                                             */
    /* ===================================================================== */

    public function test_our_order_number_in_the_narration_is_an_exact_match(): void
    {
        $payment = $this->claim(orderNo: 'ORD260925000001');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI-RECON TEST-ORD260925000001-PAYMENT,UPI/5181,,5150.00
        CSV)));

        $result = $this->reconcile->matchAll();

        $this->assertSame(1, $result['exact']);
        $this->assertSame(0, $result['likely']);

        $line = BankStatementLine::firstOrFail();
        $this->assertSame($payment->id, $line->payment_id);
        $this->assertSame('ORDER_NO', $line->match_type);
        $this->assertTrue($line->isStrongMatch());
    }

    public function test_the_customers_utr_is_an_exact_match(): void
    {
        $payment = $this->claim(utr: 'UTR523401998877', orderNo: 'ORDNOTQUOTED1');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI COLLECTION,UTR523401998877,,5150.00
        CSV)));

        $this->assertSame(1, $this->reconcile->matchAll()['exact']);

        $line = BankStatementLine::firstOrFail();
        $this->assertSame($payment->id, $line->payment_id);
        $this->assertSame('UTR', $line->match_type);
    }

    public function test_amount_and_date_alone_is_only_a_likely_match(): void
    {
        $payment = $this->claim(date: '2026-07-01', orderNo: 'ORDSILENT0001');

        // Narration gives nothing away, and the credit is two days later.
        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        03/07/2026,UPI CR,,,5150.00
        CSV)));

        $result = $this->reconcile->matchAll();

        $this->assertSame(0, $result['exact']);
        $this->assertSame(1, $result['likely']);

        $line = BankStatementLine::firstOrFail();
        $this->assertSame($payment->id, $line->payment_id);
        $this->assertSame('AMOUNT_DATE', $line->match_type);
        $this->assertFalse($line->isStrongMatch(), 'a guess must not count as proof');
    }

    public function test_two_identical_claims_are_left_for_a_person(): void
    {
        $this->claim(orderNo: 'ORDSILENT0001');
        $this->claim(orderNo: 'ORDSILENT0002');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI CR,,,5150.00
        CSV)));

        $this->assertSame(['exact' => 0, 'likely' => 0], $this->reconcile->matchAll());
        $this->assertSame('UNMATCHED', BankStatementLine::firstOrFail()->status);
    }

    public function test_a_credit_too_far_from_the_claim_is_not_matched(): void
    {
        $this->claim(date: '2026-07-01', orderNo: 'ORDSILENT0001');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        20/07/2026,UPI CR,,,5150.00
        CSV)));

        $this->assertSame(0, $this->reconcile->matchAll()['likely']);
    }

    public function test_a_reference_wins_over_an_amount_guess(): void
    {
        // Same amount and date, but only one is quoted by the bank.
        $quoted = $this->claim(orderNo: 'ORD260925000001');
        $silent = $this->claim(orderNo: 'ORDSILENT0002');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI-ORD260925000001,,,5150.00
        CSV)));

        $this->reconcile->matchAll();

        $this->assertSame($quoted->id, BankStatementLine::firstOrFail()->payment_id);
        $this->assertSame(
            1,
            $this->reconcile->claimedPayments()->where('id', $silent->id)->count(),
            'the unquoted claim should still be waiting',
        );
    }

    public function test_a_debit_line_never_matches_a_payment(): void
    {
        $this->claim(orderNo: 'ORD260925000001');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI-ORD260925000001 REVERSAL,,5150.00,
        CSV)));

        $this->assertSame(['exact' => 0, 'likely' => 0], $this->reconcile->matchAll());
    }

    public function test_an_already_approved_payment_is_not_a_candidate(): void
    {
        $payment = $this->claim(orderNo: 'ORD260925000001');
        $payment->update(['status' => 'APPROVED']);

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI-ORD260925000001,,,5150.00
        CSV)));

        $this->assertSame(['exact' => 0, 'likely' => 0], $this->reconcile->matchAll());
    }

    /* ===================================================================== */
    /*  Approving against the bank                                           */
    /* ===================================================================== */

    protected function admin(): User
    {
        return User::create([
            'name' => 'Accounts',
            'username' => 'accounts',
            'email' => 'accounts@example.test',
            'password' => 'secret123',
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);
    }

    public function test_approving_a_matched_line_posts_the_voucher(): void
    {
        $payment = $this->claim(orderNo: 'ORD260925000001');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI-ORD260925000001,,,5150.00
        CSV)));
        $this->reconcile->matchAll();

        $line = BankStatementLine::firstOrFail();

        $this->assertNull($payment->daybook_sno);

        $this->actingAs($this->admin())
            ->post("/accounts/reconciliation/{$line->id}/approve")
            ->assertRedirect();

        $payment->refresh();

        $this->assertSame('APPROVED', $payment->status);
        $this->assertNotNull($payment->daybook_sno, 'the bank confirmed it, so it should post');
        $this->assertSame('PAID', Installment::firstOrFail()->status);
    }

    public function test_bulk_approve_only_touches_reference_matches(): void
    {
        $quoted = $this->claim(orderNo: 'ORD260925000001', date: '2026-07-01');
        $guessed = $this->claim(total: 3090, orderNo: 'ORDSILENT0002', date: '2026-07-02');

        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,UPI-ORD260925000001,,,5150.00
        02/07/2026,UPI CR,,,3090.00
        CSV)));

        $result = $this->reconcile->matchAll();
        $this->assertSame(1, $result['exact']);
        $this->assertSame(1, $result['likely']);

        $this->actingAs($this->admin())
            ->post('/accounts/reconciliation/approve-matched')
            ->assertRedirect();

        $this->assertSame('APPROVED', $quoted->fresh()->status);
        $this->assertSame(
            'PENDING',
            $guessed->fresh()->status,
            'an amount-and-date guess must not be approved in bulk',
        );
    }

    public function test_the_screen_renders(): void
    {
        $this->claim(orderNo: 'ORD260925000001');

        $this->actingAs($this->admin())
            ->get('/accounts/reconciliation')
            ->assertOk()
            ->assertSee('Bank Reconciliation', false);
    }

    public function test_a_line_can_be_set_aside_and_reopened(): void
    {
        $this->reconcile->import($this->reconcile->parse($this->csv(<<<CSV
        Date,Narration,Chq./Ref.No.,Withdrawal Amt.,Deposit Amt.
        01/07/2026,INTEREST CREDITED,,,120.00
        CSV)));

        $line = BankStatementLine::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/accounts/reconciliation/{$line->id}/ignore", [
            'note' => 'Bank interest',
        ])->assertRedirect();

        $this->assertSame('IGNORED', $line->fresh()->status);

        $this->actingAs($admin)->post("/accounts/reconciliation/{$line->id}/reset")->assertRedirect();

        $this->assertSame('UNMATCHED', $line->fresh()->status);
    }
}
