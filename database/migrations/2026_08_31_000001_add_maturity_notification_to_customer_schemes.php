<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_schemes', function (Blueprint $table) {
            $table->timestamp('maturity_notified_at')->nullable()->after('maturity_date');
            $table->text('maturity_notification_note')->nullable()->after('maturity_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_schemes', function (Blueprint $table) {
            $table->dropColumn(['maturity_notified_at', 'maturity_notification_note']);
        });
    }
};
