<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| CHART OF ACCOUNTS  (3 level hierarchy)
|--------------------------------------------------------------------------
|
|   accountgbs   -> Group Base  (Assets / Liabilities / Income / Expense)
|        |
|        v
|   accountg     -> Account Group (Current Assets, Sundry Debtors, ...)
|        |
|        v
|   accountm     -> Ledger Master (Cash, SBI Bank, Customer A, ...)
|        |
|        v
|   daybook      -> Ledger entries (double entry)
|
*/

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- LEVEL 1 : accountgbs ---------------- */
        Schema::create('accountgbs', function (Blueprint $table) {
            $table->string('gbscode', 10)->primary();      // PK
            $table->string('name', 100);
            // ASSET / LIABILITY / INCOME / EXPENSE
            $table->enum('nature', ['ASSET', 'LIABILITY', 'INCOME', 'EXPENSE']);
            // BS = Balance Sheet, PL = Profit & Loss
            $table->enum('bshead', ['BS', 'PL']);
            // Normal balance side of this base group
            $table->enum('normal_side', ['D', 'C']);
            $table->integer('sortorder')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['bshead', 'nature']);
        });

        /* ---------------- LEVEL 2 : accountg ---------------- */
        Schema::create('accountg', function (Blueprint $table) {
            $table->string('grcode', 10)->primary();       // PK
            $table->string('name', 120);
            $table->string('gbscode', 10);                 // FK -> accountgbs
            $table->string('parent_grcode', 10)->nullable(); // self reference (sub group)
            $table->enum('bshead', ['BS', 'PL']);
            // CONTROL = system group (cannot delete), NORMAL = user group
            $table->enum('actype', ['CONTROL', 'NORMAL'])->default('NORMAL');
            $table->integer('sortorder')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('gbscode')->references('gbscode')->on('accountgbs')->cascadeOnUpdate();
            $table->foreign('parent_grcode')->references('grcode')->on('accountg')->nullOnDelete();
            $table->index('gbscode');
        });

        /* ---------------- LEVEL 3 : accountm (Ledger) ---------------- */
        Schema::create('accountm', function (Blueprint $table) {
            $table->string('accode', 15)->primary();       // PK  <-- daybook FK points here
            $table->string('name', 150);
            $table->string('alias', 100)->nullable();
            $table->string('grcode', 10);                  // FK -> accountg
            $table->enum('bshead', ['BS', 'PL']);
            // Nature of the ledger for auto behaviour
            $table->enum('actype', [
                'CASH', 'BANK', 'CUSTOMER', 'AGENT', 'SUPPLIER',
                'INCOME', 'EXPENSE', 'CAPITAL', 'DUTY', 'STOCK', 'GENERAL',
            ])->default('GENERAL');

            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->enum('opening_drcr', ['D', 'C'])->default('D');
            $table->date('opening_date')->nullable();

            // Party details
            $table->string('phone', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 20)->nullable();
            $table->string('bank_account_no', 40)->nullable();
            $table->string('bank_ifsc', 20)->nullable();

            // Link back to app entities
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();

            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->boolean('is_system')->default(false);   // system ledger - delete cheyyan pattilla
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('grcode')->references('grcode')->on('accountg')->cascadeOnUpdate();
            $table->index(['grcode', 'status']);
            $table->index('actype');
            $table->index('customer_id');
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accountm');
        Schema::dropIfExists('accountg');
        Schema::dropIfExists('accountgbs');
    }
};
