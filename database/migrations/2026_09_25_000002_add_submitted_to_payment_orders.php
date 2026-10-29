<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| A state for "the customer says they paid, the office has not checked yet"
|--------------------------------------------------------------------------
|
| A direct UPI intent has no aggregator behind it, so nothing can prove the
| money arrived: the response the UPI app hands back is client side and
| trivially forged. Such an order lands in SUBMITTED with a PENDING payment
| against it, and only becomes real when the office approves it against the
| bank statement - the same gate an agent's cash collection goes through.
|
| A Razorpay order never touches this state; its signature and capture check
| take it straight to PAID.
*/

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_orders')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            // SQLite stores the enum as free text, so there is nothing to widen.
            return;
        }

        DB::statement(
            "ALTER TABLE `payment_orders`
             MODIFY `status` ENUM('CREATED','SUBMITTED','PAID','FAILED','CANCELLED')
             NOT NULL DEFAULT 'CREATED'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_orders') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('payment_orders')->where('status', 'SUBMITTED')->update(['status' => 'CREATED']);

        DB::statement(
            "ALTER TABLE `payment_orders`
             MODIFY `status` ENUM('CREATED','PAID','FAILED','CANCELLED')
             NOT NULL DEFAULT 'CREATED'"
        );
    }
};
