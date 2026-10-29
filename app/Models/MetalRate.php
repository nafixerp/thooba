<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetalRate extends Model
{
    protected $fillable = ['metal_id', 'rate', 'rate_date', 'rate_time', 'status', 'created_by'];

    protected $casts = ['rate_date' => 'date', 'rate' => 'decimal:2', 'status' => 'boolean'];

    public function metal(): BelongsTo { return $this->belongsTo(Metal::class); }

    /** Latest rate for a metal (default: first active metal) */
    public static function latest_rate(?int $metalId = null, ?string $date = null): float
    {
        return (float) (static::query()
            ->when($metalId, fn ($q) => $q->where('metal_id', $metalId))
            ->where('status', true)
            ->whereDate('rate_date', '<=', $date ?: now()->toDateString())
            ->orderByDesc('rate_date')->orderByDesc('id')
            ->value('rate') ?? 0);
    }
}
