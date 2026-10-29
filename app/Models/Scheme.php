<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scheme extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'metal_id', 'amount', 'min_amount', 'max_amount',
        'flexible_amount', 'payment_interval', 'total_installments', 'duration_months',
        'bonus_type', 'bonus_value', 'gst_percent', 'gram_based',
        'description', 'terms', 'image', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'bonus_value' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'flexible_amount' => 'boolean',
            'gram_based' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function metal(): BelongsTo { return $this->belongsTo(Metal::class); }
    public function enrollments(): HasMany { return $this->hasMany(CustomerScheme::class); }

    public function scopeActive($q) { return $q->where('status', true); }

    public function getMaturityValueAttribute(): float
    {
        $base = (float) $this->amount * $this->total_installments;

        return round(match ($this->bonus_type) {
            'PERCENT' => $base * (1 + $this->bonus_value / 100),
            'AMOUNT' => $base + (float) $this->bonus_value,
            'INSTALLMENT' => $base + ((float) $this->amount * $this->bonus_value),
            default => $base,
        }, 2);
    }
}
