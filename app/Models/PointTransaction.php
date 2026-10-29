<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    protected $fillable = [
        'customer_id', 'type', 'points', 'amount', 'gram', 'source', 'source_id',
        'txn_date', 'note', 'status', 'daybook_sno',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'points' => 'decimal:2',
            'amount' => 'decimal:2',
            'gram' => 'decimal:3',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
