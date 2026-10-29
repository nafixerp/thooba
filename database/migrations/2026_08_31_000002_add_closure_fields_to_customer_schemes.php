<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_schemes', function (Blueprint $table) {
            $table->enum('closure_type', ['GOLD_REDEEM', 'REFUND', 'NO_PAYOUT'])->nullable()->after('closed_date');
            $table->decimal('closure_amount', 16, 2)->default(0)->after('closure_type');
            $table->string('closure_mode', 20)->nullable()->after('closure_amount');
            $table->unsignedBigInteger('closure_daybook_sno')->nullable()->after('closure_mode');
            $table->foreignId('renewed_from_id')->nullable()->after('closure_daybook_sno')->constrained('customer_schemes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_schemes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewed_from_id');
            $table->dropColumn(['closure_type', 'closure_amount', 'closure_mode', 'closure_daybook_sno']);
        });
    }
};
