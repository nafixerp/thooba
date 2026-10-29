<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'code', 'description', 'image',
        'weight', 'making_charge', 'price', 'status',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'making_charge' => 'decimal:2',
        'price' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function offers(): HasMany { return $this->hasMany(Offer::class); }

    public function scopeActive($q) { return $q->where('status', true); }
}
