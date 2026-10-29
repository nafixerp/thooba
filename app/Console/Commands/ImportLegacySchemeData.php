<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;

class ImportLegacySchemeData extends Command
{
    protected $signature = 'legacy:import-schemes
        {directory=storage/app : Directory containing the exported legacy CSV files}
        {--dry-run : Validate and summarize the files without changing the database}';

    protected $description = 'Import legacy SQL Anywhere scheme customers, agents, enrolments, collections, and closures';

    /** @var array<string, int> */
    private array $customerIds = [];

    /** @var array<string, int> */
    private array $agentIds = [];

    /** @var array<string, int> */
    private array $schemeIds = [];

    /** @var array<string, int> */
    private array $enrollmentIds = [];

    public function handle(): int
    {
        $directory = $this->absolutePath((string) $this->argument('directory'));
        $files = [
            'customers' => 'legacy_clients.csv',
            'agents' => 'legacy_agents.csv',
            'schemes' => 'legacy_scheme_types.csv',
            'enrollments' => 'legacy_enrollments.csv',
            'collections' => 'legacy_collections.csv',
            'finishes' => 'legacy_finishes.csv',
        ];

        foreach ($files as $key => $filename) {
            $files[$key] = $directory.DIRECTORY_SEPARATOR.$filename;
            if (! is_file($files[$key])) {
                $this->error("Missing export: {$files[$key]}");

                return self::FAILURE;
            }
        }

        $customers = iterator_to_array($this->csv($files['customers']), false);
        $agents = iterator_to_array($this->csv($files['agents']), false);
        $schemes = iterator_to_array($this->csv($files['schemes']), false);
        $enrollments = iterator_to_array($this->csv($files['enrollments']), false);
        $collections = iterator_to_array($this->csv($files['collections']), false);
        $finishes = iterator_to_array($this->csv($files['finishes']), false);

        $this->table(
            ['Legacy dataset', 'Rows'],
            [
                ['Customers', count($customers)],
                ['Agents', count($agents)],
                ['Scheme types', count($schemes)],
                ['Enrolments', count($enrollments)],
                ['Collections', count($collections)],
                ['Closures', count($finishes)],
            ]
        );

        $duplicateCustomers = count($customers) - count(array_unique(array_column($customers, 'code')));
        $duplicateCollections = count($collections) - count(array_unique(array_column($collections, 'slno')));

        if ($duplicateCustomers !== 0 || $duplicateCollections !== 0) {
            $this->error("Source key validation failed (customer duplicates={$duplicateCustomers}, collection duplicates={$duplicateCollections}).");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Validation passed; no database changes were made.');

            return self::SUCCESS;
        }

        if (DB::table('customers')->where('reg_no', 'like', 'LEGACY-%')->exists()
            || DB::table('payments')->where('receipt_no', 'like', 'LEGACY-%')->exists()) {
            $this->error('Legacy import records already exist. The command stopped to prevent duplicates.');

            return self::FAILURE;
        }

        $branchId = DB::table('branches')->orderBy('id')->value('id');
        $metalId = DB::table('metals')->where('code', 'GOLD22')->value('id')
            ?? DB::table('metals')->orderBy('id')->value('id');

        if (! $branchId || ! $metalId) {
            $this->error('A branch and a metal master are required before importing.');

            return self::FAILURE;
        }

        $knownCustomerCodes = [];
        foreach ($customers as $row) {
            $knownCustomerCodes[$this->text($row['code'] ?? '')] = true;
        }
        foreach ($enrollments as $row) {
            $knownCustomerCodes[$this->text($row['code'] ?? '')] = true;
        }
        foreach ($collections as $row) {
            $knownCustomerCodes[$this->text($row['code'] ?? '')] = true;
        }
        unset($knownCustomerCodes['']);

        $collectionAgentCounts = [];
        foreach ($collections as $row) {
            $code = $this->text($row['code'] ?? '');
            $agent = $this->text($row['agent'] ?? '');
            if ($code !== '' && $agent !== '') {
                $collectionAgentCounts[$code][$agent] = ($collectionAgentCounts[$code][$agent] ?? 0) + 1;
            }
        }

        $primaryAgents = [];
        foreach ($collectionAgentCounts as $code => $counts) {
            arsort($counts);
            $primaryAgents[$code] = (string) array_key_first($counts);
        }

        try {
            DB::transaction(function () use (
                $agents,
                $schemes,
                $customers,
                $enrollments,
                $collections,
                $finishes,
                $knownCustomerCodes,
                $primaryAgents,
                $branchId,
                $metalId,
            ) {
                $this->importAgents($agents, $collections, (int) $branchId);
                $this->importSchemes($schemes, $enrollments, (int) $metalId);
                $this->importCustomers($customers, $knownCustomerCodes, $primaryAgents, (int) $branchId);
                $this->importEnrollments($enrollments, $collections, $primaryAgents);
                $this->importCollections($collections, $enrollments);
                $this->importFinishes($finishes);
                $this->refreshCustomerGrams();
            }, 3);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Import rolled back: '.$e->getMessage());

            report($e);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Legacy scheme import completed successfully.');
        $this->table(
            ['Target table', 'Imported/linked rows'],
            [
                ['agents', count($this->agentIds)],
                ['customers', count($this->customerIds)],
                ['schemes', count($this->schemeIds)],
                ['customer_schemes', count($this->enrollmentIds)],
                ['payments', DB::table('payments')->where('receipt_no', 'like', 'LEGACY-%')->count()],
                ['installments', DB::table('installments')->whereNotNull('payment_id')->whereIn('customer_scheme_id', array_values($this->enrollmentIds))->count()],
                ['scheme_closures', DB::table('scheme_closures')->where('closure_no', 'like', 'LEGACY-%')->count()],
            ]
        );

        return self::SUCCESS;
    }

    /** @param array<int, array<string, string|null>> $sourceAgents */
    private function importAgents(array $sourceAgents, array $collections, int $branchId): void
    {
        $byCode = [];
        foreach ($sourceAgents as $row) {
            $code = $this->text($row['code'] ?? '');
            if ($code !== '') {
                $byCode[$code] = $row;
            }
        }

        foreach ($collections as $row) {
            $code = $this->text($row['agent'] ?? '');
            if ($code !== '' && ! isset($byCode[$code])) {
                $byCode[$code] = ['code' => $code, 'name' => "Legacy Agent {$code}"];
            }
        }

        ksort($byCode);
        $now = now();

        foreach ($byCode as $code => $row) {
            $phone = 'LGCY-AG-'.substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?: sha1($code), 0, 11);
            DB::table('agents')->insert([
                'agent_code' => $code,
                'branch_id' => $branchId,
                'name' => $this->text($row['name'] ?? '') ?: "Legacy Agent {$code}",
                'phone' => $phone,
                'username' => 'legacy_'.strtolower(preg_replace('/[^a-z0-9]+/i', '_', $code)),
                'password' => Hash::make($phone, ['rounds' => 8]),
                'must_change_password' => true,
                'commission_percent' => 0,
                'join_date' => null,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->agentIds[$code] = (int) DB::getPdo()->lastInsertId();
        }
    }

    /** @param array<int, array<string, string|null>> $sourceSchemes */
    private function importSchemes(array $sourceSchemes, array $enrollments, int $metalId): void
    {
        $byCode = [];
        foreach ($sourceSchemes as $row) {
            $code = $this->text($row['code'] ?? '');
            if ($code !== '') {
                $byCode[$code] = $row;
            }
        }

        foreach ($enrollments as $row) {
            $code = $this->schemeCode($row['kuritype'] ?? '');
            $byCode[$code] ??= [
                'code' => $code,
                'name' => $code === 'LEGACY' ? 'Unspecified Legacy Scheme' : "Legacy Scheme {$code}",
            ];
        }

        ksort($byCode);
        $now = now();

        foreach ($byCode as $code => $row) {
            $isOneYear = $code === 'SW1';
            $installments = $this->positiveInt($row['instnos'] ?? null) ?: ($isOneYear ? 365 : 180);
            $duration = $isOneYear ? 12 : 6;
            $amount = $this->number($row['instamt'] ?? null);
            $min = $this->number($row['collnmin'] ?? null);
            $max = $this->number($row['collnlimit'] ?? null);
            $bonus = $this->number($row['bonus'] ?? null);

            DB::table('schemes')->insert([
                'name' => $this->text($row['name'] ?? '') ?: "Legacy Scheme {$code}",
                'code' => $code,
                'metal_id' => $metalId,
                'amount' => $amount,
                'min_amount' => $min,
                'max_amount' => $max,
                'flexible_amount' => true,
                'payment_interval' => 'DAILY',
                'total_installments' => $installments,
                'duration_months' => $duration,
                'bonus_type' => $bonus != 0.0 ? 'AMOUNT' : 'NONE',
                'bonus_value' => $bonus,
                'gst_percent' => 0,
                'gram_based' => true,
                'description' => 'Imported from legacy SQL Anywhere scheme data. The legacy plan used flexible collection amounts.',
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->schemeIds[$code] = (int) DB::getPdo()->lastInsertId();
        }
    }

    /** @param array<int, array<string, string|null>> $sourceCustomers */
    private function importCustomers(array $sourceCustomers, array $knownCodes, array $primaryAgents, int $branchId): void
    {
        $byCode = [];
        foreach ($sourceCustomers as $row) {
            $code = $this->text($row['code'] ?? '');
            if ($code !== '') {
                $byCode[$code] = $row;
            }
        }

        foreach (array_keys($knownCodes) as $code) {
            $byCode[$code] ??= ['code' => $code, 'name' => "Legacy Customer {$code}"];
        }

        ksort($byCode, SORT_NATURAL | SORT_FLAG_CASE);
        $usedPhones = DB::table('customers')->pluck('phone')->filter()->mapWithKeys(fn ($p) => [(string) $p => true])->all();
        $now = now();

        foreach ($byCode as $code => $row) {
            $rawPhone = preg_replace('/\s+/', '', $this->text($row['mobile'] ?? ''));
            $phone = $rawPhone;
            if ($phone === '' || strlen($phone) > 20 || isset($usedPhones[$phone])) {
                $phone = $this->placeholderPhone($code, $usedPhones);
            }
            $usedPhones[$phone] = true;

            $addressParts = array_values(array_filter([
                $this->text($row['addr1'] ?? ''),
                $this->text($row['addr2'] ?? ''),
                $this->text($row['addr3'] ?? ''),
                $this->text($row['city'] ?? ''),
                $this->text($row['state'] ?? ''),
            ], fn ($v) => $v !== ''));
            if ($rawPhone !== '' && $phone !== $rawPhone) {
                $addressParts[] = "Legacy phone: {$rawPhone}";
            }
            $telephone = $this->text($row['telephone'] ?? '');
            if ($telephone !== '') {
                $addressParts[] = "Telephone: {$telephone}";
            }

            $panAadhaar = preg_replace('/\s+/', '', $this->text($row['panadhar'] ?? ''));
            $agentCode = $primaryAgents[$code] ?? '';
            $date = $this->date($row['adate'] ?? null);

            DB::table('customers')->insert([
                'reg_no' => 'LEGACY-'.$code,
                'branch_id' => $branchId,
                'name' => $this->text($row['name'] ?? '') ?: "Legacy Customer {$code}",
                'phone' => $phone,
                'email' => filter_var($this->text($row['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null,
                'password' => Hash::make($phone, ['rounds' => 8]),
                'must_change_password' => true,
                'source' => $agentCode !== '' ? 'AGENT' : 'ADMIN',
                'address' => $addressParts ? implode(', ', $addressParts) : null,
                'pincode' => Str::limit($this->text($row['pin'] ?? ''), 10, ''),
                'agent_id' => $this->agentIds[$agentCode] ?? null,
                'aadhaar' => preg_match('/^\d{12}$/', $panAadhaar) ? $panAadhaar : null,
                'pan' => preg_match('/^[A-Z]{5}\d{4}[A-Z]$/i', $panAadhaar) ? strtoupper($panAadhaar) : null,
                'join_date' => $date,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->customerIds[$code] = (int) DB::getPdo()->lastInsertId();
        }
    }

    /** @param array<int, array<string, string|null>> $sourceEnrollments */
    private function importEnrollments(array $sourceEnrollments, array $collections, array $primaryAgents): void
    {
        $byCode = [];
        foreach ($sourceEnrollments as $row) {
            $code = $this->text($row['code'] ?? '');
            if ($code !== '') {
                $byCode[$code] = $row;
            }
        }

        foreach ($collections as $row) {
            $code = $this->text($row['code'] ?? '');
            if ($code !== '' && ! isset($byCode[$code])) {
                $byCode[$code] = [
                    'code' => $code,
                    'startdate' => $this->date($row['tdate'] ?? null),
                    'kuritype' => '',
                    'finished' => 'N',
                ];
            }
        }

        ksort($byCode, SORT_NATURAL | SORT_FLAG_CASE);
        $now = now();

        foreach ($byCode as $code => $row) {
            $customerId = $this->customerIds[$code] ?? null;
            if (! $customerId) {
                throw new RuntimeException("Customer mapping is missing for legacy code {$code}.");
            }

            $schemeCode = $this->schemeCode($row['kuritype'] ?? '');
            $schemeId = $this->schemeIds[$schemeCode] ?? null;
            if (! $schemeId) {
                throw new RuntimeException("Scheme mapping is missing for legacy type {$schemeCode}.");
            }

            $start = $this->date($row['startdate'] ?? null) ?? now()->toDateString();
            $finished = strtoupper($this->text($row['finished'] ?? '')) === 'Y';
            $finishedDate = $this->usefulDate($row['finisheddate'] ?? null);
            $agentCode = $this->text($row['colnagent'] ?? '') ?: ($primaryAgents[$code] ?? '');
            $agentId = $this->agentIds[$agentCode] ?? null;

            DB::table('customer_schemes')->insert([
                'account_no' => 'LEGACY-'.$code,
                'book_no' => Str::limit($code, 30, ''),
                'customer_id' => $customerId,
                'scheme_id' => $schemeId,
                'agent_id' => $agentId,
                'installment_amount' => $this->number($row['instamt'] ?? null),
                'total_installments' => $this->positiveInt($row['instnos'] ?? null) ?: $this->baseInstallments($schemeCode),
                'paid_installments' => 0,
                'payment_interval' => 'DAILY',
                'start_date' => $start,
                'end_date' => $finishedDate,
                'maturity_date' => $finishedDate,
                'total_paid' => 0,
                'total_gst' => 0,
                'total_gram' => 0,
                'bonus_amount' => $this->number($row['bonus'] ?? null),
                'bonus_gram' => 0,
                'status' => $finished ? 'MATURED' : 'ACTIVE',
                'source' => $agentId ? 'AGENT' : 'ADMIN',
                'closed_date' => $finishedDate,
                'remarks' => 'Imported from legacy scheme account '.$code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->enrollmentIds[$code] = (int) DB::getPdo()->lastInsertId();

            $updates = [];
            $nominee = $this->text($row['nomname'] ?? '');
            if ($nominee !== '') {
                $updates['nominee_name'] = $nominee;
                $updates['nominee_relation'] = $this->text($row['nomrelation'] ?? '') ?: null;
            }
            if ($this->text($row['bankacno'] ?? '') !== '') {
                $updates['account_no'] = $this->text($row['bankacno']);
                $updates['bank_name'] = $this->text($row['bankname'] ?? '') ?: null;
                $updates['ifsc'] = $this->text($row['bankifsc'] ?? '') ?: null;
            }
            if ($agentId) {
                $updates['agent_id'] = $agentId;
                $updates['source'] = 'AGENT';
            }
            if ($updates) {
                $updates['updated_at'] = $now;
                DB::table('customers')->where('id', $customerId)->update($updates);
            }
        }
    }

    /**
     * @param array<int, array<string, string|null>> $collections
     * @param array<int, array<string, string|null>> $enrollments
     */
    private function importCollections(array $collections, array $enrollments): void
    {
        $stats = [];
        $sequence = [];
        $now = now();

        foreach ($enrollments as $row) {
            $code = $this->text($row['code'] ?? '');
            $amount = $this->number($row['collnopbal'] ?? null);
            $gram = $this->number($row['opwgt'] ?? null, 3);
            if ($code === '' || ($amount == 0.0 && $gram == 0.0)) {
                continue;
            }

            $customerId = $this->customerIds[$code] ?? null;
            $enrollmentId = $this->enrollmentIds[$code] ?? null;
            if (! $customerId || ! $enrollmentId) {
                throw new RuntimeException("Opening balance refers to unmapped account {$code}.");
            }

            $date = $this->date($row['startdate'] ?? null) ?? now()->toDateString();
            $receipt = 'LEGACY-OPEN-'.substr(sha1($code), 0, 20);
            $sequence[$code] = 1;

            DB::table('payments')->insert([
                'receipt_no' => $receipt,
                'customer_id' => $customerId,
                'customer_scheme_id' => $enrollmentId,
                'amount' => $amount,
                'tax' => 0,
                'total' => $amount,
                'rate' => 0,
                'gram' => $gram,
                'wallet_amount' => 0,
                'payment_date' => $date,
                'payment_type' => 'INSTALLMENT',
                'payment_mode' => 'CASH',
                'status' => 'APPROVED',
                'approved_at' => $this->timestampDate($date),
                'remarks' => 'Imported legacy opening scheme balance',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $paymentId = (int) DB::getPdo()->lastInsertId();

            DB::table('installments')->insert([
                'customer_scheme_id' => $enrollmentId,
                'installment_no' => 1,
                'due_date' => $date,
                'amount' => $amount,
                'paid_amount' => $amount,
                'gram' => $gram,
                'paid_date' => $date,
                'payment_id' => $paymentId,
                'status' => 'PAID',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $installmentId = (int) DB::getPdo()->lastInsertId();
            DB::table('payments')->where('id', $paymentId)->update(['installment_id' => $installmentId]);

            $stats[$code] = [
                'amount' => $amount,
                'gram' => $gram,
                'paid' => ($amount > 0 || $gram > 0) ? 1 : 0,
            ];
        }

        $bar = $this->output->createProgressBar(count($collections));
        $bar->setFormat('Importing collections [%bar%] %current%/%max%');
        $bar->start();

        foreach ($collections as $row) {
            $code = $this->text($row['code'] ?? '');
            $customerId = $this->customerIds[$code] ?? null;
            $enrollmentId = $this->enrollmentIds[$code] ?? null;
            if (! $customerId || ! $enrollmentId) {
                throw new RuntimeException("Collection {$row['slno']} refers to unmapped account {$code}.");
            }

            $date = $this->date($row['tdate'] ?? null) ?? now()->toDateString();
            $amount = $this->number($row['amount'] ?? null);
            $gram = $this->number($row['wgt'] ?? null, 3);
            $rate = $this->number($row['grate'] ?? null);
            $agentCode = $this->text($row['agent'] ?? '');
            $agentId = $this->agentIds[$agentCode] ?? null;
            $receipt = 'LEGACY-KC-'.$this->text($row['slno'] ?? '');
            $sequence[$code] = ($sequence[$code] ?? 0) + 1;

            DB::table('payments')->insert([
                'receipt_no' => $receipt,
                'customer_id' => $customerId,
                'customer_scheme_id' => $enrollmentId,
                'agent_id' => $agentId,
                'amount' => $amount,
                'tax' => 0,
                'total' => $amount,
                'rate' => $rate,
                'gram' => $gram,
                'wallet_amount' => 0,
                'payment_date' => $date,
                'payment_type' => 'INSTALLMENT',
                'payment_mode' => 'CASH',
                'txn_ref' => $this->text($row['docno'] ?? '') ?: ($this->text($row['rcptno'] ?? '') ?: null),
                'status' => 'APPROVED',
                'approved_at' => $this->timestampDate($date),
                'remarks' => $this->legacyCollectionNote($row),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $paymentId = (int) DB::getPdo()->lastInsertId();

            DB::table('installments')->insert([
                'customer_scheme_id' => $enrollmentId,
                'installment_no' => $sequence[$code],
                'due_date' => $date,
                'amount' => $amount,
                'paid_amount' => $amount,
                'gram' => $gram,
                'paid_date' => $date,
                'payment_id' => $paymentId,
                'status' => 'PAID',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $installmentId = (int) DB::getPdo()->lastInsertId();
            DB::table('payments')->where('id', $paymentId)->update(['installment_id' => $installmentId]);

            $stats[$code]['amount'] = ($stats[$code]['amount'] ?? 0.0) + $amount;
            $stats[$code]['gram'] = ($stats[$code]['gram'] ?? 0.0) + $gram;
            if ($amount > 0 || $gram > 0) {
                $stats[$code]['paid'] = ($stats[$code]['paid'] ?? 0) + 1;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        foreach ($this->enrollmentIds as $code => $enrollmentId) {
            $current = DB::table('customer_schemes')->where('id', $enrollmentId)->first();
            $amount = round((float) ($stats[$code]['amount'] ?? 0), 2);
            $gram = round((float) ($stats[$code]['gram'] ?? 0), 3);
            $paid = (int) ($stats[$code]['paid'] ?? 0);
            $isFinished = $current->status === 'MATURED';
            $totalInstallments = $isFinished
                ? max(1, $paid)
                : max((int) $current->total_installments, $paid + 1);

            DB::table('customer_schemes')->where('id', $enrollmentId)->update([
                'total_paid' => $amount,
                'total_gram' => $gram,
                'paid_installments' => min($paid, $totalInstallments),
                'total_installments' => $totalInstallments,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param array<int, array<string, string|null>> $finishes */
    private function importFinishes(array $finishes): void
    {
        $now = now();
        foreach ($finishes as $row) {
            $code = $this->text($row['code'] ?? '');
            $customerId = $this->customerIds[$code] ?? null;
            $enrollmentId = $this->enrollmentIds[$code] ?? null;
            if (! $customerId || ! $enrollmentId) {
                continue;
            }

            $schemeId = DB::table('customer_schemes')->where('id', $enrollmentId)->value('scheme_id');
            $date = $this->usefulDate($row['tdate'] ?? null);
            $paid = (float) DB::table('customer_schemes')->where('id', $enrollmentId)->value('total_paid');
            $bonus = $this->number($row['bonus'] ?? null);
            $payable = $this->number($row['allocamt'] ?? null);

            DB::table('scheme_closures')->insert([
                'closure_no' => 'LEGACY-KF-'.$this->text($row['slno'] ?? ''),
                'customer_id' => $customerId,
                'customer_scheme_id' => $enrollmentId,
                'scheme_id' => $schemeId,
                'closure_type' => 'MATURITY',
                'paid_amount' => $paid,
                'bonus_amount' => $bonus,
                'deduction_amount' => 0,
                'payable_amount' => $payable,
                'payment_mode' => 'ADJUSTMENT',
                'status' => 'PAID',
                'approved_at' => $date ? $date.' 00:00:00' : null,
                'paid_at' => $date ? $date.' 00:00:00' : null,
                'notes' => 'Imported legacy finish record; type='.$this->text($row['ftype'] ?? '').'; net gold weight='.$this->number($row['netgwgt'] ?? null, 3),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('customer_schemes')->where('id', $enrollmentId)->update([
                'status' => 'MATURED',
                'closed_date' => $date,
                'end_date' => $date,
                'maturity_date' => $date,
                'bonus_amount' => $bonus,
                'bonus_gram' => $this->number($row['netgwgt'] ?? null, 3),
                'updated_at' => $now,
            ]);
        }
    }

    private function refreshCustomerGrams(): void
    {
        foreach ($this->customerIds as $customerId) {
            $gram = DB::table('customer_schemes')->where('customer_id', $customerId)->sum('total_gram');
            DB::table('customers')->where('id', $customerId)->update([
                'total_gram' => round((float) $gram, 3),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return \Generator<int, array<string, string|null>> */
    private function csv(string $path): \Generator
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '\\');
        $headers = null;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }
            if ($headers === null) {
                $headers = array_map(fn ($h) => trim((string) $h), $row);
                continue;
            }
            $row = array_pad($row, count($headers), null);
            yield array_combine($headers, array_slice($row, 0, count($headers)));
        }
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return rtrim($path, '\\/');
        }

        return rtrim(base_path($path), '\\/');
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function number(mixed $value, int $precision = 2): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }

        return round((float) $value, $precision);
    }

    private function positiveInt(mixed $value): int
    {
        return max(0, (int) ($value ?? 0));
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function usefulDate(mixed $value): ?string
    {
        $date = $this->date($value);

        return $date && $date > '1900-01-01' ? $date : null;
    }

    private function timestampDate(string $date): ?string
    {
        return $date >= '1970-01-01' && $date <= '2038-01-18' ? $date.' 00:00:00' : null;
    }

    private function schemeCode(mixed $value): string
    {
        return $this->text($value) ?: 'LEGACY';
    }

    private function baseInstallments(string $schemeCode): int
    {
        return $schemeCode === 'SW1' ? 365 : 180;
    }

    /** @param array<string, bool> $used */
    private function placeholderPhone(string $code, array $used): string
    {
        $stem = preg_replace('/[^A-Z0-9-]/', '', strtoupper($code));
        $candidate = 'LGCY-'.substr($stem ?: sha1($code), 0, 14);
        if (! isset($used[$candidate])) {
            return $candidate;
        }

        return 'LGCY-'.substr(sha1($code), 0, 14);
    }

    /** @param array<string, string|null> $row */
    private function legacyCollectionNote(array $row): string
    {
        $parts = ['Imported legacy collection '.$this->text($row['slno'] ?? '')];
        foreach (['rcptno' => 'receipt', 'docno' => 'document', 'note' => 'note', 'closed' => 'closed'] as $field => $label) {
            $value = $this->text($row[$field] ?? '');
            if ($value !== '') {
                $parts[] = "{$label}={$value}";
            }
        }

        return implode('; ', $parts);
    }
}
