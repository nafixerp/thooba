<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemeClosure extends Model
{
    protected $fillable = [
        'closure_no', 'customer_id', 'customer_scheme_id', 'scheme_id', 'closure_type',
        'paid_amount', 'bonus_amount', 'deduction_amount', 'payable_amount',
        'purchase_invoice_no', 'payment_mode', 'status', 'approved_by', 'approved_at',
        'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_amount' => 'decimal:2', 'bonus_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2', 'payable_amount' => 'decimal:2',
            'approved_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function customerScheme(): BelongsTo { return $this->belongsTo(CustomerScheme::class); }
    public function scheme(): BelongsTo { return $this->belongsTo(Scheme::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public static function nextNo(): string
    {
        return 'CLS'.date('ymd').str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
