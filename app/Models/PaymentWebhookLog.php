<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookLog extends Model
{
    protected $fillable = [
        'gateway', 'event', 'signature', 'payment_order_id', 'payment_id',
        'status', 'payload', 'error', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(PaymentOrder::class, 'payment_order_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
