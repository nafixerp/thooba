<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    protected $fillable = [
        'order_no', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'gateway_signature',
        'customer_id', 'customer_scheme_id', 'payment_id',
        'amount', 'currency', 'status', 'failure_reason', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function customerScheme(): BelongsTo { return $this->belongsTo(CustomerScheme::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }

    public static function nextOrderNo(): string
    {
        return 'ORD'.now()->format('ymd').str_pad((string) ((int) static::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }
}
