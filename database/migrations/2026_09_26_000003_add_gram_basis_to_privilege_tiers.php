<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privilege_tiers', function (Blueprint $table) {
            $table->string('basis', 20)->default('AMOUNT')->after('name');
            $table->decimal('min_gram', 14, 3)->default(0)->after('min_sales');
            $table->decimal('points_per_gram', 10, 2)->default(0)->after('points_per_100');
        });
    }

    public function down(): void
    {
        Schema::table('privilege_tiers', function (Blueprint $table) {
            $table->dropColumn(['basis', 'min_gram', 'points_per_gram']);
        });
    }
};
