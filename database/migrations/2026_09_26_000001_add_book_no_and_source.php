<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Book number on an enrolment, and where a join came from.
 *
 * `book_no` is the number written on the customer's paper passbook. The shop
 * has been running on those books for years and staff quote them on the phone,
 * so the system proposes the next one and lets it be overwritten when the book
 * in hand says something else.
 *
 * `source` answers "did this come in through the app?". Until now a customer
 * created by the shop, by an agent on their round, and by someone who
 * downloaded the app looked identical in the database, so nobody could see
 * what the app was actually bringing in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_schemes', function (Blueprint $table) {
            // Not unique: two branches can legitimately run their own book
            // series, and old books are re-used once closed.
            $table->string('book_no', 30)->nullable()->after('account_no');
            $table->string('source', 10)->default('ADMIN')->after('status');
            $table->index('book_no');
            $table->index(['source', 'created_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('source', 10)->default('ADMIN')->after('status');
            $table->index(['source', 'created_at']);
        });

        // Existing rows predate the app, so ADMIN is right for them - except
        // enrolments that already carry an agent, which came off a round.
        DB::table('customer_schemes')->whereNotNull('agent_id')->update(['source' => 'AGENT']);
        DB::table('customers')->whereNotNull('agent_id')->update(['source' => 'AGENT']);
    }

    public function down(): void
    {
        Schema::table('customer_schemes', function (Blueprint $table) {
            $table->dropIndex(['book_no']);
            $table->dropIndex(['source', 'created_at']);
            $table->dropColumn(['book_no', 'source']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['source', 'created_at']);
            $table->dropColumn('source');
        });
    }
};
