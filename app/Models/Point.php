<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Point extends Model
{
    protected $fillable = ['customer_id', 'referral_id', 'balance_point', 'redeemed_point', 'total_point'];

    protected function casts(): array
    {
        return [
            'balance_point' => 'decimal:2',
            'redeemed_point' => 'decimal:2',
            'total_point' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
