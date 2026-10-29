<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Put every existing P&L ledger into a Trading / P&L section
|--------------------------------------------------------------------------
|
| Direct income and direct cost trade, so they belong in the Trading A/c and
| close into Gross Profit. Everything else is an indirect item and belongs in
| the Profit & Loss A/c. Anyone can move a ledger afterwards from
| Accounts -> Ledgers.
|
| The same pass fills in the Sundry Debtor / Creditor flip pairs and the
| Balance Sheet schedule names.
*/

return new class extends Migration
{
    public function up(): void
    {
        // Direct income / direct expense -> Trading A/c
        $directGroups = DB::table('accountg')
            ->where('bshead', 'PL')
            ->where(function ($q) {
                $q->where('name', 'like', 'Direct%')
                    ->orWhere('grcode', 'G3010')
                    ->orWhere('grcode', 'G4010');
            })
            ->pluck('grcode');

        if ($directGroups->isNotEmpty()) {
            DB::table('accountm')
                ->where('bshead', 'PL')
                ->whereIn('grcode', $directGroups)
                ->update(['tplpos' => 1]);
        }

        // Everything else in the P&L stays at 0 (the default), which is the
        // Profit & Loss A/c section.

        // Sundry Debtors <-> Sundry Creditors, so a reversed balance is shown
        // on the side it actually belongs on.
        $pairs = [
            'G1050' => 'G2020',   // Sundry Debtors     -> Agents / Sundry Creditors
            'G2020' => 'G1050',   // and back
            'G2010' => 'G1050',   // Scheme Customers (deposits) -> Sundry Debtors
        ];

        foreach ($pairs as $grcode => $flip) {
            if (DB::table('accountg')->where('grcode', $grcode)->exists()
                && DB::table('accountg')->where('grcode', $flip)->exists()) {
                DB::table('accountg')->where('grcode', $grcode)->update(['flip_grcode' => $flip]);
            }
        }

        // Balance Sheet schedules default to the ledger's own group name.
        // One UPDATE per group keeps this working on SQLite too, which has no
        // multi table UPDATE.
        foreach (DB::table('accountg')->where('bshead', 'BS')->get(['grcode', 'name']) as $g) {
            DB::table('accountm')
                ->where('grcode', $g->grcode)
                ->where('bshead', 'BS')
                ->where(function ($q) {
                    $q->whereNull('schedule_group')->orWhere('schedule_group', '');
                })
                ->update(['schedule_group' => $g->name]);
        }
    }

    public function down(): void
    {
        DB::table('accountm')->update(['tplpos' => 0, 'schedule_group' => null]);
        DB::table('accountg')->update(['flip_grcode' => null]);
    }
};
