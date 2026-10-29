<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('manager_name', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('pincode', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80);
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('agents', function (Blueprint $table) {
            if (! Schema::hasColumn('agents', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });

        Schema::create('scheme_closures', function (Blueprint $table) {
            $table->id();
            $table->string('closure_no', 40)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained()->restrictOnDelete();
            $table->enum('closure_type', ['MATURITY', 'CANCELLATION', 'REDEMPTION', 'TRANSFER'])->default('MATURITY');
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->decimal('deduction_amount', 14, 2)->default(0);
            $table->decimal('payable_amount', 16, 2)->default(0);
            $table->string('purchase_invoice_no', 80)->nullable();
            $table->enum('payment_mode', ['CASH', 'UPI', 'BANK', 'CHEQUE', 'ADJUSTMENT'])->nullable();
            $table->enum('status', ['REQUESTED', 'APPROVED', 'REJECTED', 'PAID'])->default('REQUESTED');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_no', 40)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->enum('refund_mode', ['ORIGINAL', 'CASH', 'UPI', 'BANK', 'CARD'])->default('ORIGINAL');
            $table->string('gateway_refund_id', 100)->nullable()->index();
            $table->text('reason')->nullable();
            $table->enum('status', ['REQUESTED', 'APPROVED', 'REJECTED', 'PROCESSED', 'FAILED'])->default('REQUESTED');
            $table->string('requested_by_type', 40)->nullable();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->enum('basis', ['PERCENT', 'FLAT'])->default('PERCENT');
            $table->decimal('rate', 10, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->string('commission_no', 40)->unique();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('collected_amount', 16, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->date('earned_on');
            $table->enum('status', ['EARNED', 'APPROVED', 'PAID', 'CANCELLED'])->default('EARNED');
            $table->foreignId('agent_payout_id')->nullable()->constrained('agent_payouts')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 40)->unique();
            $table->nullableMorphs('owner');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 180);
            $table->enum('category', ['PAYMENT', 'SCHEME', 'KYC', 'APP', 'GENERAL'])->default('GENERAL');
            $table->enum('priority', ['LOW', 'NORMAL', 'HIGH', 'URGENT'])->default('NORMAL');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'WAITING_CUSTOMER', 'RESOLVED', 'CLOSED'])->default('OPEN');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('author');
            $table->text('message');
            $table->string('attachment')->nullable();
            $table->boolean('internal')->default(false);
            $table->timestamps();
        });

        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30)->default('razorpay');
            $table->string('event', 120)->nullable();
            $table->string('signature', 191)->nullable();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['RECEIVED', 'PROCESSED', 'REJECTED', 'FAILED'])->default('RECEIVED');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('entry_date');
            $table->enum('entry_type', ['PAYMENT', 'REFUND', 'BONUS', 'ADJUSTMENT', 'CLOSURE']);
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->decimal('balance', 16, 2)->default(0);
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'entry_date']);
        });

        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', ['AADHAAR', 'PAN', 'PASSPORT', 'VOTER_ID', 'DRIVING_LICENSE', 'OTHER']);
            $table->string('document_no', 80)->nullable();
            $table->string('front_file');
            $table->string('back_file')->nullable();
            $table->enum('status', ['PENDING', 'VERIFIED', 'REJECTED'])->default('PENDING');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['PENDING', 'VERIFIED', 'REJECTED'])->default('PENDING');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->enum('platform', ['ANDROID', 'IOS', 'WEB'])->default('ANDROID');
            $table->string('version', 30);
            $table->unsignedInteger('build_number')->default(1);
            $table->string('min_supported_version', 30)->nullable();
            $table->unsignedInteger('min_supported_build')->nullable();
            $table->boolean('force_update')->default(false);
            $table->boolean('maintenance_mode')->default(false);
            $table->string('store_url')->nullable();
            $table->text('release_notes')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['platform', 'status']);
        });

        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->default('TERMS');
            $table->string('version', 30)->nullable();
            // dateTime, not timestamp - see the otp_codes note: MySQL would
            // otherwise rewrite this on every update to the row.
            $table->dateTime('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('actor');
            $table->string('action', 120);
            $table->string('module', 80)->nullable();
            $table->nullableMorphs('auditable');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->nullableMorphs('notifiable');
                $table->string('title', 180);
                $table->text('message');
                $table->enum('type', ['REMINDER', 'MATURITY', 'GOLD_RATE', 'OFFER', 'BIRTHDAY', 'SYSTEM'])->default('SYSTEM');
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['agents', 'customers'] as $tableName) {
            if (Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('branch_id');
                });
            }
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropConstrainedForeignId('role_id');
            }
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });

        foreach ([
            'notifications', 'audit_logs', 'terms_acceptances', 'app_versions',
            'document_verifications', 'kyc_documents', 'customer_ledgers',
            'payment_webhook_logs', 'support_ticket_replies', 'support_tickets',
            'agent_commissions', 'commission_rules', 'refunds', 'scheme_closures',
            'permission_role', 'permissions', 'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::dropIfExists('branches');
    }
};
