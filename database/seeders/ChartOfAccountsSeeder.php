<?php

namespace Database\Seeders;

use App\Models\Accountg;
use App\Models\Accountgbs;
use App\Models\Accountm;
use Illuminate\Database\Seeder;

/**
 * Standard Chart of Accounts.
 *
 *   accountgbs (base)  ->  accountg (group)  ->  accountm (ledger)
 *
 * config/scheme.php-le control accode ivideninnu varunnathaanu.
 * Ee codes maattarut - auto posting ithine aashrayikkunnu.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------------- LEVEL 1 : Group Base ---------------- */
        $bases = [
            ['gbscode' => '1', 'name' => 'Assets',      'nature' => 'ASSET',     'bshead' => 'BS', 'normal_side' => 'D', 'sortorder' => 1],
            ['gbscode' => '2', 'name' => 'Liabilities', 'nature' => 'LIABILITY', 'bshead' => 'BS', 'normal_side' => 'C', 'sortorder' => 2],
            ['gbscode' => '3', 'name' => 'Income',      'nature' => 'INCOME',    'bshead' => 'PL', 'normal_side' => 'C', 'sortorder' => 3],
            ['gbscode' => '4', 'name' => 'Expenses',    'nature' => 'EXPENSE',   'bshead' => 'PL', 'normal_side' => 'D', 'sortorder' => 4],
        ];

        foreach ($bases as $b) {
            Accountgbs::updateOrCreate(['gbscode' => $b['gbscode']], $b);
        }

        /* ---------------- LEVEL 2 : Groups ---------------- */
        $groups = [
            // ASSETS
            ['grcode' => 'G1010', 'name' => 'Current Assets',      'gbscode' => '1', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 11],
            ['grcode' => 'G1020', 'name' => 'Cash in Hand',        'gbscode' => '1', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 12, 'parent_grcode' => 'G1010'],
            ['grcode' => 'G1030', 'name' => 'Bank Accounts',       'gbscode' => '1', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 13, 'parent_grcode' => 'G1010'],
            ['grcode' => 'G1040', 'name' => 'Fixed Assets',        'gbscode' => '1', 'bshead' => 'BS', 'actype' => 'NORMAL',  'sortorder' => 14],
            ['grcode' => 'G1050', 'name' => 'Sundry Debtors',      'gbscode' => '1', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 15, 'parent_grcode' => 'G1010', 'flip_grcode' => 'G2020'],
            ['grcode' => 'G1060', 'name' => 'Stock in Hand',       'gbscode' => '1', 'bshead' => 'BS', 'actype' => 'NORMAL',  'sortorder' => 16, 'parent_grcode' => 'G1010'],
            ['grcode' => 'G1070', 'name' => 'Loans & Advances (Asset)', 'gbscode' => '1', 'bshead' => 'BS', 'actype' => 'NORMAL', 'sortorder' => 17],

            // LIABILITIES
            ['grcode' => 'G2010', 'name' => 'Scheme Customers',    'gbscode' => '2', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 21, 'flip_grcode' => 'G1050'],
            ['grcode' => 'G2020', 'name' => 'Agents / Sundry Creditors', 'gbscode' => '2', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 22, 'flip_grcode' => 'G1050'],
            ['grcode' => 'G2030', 'name' => 'Duties & Taxes',      'gbscode' => '2', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 23],
            ['grcode' => 'G2040', 'name' => 'Capital Account',     'gbscode' => '2', 'bshead' => 'BS', 'actype' => 'CONTROL', 'sortorder' => 24],
            ['grcode' => 'G2050', 'name' => 'Current Liabilities', 'gbscode' => '2', 'bshead' => 'BS', 'actype' => 'NORMAL',  'sortorder' => 25],
            ['grcode' => 'G2060', 'name' => 'Loans (Liability)',   'gbscode' => '2', 'bshead' => 'BS', 'actype' => 'NORMAL',  'sortorder' => 26],

            // INCOME
            ['grcode' => 'G3010', 'name' => 'Direct Income',       'gbscode' => '3', 'bshead' => 'PL', 'actype' => 'CONTROL', 'sortorder' => 31],
            ['grcode' => 'G3020', 'name' => 'Indirect Income',     'gbscode' => '3', 'bshead' => 'PL', 'actype' => 'NORMAL',  'sortorder' => 32],

            // EXPENSES
            ['grcode' => 'G4010', 'name' => 'Direct Expenses',     'gbscode' => '4', 'bshead' => 'PL', 'actype' => 'CONTROL', 'sortorder' => 41],
            ['grcode' => 'G4020', 'name' => 'Indirect Expenses',   'gbscode' => '4', 'bshead' => 'PL', 'actype' => 'NORMAL',  'sortorder' => 42],
        ];

        // parents first - the self references can only be written once every
        // group row exists.
        foreach ($groups as $g) {
            unset($g['parent_grcode'], $g['flip_grcode']);
            Accountg::updateOrCreate(['grcode' => $g['grcode']], $g);
        }

        foreach ($groups as $g) {
            $links = array_filter([
                'parent_grcode' => $g['parent_grcode'] ?? null,
                'flip_grcode' => $g['flip_grcode'] ?? null,
            ]);

            if ($links) {
                Accountg::where('grcode', $g['grcode'])->update($links);
            }
        }

        /* ---------------- LEVEL 3 : Ledgers ---------------- */
        $ledgers = [
            // ---- Assets ----
            ['accode' => '1001', 'name' => 'Cash in Hand',            'grcode' => 'G1020', 'bshead' => 'BS', 'actype' => 'CASH'],
            ['accode' => '1002', 'name' => 'Bank Account',            'grcode' => 'G1030', 'bshead' => 'BS', 'actype' => 'BANK'],
            ['accode' => '1003', 'name' => 'UPI Settlement Account',  'grcode' => 'G1030', 'bshead' => 'BS', 'actype' => 'BANK'],
            ['accode' => '1010', 'name' => 'Gold Stock',              'grcode' => 'G1060', 'bshead' => 'BS', 'actype' => 'STOCK'],
            ['accode' => '1020', 'name' => 'Furniture & Fixtures',    'grcode' => 'G1040', 'bshead' => 'BS', 'actype' => 'GENERAL'],
            ['accode' => '1030', 'name' => 'Advance to Suppliers',    'grcode' => 'G1070', 'bshead' => 'BS', 'actype' => 'GENERAL'],

            // ---- Liabilities ----
            ['accode' => '2001', 'name' => 'Scheme Deposit Control',  'grcode' => 'G2010', 'bshead' => 'BS', 'actype' => 'GENERAL', 'opening_drcr' => 'C'],
            ['accode' => '2002', 'name' => 'GST Payable',             'grcode' => 'G2030', 'bshead' => 'BS', 'actype' => 'DUTY',    'opening_drcr' => 'C'],
            ['accode' => '2003', 'name' => 'TDS Payable',             'grcode' => 'G2030', 'bshead' => 'BS', 'actype' => 'DUTY',    'opening_drcr' => 'C'],
            ['accode' => '2010', 'name' => 'Capital Account',         'grcode' => 'G2040', 'bshead' => 'BS', 'actype' => 'CAPITAL', 'opening_drcr' => 'C'],
            ['accode' => '2020', 'name' => 'Sundry Creditors',        'grcode' => 'G2020', 'bshead' => 'BS', 'actype' => 'SUPPLIER','opening_drcr' => 'C'],
            ['accode' => '2030', 'name' => 'Outstanding Expenses',    'grcode' => 'G2050', 'bshead' => 'BS', 'actype' => 'GENERAL', 'opening_drcr' => 'C'],

            // ---- Income ----
            ['accode' => '4001', 'tplpos' => 1, 'name' => 'Gold Sales',              'grcode' => 'G3010', 'bshead' => 'PL', 'actype' => 'INCOME',  'opening_drcr' => 'C'],
            ['accode' => '4002', 'tplpos' => 1, 'name' => 'Making Charges Income',   'grcode' => 'G3010', 'bshead' => 'PL', 'actype' => 'INCOME',  'opening_drcr' => 'C'],
            ['accode' => '4010', 'name' => 'Other Income',            'grcode' => 'G3020', 'bshead' => 'PL', 'actype' => 'INCOME',  'opening_drcr' => 'C'],
            ['accode' => '4020', 'name' => 'Interest Received',       'grcode' => 'G3020', 'bshead' => 'PL', 'actype' => 'INCOME',  'opening_drcr' => 'C'],

            // ---- Expenses ----
            ['accode' => '3001', 'tplpos' => 1, 'name' => 'Gold Purchase',           'grcode' => 'G4010', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5001', 'name' => 'Agent Commission',        'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5002', 'name' => 'Salary & Wages',          'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5003', 'name' => 'Rent',                    'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5004', 'name' => 'Electricity & Water',     'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5005', 'name' => 'Advertisement & Publicity','grcode' => 'G4020','bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5006', 'name' => 'Telephone & Internet',    'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5007', 'name' => 'Printing & Stationery',   'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5008', 'name' => 'Bank Charges',            'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5009', 'name' => 'Scheme Bonus Expense',    'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
            ['accode' => '5099', 'name' => 'Round Off',               'grcode' => 'G4020', 'bshead' => 'PL', 'actype' => 'EXPENSE'],
        ];

        foreach ($ledgers as $l) {
            Accountm::updateOrCreate(['accode' => $l['accode']], array_merge([
                'opening_balance' => 0,
                'opening_drcr' => 'D',
                // 0 = Profit & Loss A/c, 1 = Trading A/c. Direct income and
                // direct cost carry tplpos = 1 above and close into Gross Profit.
                'tplpos' => 0,
                'is_system' => true,
                'status' => true,
            ], $l));
        }

        $this->command?->info('  Chart of Accounts seeded: '
            .Accountgbs::count().' bases, '
            .Accountg::count().' groups, '
            .Accountm::count().' ledgers.');
    }
}
