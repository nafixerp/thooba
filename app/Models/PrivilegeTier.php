<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivilegeTier extends Model
{
    protected $fillable = [
        'name', 'basis', 'min_sales', 'min_gram', 'points_per_100', 'points_per_gram', 'card_color',
        'benefits', 'sortorder', 'status',
    ];

    protected function casts(): array
    {
        return [
            'min_sales' => 'decimal:2',
            'min_gram' => 'decimal:3',
            'points_per_100' => 'decimal:2',
            'points_per_gram' => 'decimal:2',
            'sortorder' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getBasisLabelAttribute(): string
    {
        return $this->basis === 'GRAM' ? 'Gold Gram' : 'Sales Amount';
    }

    public function getMinimumLabelAttribute(): string
    {
        return $this->basis === 'GRAM'
            ? number_format((float) $this->min_gram, 3).' g'
            : 'Rs '.number_format((float) $this->min_sales, 2);
    }

    public function getRewardRateLabelAttribute(): string
    {
        return $this->basis === 'GRAM'
            ? number_format((float) $this->points_per_gram, 2).' points / gram'
            : number_format((float) $this->points_per_100, 2).' points / Rs 100';
    }
}
