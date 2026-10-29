<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- LOCATION ---------------- */
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();
            $table->string('name', 100);
            $table->string('phone_code', 8)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 10)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        /* ---------------- METAL & RATE ---------------- */
        Schema::create('metals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);              // Gold 22K, Silver ...
            $table->string('code', 20)->unique();
            $table->string('purity', 20)->nullable();  // 916, 999
            $table->string('unit', 10)->default('GRAM');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 14, 2);           // per gram
            $table->date('rate_date');
            $table->time('rate_time')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['metal_id', 'rate_date']);
        });

        /* ---------------- PRODUCT ---------------- */
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('image')->nullable();
            $table->integer('sortorder')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('making_charge', 12, 2)->default(0);
            $table->decimal('price', 14, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('offer_code', 40)->nullable();
            $table->decimal('discount', 10, 2)->default(0);
            $table->enum('discount_type', ['PERCENT', 'AMOUNT'])->default('PERCENT');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        /* ---------------- CONTENT ---------------- */
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('title', 150)->nullable();
            $table->string('link')->nullable();
            $table->integer('sortorder')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('banner_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->longText('description')->nullable();
            $table->enum('type', ['TERMS', 'PRIVACY', 'REFUND', 'FAQ'])->default('TERMS');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('abouts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->longText('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('designation', 120)->nullable();
            $table->string('photo')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->integer('sortorder')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('custom_messages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('message');
            $table->enum('channel', ['PUSH', 'SMS', 'EMAIL', 'WHATSAPP'])->default('PUSH');
            $table->enum('target', ['ALL', 'CUSTOMER', 'AGENT', 'SINGLE'])->default('ALL');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        /* ---------------- BANK ---------------- */
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 120);
            $table->string('beneficiary_name', 120);
            $table->string('ifsc', 20);
            $table->string('account_no', 40);
            $table->string('branch', 120)->nullable();
            $table->string('upi_id', 80)->nullable();
            $table->string('qr_image')->nullable();
            $table->string('accode', 15)->nullable();     // linked ledger
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        /* ---------------- SETTINGS ---------------- */
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->string('group', 40)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'settings', 'banks', 'custom_messages', 'teams', 'abouts', 'terms',
            'banner_contents', 'banners', 'offers', 'products', 'categories',
            'metal_rates', 'metals', 'districts', 'states', 'countries',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
