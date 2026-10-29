<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'platform', 'version', 'build_number', 'min_supported_version',
        'min_supported_build', 'force_update', 'maintenance_mode', 'store_url',
        'release_notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'build_number' => 'integer', 'min_supported_build' => 'integer',
            'force_update' => 'boolean', 'maintenance_mode' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function scopeActive($q) { return $q->where('status', true); }
}
