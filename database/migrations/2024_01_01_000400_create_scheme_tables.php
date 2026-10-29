<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- AGENT ---------------- */
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_code', 30)->unique();
            $table->string('name', 150);
            $table->string('phone', 20)->index();
            $table->string('email', 120)->nullable();
            $table->string('password')->nullable();
            $table->string('aadhaar', 20)->nullable();
            $table->string('pan', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('image')->nullable();
            $table->decimal('commission_percent', 6, 2)->default(0);
            $table->string('bank_name', 120)->nullable();
            $table->string('account_no', 40)->nullable();
            $table->string('ifsc', 20)->nullable();
            $table->string('accode', 15)->nullable();      // ledger in accountm
            $table->date('join_date')->nullable();
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        /* ---------------- CUSTOMER ---------------- */
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('reg_no', 30)->unique();        // Registration Number
            $table->string('name', 150);
            $table->string('phone', 20)->unique();
            $table->string('email', 120)->nullable();
            $table->string('password')->nullable();
            $table->string('photo')->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['MALE', 'FEMALE', 'OTHER'])->nullable();
            $table->text('address')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();

            $table->string('aadhaar', 20)->nullable();
            $table->string('pan', 20)->nullable();
            $table->string('kyc_doc')->nullable();
            $table->enum('kyc_status', ['PENDING', 'VERIFIED', 'REJECTED'])->default('PENDING');

            $table->string('nominee_name', 150)->nullable();
            $table->string('nominee_relation', 60)->nullable();
            $table->string('nominee_phone', 20)->nullable();

            $table->string('bank_name', 120)->nullable();
            $table->string('account_no', 40)->nullable();
            $table->string('ifsc', 20)->nullable();

            $table->string('referral_code', 30)->nullable()->unique();
            $table->string('referred_by', 30)->nullable();

            $table->decimal('wallet_amount', 16, 2)->default(0);
            $table->decimal('total_gram', 14, 3)->default(0);
            $table->string('accode', 15)->nullable();      // ledger in accountm
            $table->string('fcm_token')->nullable();
            $table->date('join_date')->nullable();
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        /* ---------------- SCHEME MASTER ---------------- */
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 30)->unique();
            $table->foreignId('metal_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2)->default(0);          // installment amount
            $table->decimal('min_amount', 14, 2)->default(0);
            $table->decimal('max_amount', 14, 2)->default(0);
            $table->boolean('flexible_amount')->default(false);
            $table->enum('payment_interval', ['DAILY', 'WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY'])->default('MONTHLY');
            $table->integer('total_installments')->default(11);
            $table->integer('duration_months')->default(11);
            $table->enum('bonus_type', ['NONE', 'PERCENT', 'AMOUNT', 'INSTALLMENT'])->default('NONE');
            $table->decimal('bonus_value', 12, 2)->default(0);
            $table->decimal('gst_percent', 6, 2)->default(3);
            $table->boolean('gram_based')->default(true);          // amount -> gram conversion
            $table->text('description')->nullable();
            $table->longText('terms')->nullable();
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        /* ---------------- CUSTOMER SCHEME (Enrollment) ---------------- */
        Schema::create('customer_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('account_no', 40)->unique();            // scheme account no
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('installment_amount', 14, 2);
            $table->integer('total_installments');
            $table->integer('paid_installments')->default(0);
            $table->enum('payment_interval', ['DAILY', 'WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY'])->default('MONTHLY');

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('maturity_date')->nullable();

            $table->decimal('total_paid', 16, 2)->default(0);
            $table->decimal('total_gst', 16, 2)->default(0);
            $table->decimal('total_gram', 14, 3)->default(0);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->decimal('bonus_gram', 14, 3)->default(0);

            $table->enum('status', ['ACTIVE', 'MATURED', 'CLOSED', 'CANCELLED', 'DEFAULTED'])->default('ACTIVE');
            $table->date('closed_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('status');
        });

        /* ---------------- INSTALLMENT SCHEDULE ---------------- */
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_scheme_id')->constrained()->cascadeOnDelete();
            $table->integer('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('gram', 14, 3)->default(0);
            $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->enum('status', ['PENDING', 'PAID', 'PARTIAL', 'OVERDUE'])->default('PENDING');
            $table->timestamps();

            $table->index(['customer_scheme_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        /* ---------------- PAYMENT ---------------- */
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 40)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 14, 2);            // base amount
            $table->decimal('tax', 12, 2)->default(0);   // GST
            $table->decimal('total', 14, 2);             // amount + tax
            $table->decimal('rate', 14, 2)->default(0);  // gold rate/gram on that day
            $table->decimal('gram', 14, 3)->default(0);  // amount / rate
            $table->decimal('wallet_amount', 14, 2)->default(0);

            $table->date('payment_date');
            $table->enum('payment_type', ['INSTALLMENT', 'DIRECT', 'ADVANCE', 'REDEEM', 'BONUS'])->default('INSTALLMENT');
            $table->enum('payment_mode', ['CASH', 'UPI', 'CARD', 'BANK', 'CHEQUE', 'WALLET'])->default('CASH');
            $table->string('txn_ref', 80)->nullable();
            $table->string('screenshot')->nullable();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('reject_reason')->nullable();

            // Accounting link
            $table->unsignedBigInteger('daybook_sno')->nullable();  // -> daybookpart.sno
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['payment_date', 'status']);
            $table->index('daybook_sno');
        });

        /* ---------------- AGENT PAYOUT (Commission) ---------------- */
        Schema::create('agent_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('collected_amount', 16, 2)->default(0);
            $table->decimal('amount', 14, 2);            // commission paid
            $table->date('received_date')->nullable();
            $table->enum('payment_mode', ['CASH', 'UPI', 'BANK', 'CHEQUE'])->default('BANK');
            $table->text('notes')->nullable();
            $table->enum('status', ['PENDING', 'PAID'])->default('PENDING');
            $table->unsignedBigInteger('daybook_sno')->nullable();
            $table->timestamps();
        });

        /* ---------------- POINTS / REFERRAL ---------------- */
        Schema::create('points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('referral_id', 30)->nullable();
            $table->decimal('balance_point', 14, 2)->default(0);
            $table->decimal('redeemed_point', 14, 2)->default(0);
            $table->decimal('total_point', 14, 2)->default(0);
            $table->timestamps();

            $table->unique('customer_id');
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['EARN', 'REDEEM', 'EXPIRE', 'ADJUST']);
            $table->decimal('points', 14, 2);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('gram', 14, 3)->default(0);
            $table->string('source', 40)->nullable();      // REFERRAL / PAYMENT / MANUAL
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('txn_date');
            $table->text('note')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('APPROVED');
            $table->unsignedBigInteger('daybook_sno')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();          // referrer
            $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->decimal('points', 12, 2)->default(0);
            $table->date('referral_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'referrals', 'point_transactions', 'points', 'agent_payouts', 'payments',
            'installments', 'customer_schemes', 'schemes', 'customers', 'agents',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
