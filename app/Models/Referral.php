<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = ['code', 'customer_id', 'referred_customer_id', 'points', 'referral_date'];

    protected $casts = ['referral_date' => 'date', 'points' => 'decimal:2'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function referred(): BelongsTo { return $this->belongsTo(Customer::class, 'referred_customer_id'); }
}
