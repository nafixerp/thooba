<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app support: OTP login, agent app login, payment gateway orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Default password is the phone number; force a change on first login.
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->unique()->after('email');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->string('fcm_token')->nullable()->after('accode');
        });

        /* ---------------- OTP CODES ---------------- */
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');                 // never store the plain OTP
            $table->enum('purpose', ['LOGIN', 'REGISTER', 'RESET'])->default('LOGIN');
            $table->unsignedTinyInteger('attempts')->default(0);
            // dateTime, not timestamp: MySQL gives the first non-nullable
            // TIMESTAMP column ON UPDATE CURRENT_TIMESTAMP, which reset the
            // expiry every time a wrong attempt was counted.
            $table->dateTime('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'expires_at']);
        });

        /* ---------------- PAYMENT GATEWAY ORDERS ---------------- */
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->string('gateway', 30)->default('razorpay');
            $table->string('gateway_order_id', 100)->nullable()->index();
            $table->string('gateway_payment_id', 100)->nullable()->index();
            $table->string('gateway_signature', 191)->nullable();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('currency', 5)->default('INR');
            // SUBMITTED: a direct UPI intent the customer says they paid,
            // waiting for the office to confirm it against the bank.
            $table->enum('status', ['CREATED', 'SUBMITTED', 'PAID', 'FAILED', 'CANCELLED'])->default('CREATED');
            $table->text('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('otp_codes');

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['username', 'must_change_password', 'fcm_token']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
