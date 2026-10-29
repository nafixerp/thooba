<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bank extends Model
{
    protected $fillable = [
        'bank_name', 'beneficiary_name', 'ifsc', 'account_no',
        'branch', 'upi_id', 'qr_image', 'accode', 'status',
    ];

    protected $casts = ['status' => 'boolean'];

    public function ledger(): BelongsTo { return $this->belongsTo(Accountm::class, 'accode', 'accode'); }

    public function scopeActive($q) { return $q->where('status', true); }
}
