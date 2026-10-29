<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Metal extends Model
{
    protected $fillable = ['name', 'code', 'purity', 'unit', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function rates(): HasMany { return $this->hasMany(MetalRate::class); }

    public function scopeActive($q) { return $q->where('status', true); }

    public function currentRate(?string $date = null): float
    {
        return (float) ($this->rates()
            ->where('status', true)
            // whereDate, not a string compare: the cast stores
            // "2026-09-28 00:00:00", which sorts after "2026-09-28" and made
            // the app show yesterday's rate on the day it was set.
            ->whereDate('rate_date', '<=', $date ?: now()->toDateString())
            ->orderByDesc('rate_date')->orderByDesc('id')
            ->value('rate') ?? 0);
    }
}
