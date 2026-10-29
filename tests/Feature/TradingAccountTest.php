<?php

namespace Tests\Feature;

use App\Models\StockValue;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use App\Services\TradingAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The Trading / Profit & Loss structure ported from dua soft, and the reports
 * that hang off it.
 *
 * The one rule that matters: whatever the statement says the net profit is,
 * the Balance Sheet has to tally with it.
 */
class TradingAccountTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accounts;

    protected FinancialReportService $reports;

    protected TradingAccountService $trading;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL points at a subdirectory, which would otherwise prefix every
        // test request and 404 the lot.
        URL::forceRootUrl('http://localhost');
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->accounts = app(AccountingService::class);
        $this->reports = app(FinancialReportService::class);
        $this->trading = app(TradingAccountService::class);
    }

    protected function admin(): User
    {
        return User::create([
            'name' => 'Report Viewer',
            'username' => 'reports',
            'email' => 'reports@example.test',
            'password' => 'secret123',
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);
    }

    /** Capital in, goods bought, goods sold, an indirect expense paid. */
    protected function tradeSomeGoods(): void
    {
        // Capital introduced
        $this->accounts->postSimple('1002', '2010', 500000, ['voucher_type' => 'OPENING', 'voucher_date' => '2026-04-01']);
        // Purchases (direct cost, Trading A/c)
        $this->accounts->postSimple('3001', '1002', 300000, ['voucher_type' => 'PURCHASE', 'voucher_date' => '2026-04-10']);
        // Sales (direct income, Trading A/c)
        $this->accounts->postSimple('1001', '4001', 420000, ['voucher_type' => 'SALES', 'voucher_date' => '2026-05-15']);
        // Rent (indirect expense, P&L A/c)
        $this->accounts->postSimple('5003', '1001', 60000, ['voucher_type' => 'PAYMENT', 'voucher_date' => '2026-05-20']);
        // Interest received (indirect income, P&L A/c)
        $this->accounts->postSimple('1002', '4020', 5000, ['voucher_type' => 'RECEIPT', 'voucher_date' => '2026-06-01']);
    }

    public function test_direct_items_land_in_the_trading_account_and_indirect_items_in_the_pl(): void
    {
        $this->tradeSomeGoods();

        $s = $this->trading->statement('2026-04-01', '2026-06-30');

        $this->assertCount(1, $s['trading'], 'Only the first trading section should be in use');

        $trading = $s['trading'][0];
        $names = fn (array $lines) => array_column($lines, 'name');

        $this->assertContains('Gold Purchase', $names($trading['debit']));
        $this->assertContains('Gold Sales', $names($trading['credit']));

        $pl = $s['profit_loss'];
        $this->assertContains('Rent', $names($pl['debit']));
        $this->assertContains('Interest Received', $names($pl['credit']));

        // Direct items must NOT also appear in the P&L section.
        $this->assertNotContains('Gold Purchase', $names($pl['debit']));
        $this->assertNotContains('Gold Sales', $names($pl['credit']));
    }

    public function test_gross_profit_is_carried_down_into_the_profit_and_loss_account(): void
    {
        $this->tradeSomeGoods();

        $s = $this->trading->statement('2026-04-01', '2026-06-30');

        // 420000 sales - 300000 purchases
        $this->assertEqualsWithDelta(120000, $s['gross_profit'], 0.05);
        $this->assertTrue($s['is_gross_profit']);

        $bd = collect($s['profit_loss']['credit'])->firstWhere('accode', TradingAccountService::GROSS_PROFIT);
        $this->assertNotNull($bd, 'Gross profit b/d is missing from the P&L');
        $this->assertSame('Gross Profit b/d', $bd['name']);
        $this->assertEqualsWithDelta(120000, $bd['amount'], 0.05);

        // 120000 gross + 5000 interest - 60000 rent
        $this->assertEqualsWithDelta(65000, $s['net_profit'], 0.05);
        $this->assertTrue($s['is_profit']);
    }

    public function test_every_section_squares_off(): void
    {
        $this->tradeSomeGoods();

        $s = $this->trading->statement('2026-04-01', '2026-06-30');

        foreach ($s['trading'] as $section) {
            $this->assertEqualsWithDelta(
                $section['total_debit'],
                $section['total_credit'],
                0.05,
                $section['title'].' does not square off',
            );
        }

        $this->assertEqualsWithDelta(
            $s['profit_loss']['total_debit'],
            $s['profit_loss']['total_credit'],
            0.05,
            'The P&L A/c does not square off',
        );
    }

    public function test_a_gross_loss_is_carried_down_on_the_debit_side(): void
    {
        $this->accounts->postSimple('1002', '2010', 500000, ['voucher_type' => 'OPENING', 'voucher_date' => '2026-04-01']);
        // Sold for less than it cost.
        $this->accounts->postSimple('3001', '1002', 300000, ['voucher_type' => 'PURCHASE', 'voucher_date' => '2026-04-10']);
        $this->accounts->postSimple('1001', '4001', 250000, ['voucher_type' => 'SALES', 'voucher_date' => '2026-05-15']);

        $s = $this->trading->statement('2026-04-01', '2026-06-30');

        $this->assertEqualsWithDelta(-50000, $s['gross_profit'], 0.05);
        $this->assertFalse($s['is_gross_profit']);

        $bd = collect($s['profit_loss']['debit'])->firstWhere('accode', TradingAccountService::GROSS_PROFIT);
        $this->assertNotNull($bd, 'Gross loss b/d should sit on the debit side of the P&L');
        $this->assertSame('Gross Loss b/d', $bd['name']);

        $this->assertEqualsWithDelta(-50000, $s['net_profit'], 0.05);
    }

    public function test_the_statement_and_the_balance_sheet_agree(): void
    {
        $this->tradeSomeGoods();

        $s = $this->trading->statement('2026-04-01', '2026-06-30');
        $bs = $this->reports->balanceSheet('2026-06-30', '2026-04-01');

        $this->assertEqualsWithDelta($s['net_profit'], $bs['net_profit'], 0.05);
        $this->assertTrue($bs['balanced'], 'Balance sheet out by '.$bs['difference']);
    }

    public function test_closing_stock_moves_both_the_trading_account_and_the_balance_sheet(): void
    {
        $this->tradeSomeGoods();

        $before = $this->trading->statement('2026-04-01', '2026-06-30');

        StockValue::create(['as_on' => '2026-06-30', 'amount' => 80000, 'note' => 'Physical count']);

        $after = $this->trading->statement('2026-04-01', '2026-06-30');
        $bs = $this->reports->balanceSheet('2026-06-30', '2026-04-01');

        // The stock ledger has no postings, so its book value is zero and the
        // whole 80,000 is new.
        $this->assertEqualsWithDelta(80000, $after['trading'][0]['closing_stock'], 0.05);
        $this->assertEqualsWithDelta(
            $before['gross_profit'] + 80000,
            $after['gross_profit'],
            0.05,
            'Closing stock did not lift the gross profit',
        );

        $this->assertEqualsWithDelta($after['net_profit'], $bs['net_profit'], 0.05);
        $this->assertTrue($bs['balanced'], 'Balance sheet out by '.$bs['difference'].' after valuing stock');

        // And the asset side carries the valuation.
        $stock = collect($bs['assets'])->firstWhere('group', 'Stock in Hand');
        $this->assertNotNull($stock, 'Closing stock is missing from the assets');
        $this->assertEqualsWithDelta(80000, $stock['total'], 0.05);
    }

    public function test_upto_date_mode_includes_ledger_opening_balances(): void
    {
        $this->tradeSomeGoods();

        // An opening balance on a P&L ledger only counts in "upto date" mode.
        \App\Models\Accountm::where('accode', '4010')->update([
            'opening_balance' => 25000,
            'opening_drcr' => 'C',
        ]);

        $period = $this->trading->statement('2026-04-01', '2026-06-30', false);
        $upto = $this->trading->statement('2026-04-01', '2026-06-30', true);

        $this->assertEqualsWithDelta(
            $period['net_profit'] + 25000,
            $upto['net_profit'],
            0.05,
            'Upto-date mode ignored the opening balance',
        );
    }

    public function test_a_sundry_debtor_in_credit_is_reported_as_a_creditor(): void
    {
        \App\Models\Accountm::create([
            'accode' => 'C000001', 'name' => 'Overpaid Customer', 'grcode' => 'G2010',
            'bshead' => 'BS', 'actype' => 'CUSTOMER', 'opening_drcr' => 'C',
        ]);

        // Customer pays in: their ledger goes into credit.
        $this->accounts->postSimple('1001', 'C000001', 9000, [
            'voucher_type' => 'RECEIPT', 'voucher_date' => '2026-05-01',
        ]);

        $rp = $this->reports->receivablePayableSummary('2026-06-30');

        $this->assertSame([], array_column($rp['receivable'], 'accode'));
        $this->assertContains('C000001', array_column($rp['payable'], 'accode'));
        $this->assertEqualsWithDelta(9000, $rp['total_payable'], 0.05);
    }

    public function test_cash_flow_and_receipts_payments_agree_with_the_cash_book(): void
    {
        $this->tradeSomeGoods();

        $cf = $this->reports->cashFlow('2026-04-01', '2026-06-30');
        $rp = $this->reports->receiptsAndPayments('2026-04-01', '2026-06-30');

        $this->assertEqualsWithDelta($cf['total_inflow'], $rp['total_receipts'], 0.05);
        $this->assertEqualsWithDelta($cf['total_outflow'], $rp['total_payments'], 0.05);
        $this->assertEqualsWithDelta($cf['closing'], $rp['closing'], 0.05);

        // Cash 420000 in less 60000 out, bank 500000 + 5000 in less 300000 out.
        $this->assertEqualsWithDelta(925000, $cf['total_inflow'], 0.05);
        $this->assertEqualsWithDelta(360000, $cf['total_outflow'], 0.05);
        $this->assertEqualsWithDelta(565000, $cf['closing'], 0.05);
    }

    public function test_group_trial_balance_matches_the_ledger_trial_balance(): void
    {
        $this->tradeSomeGoods();

        $detail = $this->reports->trialBalance('2026-04-01', '2026-06-30');
        $group = $this->reports->trialBalanceByGroup('2026-04-01', '2026-06-30');

        $this->assertTrue($group['balanced']);
        $this->assertEqualsWithDelta($detail['totals']['dr'], $group['totals']['debit'], 0.05);
        $this->assertEqualsWithDelta($detail['totals']['closing_dr'], $group['totals']['closing_dr'], 0.05);
        $this->assertEqualsWithDelta($detail['totals']['closing_cr'], $group['totals']['closing_cr'], 0.05);
    }

    public function test_every_accounts_screen_renders(): void
    {
        $this->tradeSomeGoods();
        StockValue::create(['as_on' => '2026-06-30', 'amount' => 80000]);

        $pages = [
            '/accounts/reports/trading-profit-loss',
            '/accounts/reports/trading-profit-loss?upto=1',
            '/accounts/reports/profit-loss',
            '/accounts/reports/balance-sheet',
            '/accounts/reports/balance-sheet-schedules',
            '/accounts/reports/trial-balance',
            '/accounts/reports/trial-balance-group',
            '/accounts/reports/cash-flow',
            '/accounts/reports/receipts-payments',
            '/accounts/reports/group-summary',
            '/accounts/reports/receivable-payable',
            '/accounts/reports/journal-register',
            '/accounts/reports/daybook',
            '/accounts/reports/outstanding',
            '/accounts/stock-values',
            '/accounts/ledgers/1001/edit',
        ];

        $admin = $this->admin();

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk("$page did not render");
        }
    }

    public function test_the_trading_statement_page_prints_its_figures(): void
    {
        $this->tradeSomeGoods();
        StockValue::create(['as_on' => '2026-06-30', 'amount' => 80000]);

        $html = $this->actingAs($this->admin())
            ->get('/accounts/reports/trading-profit-loss?from=2026-04-01&to=2026-06-30')
            ->assertOk()
            ->getContent();

        // Both accounts, and the line that joins them.
        $this->assertStringContainsString('Trading A/c', $html);
        $this->assertStringContainsString('Profit &amp; Loss A/c', $html);
        $this->assertStringContainsString('Gross Profit c/d', $html);
        $this->assertStringContainsString('Gross Profit b/d', $html);
        $this->assertStringContainsString('Net Profit', $html);

        // The ledgers, on the side they belong on.
        $this->assertStringContainsString('To Gold Purchase', $html);
        $this->assertStringContainsString('By Gold Sales', $html);
        $this->assertStringContainsString('To Rent', $html);
        $this->assertStringContainsString('By Interest Received', $html);
        $this->assertStringContainsString('By Closing Stock', $html);

        // Gross profit = 420000 sales + 80000 closing stock - 300000 purchases.
        $this->assertStringContainsString(money_in(200000), $html);
        // Net profit = 200000 + 5000 interest - 60000 rent.
        $this->assertStringContainsString(money_in(145000), $html);
    }

    public function test_every_csv_export_downloads(): void
    {
        $this->tradeSomeGoods();

        $reports = [
            'trading-profit-loss', 'profit-loss', 'balance-sheet', 'trial-balance',
            'trial-balance-group', 'cash-flow', 'receipts-payments', 'group-summary',
            'receivable-payable', 'general-ledger',
        ];

        $admin = $this->admin();

        foreach ($reports as $report) {
            $this->actingAs($admin)
                ->get("/accounts/reports/export/$report")
                ->assertOk("$report export failed");
        }
    }
}
