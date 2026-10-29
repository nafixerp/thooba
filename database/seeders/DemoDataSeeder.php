<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Scheme;
use App\Services\AccountingService;
use App\Services\SchemeService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data - customers, agents, enrollments and REAL posted payments,
 * so Trial Balance / P&L / Balance Sheet-il data kaanam.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = app(AccountingService::class);
        $schemeSvc = app(SchemeService::class);

        /* ---------------- Opening capital ---------------- */
        $accounts->postSimple('1002', '2010', 500000, [
            'voucher_type' => 'OPENING',
            'voucher_date' => now()->startOfYear()->toDateString(),
            'narration' => 'Opening capital introduced',
            'is_opening' => true,
        ]);

        $accounts->postSimple('1001', '1002', 50000, [
            'voucher_type' => 'CONTRA',
            'voucher_date' => now()->startOfYear()->addDay()->toDateString(),
            'narration' => 'Cash withdrawn from bank for counter',
        ]);

        /* ---------------- Agents ---------------- */
        $agents = [];
        foreach ([
            ['Rahul Menon', '9847011111', 2.5],
            ['Fathima Beevi', '9847022222', 2.0],
            ['Joseph Thomas', '9847033333', 3.0],
        ] as $i => [$name, $phone, $comm]) {
            $a = Agent::updateOrCreate(['phone' => $phone], [
                'agent_code' => 'AGT'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@agent.test',
                'password' => Hash::make($phone),
                'must_change_password' => true,
                'commission_percent' => $comm,
                'join_date' => now()->subMonths(10)->toDateString(),
                'status' => true,
            ]);
            $accounts->ledgerForAgent($a);
            $agents[] = $a;
        }

        /* ---------------- Customers + Schemes + Payments ---------------- */
        $names = [
            'Anand Krishnan', 'Sneha Nair', 'Muhammed Ashraf', 'Divya Menon',
            'Vishnu Prasad', 'Aleena Jose', 'Rajesh Kumar', 'Nithya Suresh',
            'Sameer Ali', 'Lakshmi Devi', 'Arun Babu', 'Fathima Rasheed',
        ];

        $scheme = Scheme::where('code', 'GS11')->first();
        $scheme12 = Scheme::where('code', 'GS12')->first();

        foreach ($names as $i => $name) {
            $phone = '90000'.str_pad((string) (10000 + $i), 5, '0', STR_PAD_LEFT);

            $c = Customer::updateOrCreate(['phone' => $phone], [
                'reg_no' => 'CUS'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@mail.test',
                'password' => Hash::make('customer@123'),
                'agent_id' => $agents[$i % count($agents)]->id,
                'referral_code' => 'REF'.strtoupper(substr(md5($phone), 0, 6)),
                'join_date' => now()->subMonths(8)->toDateString(),
                'kyc_status' => 'VERIFIED',
                'status' => true,
            ]);

            $accounts->ledgerForCustomer($c);

            $useScheme = $i % 3 === 0 ? $scheme12 : $scheme;

            // Pay 3-6 installments, ending in the current month so the
            // dashboard has fresh data to show.
            $payCount = 3 + ($i % 4);
            $start = now()->startOfMonth()->subMonths($payCount - 1)->addDays(min($i, 27));

            $cs = $schemeSvc->enroll($c, $useScheme, [
                'start_date' => $start->toDateString(),
                'installment_amount' => $useScheme->amount,
                'agent_id' => $c->agent_id,
            ]);

            for ($n = 0; $n < $payCount; $n++) {
                $date = $start->copy()->addMonths($n);

                // Every 4th customer's last payment lands today.
                if ($n === $payCount - 1 && $i % 4 === 0) {
                    $date = now();
                }
                if ($date->isFuture()) {
                    $date = now();
                }

                $schemeSvc->recordPayment([
                    'customer_id' => $c->id,
                    'customer_scheme_id' => $cs->id,
                    'amount' => (float) $cs->installment_amount,
                    'payment_date' => $date->toDateString(),
                    'payment_mode' => $n % 2 === 0 ? 'CASH' : 'UPI',
                    'payment_type' => 'INSTALLMENT',
                    'agent_id' => $c->agent_id,
                    'status' => 'APPROVED',
                ]);
            }

            // Ithil randenam pending aayi vekkam (approval screen kaanan)
            if ($i % 5 === 0) {
                $schemeSvc->recordPayment([
                    'customer_id' => $c->id,
                    'customer_scheme_id' => $cs->id,
                    'amount' => (float) $cs->installment_amount,
                    'payment_date' => now()->toDateString(),
                    'payment_mode' => 'UPI',
                    'txn_ref' => 'UPI'.rand(100000, 999999),
                    'status' => 'PENDING',
                ]);
            }

            $schemeSvc->addPoints($c, 100, 'REFERRAL', 'Welcome bonus points');
        }

        /* ---------------- Expenses ---------------- */
        $expenses = [
            ['5003', 25000, 'Shop rent'],
            ['5002', 85000, 'Staff salary'],
            ['5004', 6500, 'Electricity bill'],
            ['5005', 15000, 'Newspaper advertisement'],
            ['5008', 1200, 'Bank charges'],
        ];

        foreach ($expenses as $m => [$accode, $amt, $narr]) {
            $accounts->postSimple($accode, $m % 2 ? '1001' : '1002', $amt, [
                'voucher_type' => 'PAYMENT',
                'voucher_date' => now()->subMonths(2)->addDays($m)->toDateString(),
                'narration' => $narr,
            ]);
        }

        /* ---------------- Agent commission ---------------- */
        foreach ($agents as $a) {
            $collected = $a->collection();
            $comm = round($collected * $a->commission_percent / 100, 2);
            if ($comm <= 0) {
                continue;
            }

            $voucher = $accounts->postAgentCommission($comm, 'BANK', now()->subMonth()->toDateString(), [
                'narration' => 'Commission to '.$a->name,
            ]);

            $a->payouts()->create([
                'from_date' => now()->subMonths(6)->toDateString(),
                'to_date' => now()->subMonth()->toDateString(),
                'collected_amount' => $collected,
                'amount' => $comm,
                'received_date' => now()->subMonth()->toDateString(),
                'payment_mode' => 'BANK',
                'status' => 'PAID',
                'daybook_sno' => $voucher->sno,
                'notes' => 'Auto generated demo payout',
            ]);
        }

        /* ---------------- A gold sale ---------------- */
        $accounts->postSimple('1001', '4001', 125000, [
            'voucher_type' => 'SALES',
            'voucher_date' => now()->subDays(10)->toDateString(),
            'narration' => 'Counter gold sale',
        ]);

        $this->command?->info('  Demo data seeded: '.Customer::count().' customers, '
            .Agent::count().' agents.');
    }
}
