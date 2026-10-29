<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TRADING / MANUFACTURING ACCOUNT STRUCTURE
|--------------------------------------------------------------------------
|
| The legacy "dua soft" (PowerBuilder/kuri) books do not have a single flat
| Profit & Loss. They print one vertical statement made of up to three T
| accounts:
|
|     accountm.tplpos = 1   Trading A/c I      -> Gross Profit c/d  (GPROFITN)
|     accountm.tplpos = 2   Trading A/c II     -> Gross Profit c/d  (GPROFITT)
|     accountm.tplpos = 0   Profit & Loss A/c  <- Gross Profit b/d  (GPROFIT)
|                                              -> Net Profit        (PROFIT)
|
| Two trading sections exist because the old KGST/VAT books kept taxable and
| non-taxable goods apart. tplpos carries that classification per ledger.
|
| schedule_group / schedule_pos drive the Schedules to the Balance Sheet
| (legacy accountm.shedgrp / shepos), and accountg.flip_grcode implements the
| SunDbCrAutoCorrect switch: a Sundry Debtor sitting on a credit balance is
| reported under Sundry Creditors, and the other way round.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accountm', function (Blueprint $table) {
            // 0 = Profit & Loss A/c, 1 = Trading A/c I, 2 = Trading A/c II
            $table->unsignedTinyInteger('tplpos')->default(0)->after('bshead');
            // Schedules to the Balance Sheet
            $table->string('schedule_group', 60)->nullable()->after('tplpos');
            $table->integer('schedule_pos')->default(0)->after('schedule_group');
            // Legacy accountm.prnt - keep a ledger out of the printed statements
            $table->boolean('print_in_reports')->default(true)->after('schedule_pos');

            $table->index(['bshead', 'tplpos']);
        });

        Schema::table('accountg', function (Blueprint $table) {
            // Opposite group to report this group's reversed balances under.
            $table->string('flip_grcode', 10)->nullable()->after('parent_grcode');
        });

        Schema::table('accountgbs', function (Blueprint $table) {
            // Schedule printed expanded (every ledger) or collapsed (one line).
            $table->boolean('expand')->default(true)->after('sortorder');
        });

        /*
         | Closing stock value per date.
         |
         | dua soft derives this from its inventory tables (items/salesd/
         | purchased). This project has no inventory module, so the figure is
         | entered for the period - the legacy "Manualstkvalue" mode - and the
         | Trading A/c picks up the latest value on or before the end date.
         */
        Schema::create('stock_values', function (Blueprint $table) {
            $table->id();
            $table->date('as_on')->unique();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('gram', 18, 3)->default(0);
            $table->string('note', 190)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('as_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_values');

        Schema::table('accountgbs', function (Blueprint $table) {
            $table->dropColumn('expand');
        });

        Schema::table('accountg', function (Blueprint $table) {
            $table->dropColumn('flip_grcode');
        });

        Schema::table('accountm', function (Blueprint $table) {
            $table->dropIndex(['bshead', 'tplpos']);
            $table->dropColumn(['tplpos', 'schedule_group', 'schedule_pos', 'print_in_reports']);
        });
    }
};
