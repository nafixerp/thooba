<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| VOUCHER TABLES
|--------------------------------------------------------------------------
|
|   daybookpart  -> Voucher HEADER  (sno = voucher serial)
|        |
|        v  1 : N
|   daybook      -> Voucher LINES / Ledger entries
|                   accode   = ledger being posted
|                   opaccode = opposite (counterparty) ledger
|
|  SIGN CONVENTION
|  ---------------
|   amount > 0  => DEBIT   (drcr = 'D')
|   amount < 0  => CREDIT  (drcr = 'C')
|   SUM(amount) of one voucher MUST be 0.00
|
*/

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- VOUCHER HEADER ---------------- */
        Schema::create('daybookpart', function (Blueprint $table) {
            $table->bigIncrements('sno');                    // PK - voucher serial
            $table->string('voucher_type', 20);              // RECEIPT / PAYMENT / JOURNAL / CONTRA / SALES ...
            $table->string('voucher_no', 30);                // RCP/2026/000123
            $table->date('voucher_date');
            $table->string('fy', 9)->nullable();             // 2026-2027
            $table->decimal('total_amount', 18, 2)->default(0);   // total debit of the voucher
            $table->string('reference', 60)->nullable();
            $table->text('narration')->nullable();
            $table->string('branch', 30)->nullable();

            // Source document link (polymorphic-lite)
            $table->string('source_type', 40)->nullable();   // payment / agent_payout / redeem / manual
            $table->unsignedBigInteger('source_id')->nullable();

            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('POSTED');
            $table->boolean('is_opening')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['voucher_type', 'voucher_no']);
            $table->index(['voucher_date', 'voucher_type']);
            $table->index(['source_type', 'source_id']);
            $table->index('status');
        });

        /* ---------------- VOUCHER LINES / LEDGER ---------------- */
        Schema::create('daybook', function (Blueprint $table) {
            $table->bigIncrements('slno');                   // PK - line serial
            $table->unsignedBigInteger('sno');               // FK -> daybookpart.sno
            $table->integer('line_no')->default(1);

            $table->string('accode', 15);                    // FK -> accountm.accode  (ledger posted)
            $table->string('opaccode', 15)->nullable();      // FK -> accountm.accode  (counterparty)

            // signed amount : +ve = Debit, -ve = Credit
            $table->decimal('amount', 18, 2)->default(0);
            $table->enum('drcr', ['D', 'C']);
            // Convenience columns (always positive) for report readability
            $table->decimal('dr_amount', 18, 2)->default(0);
            $table->decimal('cr_amount', 18, 2)->default(0);

            // Denormalised for fast ledger reports (no join needed)
            $table->string('voucher_type', 20);
            $table->string('voucher_no', 30);
            $table->date('voucher_date');
            $table->string('fy', 9)->nullable();

            $table->string('remarks', 255)->nullable();
            $table->text('narration')->nullable();
            $table->string('ref_no', 60)->nullable();
            $table->string('branch', 30)->nullable();

            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('POSTED');
            $table->boolean('is_opening')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('sno')->references('sno')->on('daybookpart')->cascadeOnDelete();
            $table->foreign('accode')->references('accode')->on('accountm')->cascadeOnUpdate();
            $table->foreign('opaccode')->references('accode')->on('accountm')->cascadeOnUpdate();

            // Ledger / Trial balance queries ee index use cheyyum
            $table->index(['accode', 'voucher_date', 'status']);
            $table->index(['opaccode', 'voucher_date']);
            $table->index(['voucher_date', 'status']);
            $table->index('sno');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daybook');
        Schema::dropIfExists('daybookpart');
    }
};
