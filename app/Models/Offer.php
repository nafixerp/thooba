<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    protected $fillable = [
        'product_id', 'title', 'description', 'offer_code', 'discount',
        'discount_type', 'start_date', 'end_date', 'image', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'discount' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function scopeActive($q)
    {
        return $q->where('status', true)
            ->where(fn ($w) => $w->whereNull('end_date')->orWhereDate('end_date', '>=', now()));
    }
}
