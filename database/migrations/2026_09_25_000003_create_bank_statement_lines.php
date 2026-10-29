<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Bank statement lines, for reconciling direct UPI collections
|--------------------------------------------------------------------------
|
| A direct UPI payment has no aggregator behind it, so the app's word that
| the money arrived is only a claim. The bank statement is the evidence.
|
| The shop exports the statement from net banking, uploads the CSV here, and
| every credit line is matched against the payments customers have claimed:
|
|   ORDER_NO     the narration carries our own order number, which the app
|                puts in the UPI `tr` field. The strongest match there is.
|   UTR          the narration or reference carries the UTR the customer
|                typed in.
|   AMOUNT_DATE  no reference to go on, but exactly one claimed payment has
|                that amount within a few days. Needs a human to agree.
|
| `fingerprint` is what stops a re-upload of an overlapping date range from
| creating the same line twice.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();

            $table->date('txn_date');
            $table->text('narration')->nullable();
            $table->string('ref_no', 80)->nullable();

            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('balance', 18, 2)->nullable();

            // date + amounts + narration, hashed. Re-importing an overlapping
            // range then updates the same row instead of duplicating it.
            $table->string('fingerprint', 64)->unique();

            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('match_type', ['ORDER_NO', 'UTR', 'AMOUNT_DATE', 'MANUAL'])->nullable();
            $table->enum('status', ['UNMATCHED', 'MATCHED', 'IGNORED'])->default('UNMATCHED');
            $table->string('note', 255)->nullable();

            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'txn_date']);
            $table->index('txn_date');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
