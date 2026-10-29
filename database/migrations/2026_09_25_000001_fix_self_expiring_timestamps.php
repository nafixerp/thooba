<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Stop MySQL rewriting these columns on every UPDATE
|--------------------------------------------------------------------------
|
| `$table->timestamp('expires_at')` looks harmless, but MySQL and MariaDB
| running with the default `explicit_defaults_for_timestamp = OFF` silently
| give the FIRST non-nullable TIMESTAMP column in a table
|
|     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
|
| so ANY update to the row rewrites it to now().
|
| What that did in practice:
|
|   otp_codes.expires_at
|       OtpService::verify() calls $otp->increment('attempts') when the code
|       is wrong. That UPDATE reset expires_at to now(), so the OTP died on
|       the first mistyped digit even though max_attempts is 5. The customer
|       then got "OTP expired or not requested" for the code sitting in their
|       SMS inbox.
|
|   terms_acceptances.accepted_at
|       Same shape: any later update to the row would rewrite the date the
|       customer accepted the terms.
|
| DATETIME never gets that implicit rule, has a wider range than TIMESTAMP,
| and behaves the same on SQLite - which is what the test suite runs on.
*/

return new class extends Migration
{
    /** table => column */
    protected array $columns = [
        'otp_codes' => 'expires_at',
        'terms_acceptances' => 'accepted_at',
    ];

    public function up(): void
    {
        // SQLite has no such behaviour and no MODIFY COLUMN; nothing to fix.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DATETIME NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NOT NULL");
        }
    }
};
