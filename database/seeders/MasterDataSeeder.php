<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Category;
use App\Models\Country;
use App\Models\District;
use App\Models\Metal;
use App\Models\MetalRate;
use App\Models\Scheme;
use App\Models\Setting;
use App\Models\State;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------------- ADMIN USERS ---------------- */
        User::updateOrCreate(['email' => 'admin@scheme.test'], [
            'name' => 'Super Admin',
            'username' => 'admin',
            'password' => Hash::make('admin@123'),
            'phone' => '9999999999',
            'role' => 'SUPERADMIN',
            'status' => true,
        ]);

        User::updateOrCreate(['email' => 'accounts@scheme.test'], [
            'name' => 'Accountant',
            'username' => 'accounts',
            'password' => Hash::make('accounts@123'),
            'role' => 'ACCOUNTANT',
            'status' => true,
        ]);

        /* ---------------- LOCATION ---------------- */
        $india = Country::updateOrCreate(['code' => 'IN'], [
            'name' => 'India', 'phone_code' => '+91', 'currency' => 'INR', 'status' => true,
        ]);

        $kerala = State::updateOrCreate(
            ['country_id' => $india->id, 'name' => 'Kerala'],
            ['code' => 'KL', 'status' => true]
        );

        foreach ([
            'Thiruvananthapuram', 'Kollam', 'Pathanamthitta', 'Alappuzha', 'Kottayam',
            'Idukki', 'Ernakulam', 'Thrissur', 'Palakkad', 'Malappuram',
            'Kozhikode', 'Wayanad', 'Kannur', 'Kasaragod',
        ] as $d) {
            District::updateOrCreate(['state_id' => $kerala->id, 'name' => $d], ['status' => true]);
        }

        foreach (['Tamil Nadu' => 'TN', 'Karnataka' => 'KA', 'Maharashtra' => 'MH'] as $name => $code) {
            State::updateOrCreate(['country_id' => $india->id, 'name' => $name], ['code' => $code, 'status' => true]);
        }

        /* ---------------- METALS & RATES ---------------- */
        $gold22 = Metal::updateOrCreate(['code' => 'GOLD22'], [
            'name' => 'Gold 22K', 'purity' => '916', 'unit' => 'GRAM', 'status' => true,
        ]);
        $gold24 = Metal::updateOrCreate(['code' => 'GOLD24'], [
            'name' => 'Gold 24K', 'purity' => '999', 'unit' => 'GRAM', 'status' => true,
        ]);
        Metal::updateOrCreate(['code' => 'SILVER'], [
            'name' => 'Silver', 'purity' => '925', 'unit' => 'GRAM', 'status' => true,
        ]);

        MetalRate::updateOrCreate(
            ['metal_id' => $gold22->id, 'rate_date' => now()->toDateString()],
            ['rate' => 7250.00, 'status' => true]
        );
        MetalRate::updateOrCreate(
            ['metal_id' => $gold24->id, 'rate_date' => now()->toDateString()],
            ['rate' => 7910.00, 'status' => true]
        );

        /* ---------------- SCHEMES ---------------- */
        Scheme::updateOrCreate(['code' => 'GS11'], [
            'name' => 'Gold Saving 11 Months',
            'metal_id' => $gold22->id,
            'amount' => 2000,
            'min_amount' => 1000,
            'max_amount' => 50000,
            'flexible_amount' => true,
            'payment_interval' => 'MONTHLY',
            'total_installments' => 11,
            'duration_months' => 11,
            'bonus_type' => 'INSTALLMENT',
            'bonus_value' => 1,          // 1 free installment on maturity
            'gst_percent' => 3,
            'gram_based' => true,
            'description' => '11 masam adakkuka, 12-am masam company adakkum.',
            'status' => true,
        ]);

        Scheme::updateOrCreate(['code' => 'GS12'], [
            'name' => 'Gold Plus 12 Months',
            'metal_id' => $gold22->id,
            'amount' => 5000,
            'payment_interval' => 'MONTHLY',
            'total_installments' => 12,
            'duration_months' => 12,
            'bonus_type' => 'PERCENT',
            'bonus_value' => 5,
            'gst_percent' => 3,
            'gram_based' => true,
            'status' => true,
        ]);

        Scheme::updateOrCreate(['code' => 'DS100'], [
            'name' => 'Daily Saving Scheme',
            'metal_id' => $gold22->id,
            'amount' => 100,
            'payment_interval' => 'DAILY',
            'total_installments' => 300,
            'duration_months' => 10,
            'bonus_type' => 'NONE',
            'gst_percent' => 3,
            'status' => true,
        ]);

        /* ---------------- CATEGORY ---------------- */
        foreach (['Rings', 'Necklaces', 'Bangles', 'Earrings', 'Chains', 'Bracelets'] as $i => $c) {
            Category::updateOrCreate(['name' => $c], ['sortorder' => $i + 1, 'status' => true]);
        }

        /* ---------------- BANK ---------------- */
        Bank::updateOrCreate(['account_no' => '0123456789012'], [
            'bank_name' => 'State Bank of India',
            'beneficiary_name' => 'Ishq Jewellery',
            'ifsc' => 'SBIN0001234',
            'branch' => 'Main Branch',
            'upi_id' => 'ishqjewellery@sbi',
            'accode' => '1002',
            'status' => true,
        ]);

        /* ---------------- TERMS ---------------- */
        Term::updateOrCreate(['title' => 'Scheme Terms & Conditions'], [
            'description' => '<p>Scheme-il chernnal 11 masam thudarchayayi adakkanam. '
                .'Maturity date-inu munpu close cheythal bonus labhikkilla.</p>',
            'type' => 'TERMS',
            'status' => true,
        ]);

        /* ---------------- SETTINGS ---------------- */
        foreach ([
            'company_name' => 'Ishq Jewellery',
            'company_tagline' => 'Gold And Diamonds',
            'company_phone' => '+91 98470 00000',
            'company_email' => 'info@ishqjewellery.com',
            'company_address' => 'Main Road, Kochi, Kerala 682001',
            'company_gstin' => '32AAAAA0000A1Z5',
            'referral_points' => '100',
            'point_value' => '1',
            'gst_percent' => '3',
            'customer_code_prefix' => 'CUS',
            'scheme_account_prefix' => 'SCH',
            'receipt_code_prefix' => 'RCT',
            'voucher_prefix_receipt' => 'RCP',
            'voucher_prefix_payment' => 'PMT',
            'voucher_prefix_sales' => 'SAL',
            'voucher_prefix_journal' => 'JV',
        ] as $k => $v) {
            Setting::put($k, $v, 'company');
        }

        $this->command?->info('  Master data seeded.');
    }
}
