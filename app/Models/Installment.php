<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    protected $fillable = [
        'customer_scheme_id', 'installment_no', 'due_date', 'amount',
        'paid_amount', 'gram', 'paid_date', 'payment_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'gram' => 'decimal:3',
        ];
    }

    public function customerScheme(): BelongsTo { return $this->belongsTo(CustomerScheme::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }

    public function scopePending($q) { return $q->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE']); }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'PAID' && $this->due_date->isPast();
    }
}
