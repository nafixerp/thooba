<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agents')
            ->select(['id', 'phone', 'password'])
            ->orderBy('id')
            ->chunkById(100, function ($agents) {
                foreach ($agents as $agent) {
                    $usesDemoPassword = filled($agent->password)
                        && Hash::check('agent@123', $agent->password);
                    $usesMobilePassword = filled($agent->password)
                        && Hash::check((string) $agent->phone, $agent->password);

                    if (blank($agent->password) || $usesDemoPassword) {
                        DB::table('agents')->where('id', $agent->id)->update([
                            'password' => Hash::make((string) $agent->phone),
                            'must_change_password' => true,
                            'updated_at' => now(),
                        ]);
                    } elseif ($usesMobilePassword) {
                        DB::table('agents')->where('id', $agent->id)->update([
                            'must_change_password' => true,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Password changes are intentionally not reversible.
    }
};
