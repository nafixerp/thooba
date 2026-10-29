<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privilege_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->decimal('min_sales', 14, 2)->default(0);
            $table->decimal('points_per_100', 10, 2)->default(0);
            $table->string('card_color', 20)->default('#B7791F');
            $table->text('benefits')->nullable();
            $table->integer('sortorder')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['status', 'min_sales']);
        });

        DB::table('privilege_tiers')->insert([
            ['name' => 'Bronze', 'min_sales' => 0, 'points_per_100' => 1, 'card_color' => '#B7791F', 'benefits' => '1 point for every Rs 100 collected', 'sortorder' => 1, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Silver', 'min_sales' => 50000, 'points_per_100' => 1.5, 'card_color' => '#718096', 'benefits' => '1.5 points for every Rs 100 collected', 'sortorder' => 2, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gold', 'min_sales' => 100000, 'points_per_100' => 2, 'card_color' => '#D69E2E', 'benefits' => '2 points for every Rs 100 collected', 'sortorder' => 3, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('dedupe_key', 190)->nullable()->unique()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn('dedupe_key');
        });

        Schema::dropIfExists('privilege_tiers');
    }
};
