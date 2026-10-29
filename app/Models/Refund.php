<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'refund_no', 'customer_id', 'customer_scheme_id', 'payment_id', 'amount',
        'refund_mode', 'gateway_refund_id', 'reason', 'status', 'requested_by_type',
        'requested_by_id', 'approved_by', 'approved_at', 'processed_at', 'admin_note',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'approved_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function customerScheme(): BelongsTo { return $this->belongsTo(CustomerScheme::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public static function nextNo(): string
    {
        return 'REF'.date('ymd').str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
