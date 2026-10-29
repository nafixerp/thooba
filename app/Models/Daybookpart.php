<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Voucher HEADER.
 *
 *   daybookpart (1) ──► daybook (N)
 */
class Daybookpart extends Model
{
    protected $table = 'daybookpart';

    protected $primaryKey = 'sno';

    protected $fillable = [
        'voucher_type', 'voucher_no', 'voucher_date', 'fy', 'total_amount',
        'reference', 'narration', 'branch', 'source_type', 'source_id',
        'status', 'is_opening', 'created_by', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'total_amount' => 'decimal:2',
        'is_opening' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(Daybook::class, 'sno', 'sno')->orderBy('line_no');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePosted($q)
    {
        return $q->where('status', 'POSTED');
    }

    public function scopeBetween($q, $from, $to)
    {
        return $q->whereBetween('voucher_date', [$from, $to]);
    }

    /** Debit total of the voucher */
    public function getDebitTotalAttribute(): float
    {
        return (float) $this->lines->sum('dr_amount');
    }

    public function getCreditTotalAttribute(): float
    {
        return (float) $this->lines->sum('cr_amount');
    }

    public function isBalanced(): bool
    {
        return abs($this->debit_total - $this->credit_total) < 0.005;
    }
}
