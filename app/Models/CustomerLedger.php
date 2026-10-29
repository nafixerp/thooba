<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedger extends Model
{
    protected $fillable = [
        'customer_id', 'customer_scheme_id', 'payment_id', 'entry_date',
        'entry_type', 'description', 'debit', 'credit', 'balance',
        'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2', 'balance' => 'decimal:2'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function customerScheme(): BelongsTo { return $this->belongsTo(CustomerScheme::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
