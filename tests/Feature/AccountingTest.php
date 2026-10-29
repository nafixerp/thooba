<?php

namespace Tests\Feature;

use App\Models\Accountm;
use App\Models\Daybook;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accounts;

    protected FinancialReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->accounts = app(AccountingService::class);
        $this->reports = app(FinancialReportService::class);
    }

    public function test_a_simple_voucher_posts_both_sides(): void
    {
        $v = $this->accounts->postSimple('1001', '4001', 5000, [
            'voucher_type' => 'SALES',
            'voucher_date' => '2026-05-10',
            'narration' => 'Counter sale',
        ]);

        $this->assertCount(2, $v->lines);
        $this->assertEqualsWithDelta(0, Daybook::where('sno', $v->sno)->sum('amount'), 0.005);
        $this->assertEqualsWithDelta(5000, $v->debit_total, 0.005);
        $this->assertEqualsWithDelta(5000, $v->credit_total, 0.005);
        $this->assertTrue($v->isBalanced());
    }

    public function test_unbalanced_voucher_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->accounts->post(
            ['voucher_type' => 'JOURNAL', 'voucher_date' => '2026-05-10'],
            [
                ['accode' => '1001', 'amount' => 100],
                ['accode' => '4001', 'amount' => -90],
            ]
        );
    }

    public function test_unknown_ledger_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->accounts->postSimple('9999', '1001', 100, [
            'voucher_type' => 'JOURNAL', 'voucher_date' => '2026-05-10',
        ]);
    }

    public function test_scheme_receipt_splits_gst(): void
    {
        Accountm::create([
            'accode' => 'C000001', 'name' => 'Test Customer', 'grcode' => 'G2010',
            'bshead' => 'BS', 'actype' => 'CUSTOMER', 'opening_drcr' => 'C',
        ]);

        $v = $this->accounts->postSchemeReceipt('C000001', 2000, 60, 'CASH', '2026-05-10');

        $this->assertCount(3, $v->lines);
        $this->assertEqualsWithDelta(2060, $v->debit_total, 0.005);
        $this->assertEqualsWithDelta(-2000, Daybook::where('accode', 'C000001')->sum('amount'), 0.005);
        $this->assertEqualsWithDelta(-60, Daybook::where('accode', '2002')->sum('amount'), 0.005);
    }

    public function test_trial_balance_tallies(): void
    {
        $this->accounts->postSimple('1002', '2010', 100000, ['voucher_type' => 'OPENING', 'voucher_date' => '2026-04-01']);
        $this->accounts->postSimple('1001', '1002', 20000, ['voucher_type' => 'CONTRA', 'voucher_date' => '2026-04-02']);
        $this->accounts->postSimple('5003', '1001', 5000, ['voucher_type' => 'PAYMENT', 'voucher_date' => '2026-04-05']);
        $this->accounts->postSimple('1001', '4001', 30000, ['voucher_type' => 'SALES', 'voucher_date' => '2026-04-10']);

        $tb = $this->reports->trialBalance('2026-04-01', '2026-06-30');

        $this->assertTrue($tb['balanced'], 'Trial balance did not tally');
        $this->assertEqualsWithDelta($tb['totals']['dr'], $tb['totals']['cr'], 0.05);
        $this->assertEqualsWithDelta($tb['totals']['closing_dr'], $tb['totals']['closing_cr'], 0.05);
    }

    public function test_balance_sheet_tallies_and_carries_profit(): void
    {
        $this->accounts->postSimple('1002', '2010', 100000, ['voucher_type' => 'OPENING', 'voucher_date' => '2026-04-01']);
        $this->accounts->postSimple('1001', '4001', 50000, ['voucher_type' => 'SALES', 'voucher_date' => '2026-05-01']);
        $this->accounts->postSimple('5003', '1001', 20000, ['voucher_type' => 'PAYMENT', 'voucher_date' => '2026-05-02']);

        $pl = $this->reports->profitAndLoss('2026-04-01', '2026-06-30');
        $bs = $this->reports->balanceSheet('2026-06-30', '2026-04-01');

        $this->assertEqualsWithDelta(50000, $pl['total_income'], 0.05);
        $this->assertEqualsWithDelta(20000, $pl['total_expense'], 0.05);
        $this->assertEqualsWithDelta(30000, $pl['net_profit'], 0.05);

        $this->assertTrue($bs['balanced'], 'Balance sheet difference: '.$bs['difference']);
        $this->assertEqualsWithDelta(30000, $bs['net_profit'], 0.05);
    }

    public function test_cancelled_voucher_leaves_the_reports(): void
    {
        $this->accounts->postSimple('1001', '4001', 10000, ['voucher_type' => 'SALES', 'voucher_date' => '2026-05-01']);
        $v = $this->accounts->postSimple('1001', '4001', 5000, ['voucher_type' => 'SALES', 'voucher_date' => '2026-05-02']);

        $before = $this->reports->profitAndLoss('2026-04-01', '2026-06-30')['total_income'];
        $this->accounts->cancel($v->sno, 'test');
        $after = $this->reports->profitAndLoss('2026-04-01', '2026-06-30')['total_income'];

        $this->assertEqualsWithDelta(5000, $before - $after, 0.05);
        $this->assertTrue($this->reports->trialBalance('2026-04-01', '2026-06-30')['balanced']);
    }

    public function test_general_ledger_running_balance(): void
    {
        $this->accounts->postSimple('1001', '4001', 1000, ['voucher_type' => 'SALES', 'voucher_date' => '2026-05-01']);
        $this->accounts->postSimple('5003', '1001', 400, ['voucher_type' => 'PAYMENT', 'voucher_date' => '2026-05-02']);

        $gl = $this->reports->generalLedger('1001', '2026-04-01', '2026-06-30');

        $this->assertEqualsWithDelta(1000, $gl['total_debit'], 0.005);
        $this->assertEqualsWithDelta(400, $gl['total_credit'], 0.005);
        $this->assertEqualsWithDelta(600, $gl['closing'], 0.005);
        $this->assertSame('Dr', $gl['closing_side']);
    }
}
