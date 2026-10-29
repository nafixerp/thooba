<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger entry / voucher line.
 *
 *  amount > 0  => DEBIT
 *  amount < 0  => CREDIT
 *  SUM(amount) per voucher = 0
 */
class Daybook extends Model
{
    protected $table = 'daybook';

    protected $primaryKey = 'slno';

    protected $fillable = [
        'sno', 'line_no', 'accode', 'opaccode', 'amount', 'drcr',
        'dr_amount', 'cr_amount', 'voucher_type', 'voucher_no', 'voucher_date',
        'fy', 'remarks', 'narration', 'ref_no', 'branch', 'status',
        'is_opening', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'dr_amount' => 'decimal:2',
        'cr_amount' => 'decimal:2',
        'voucher_date' => 'date',
        'is_opening' => 'boolean',
    ];

    /* ---------------- Relations ---------------- */

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Daybookpart::class, 'sno', 'sno');
    }

    /** The ledger this line is posted to */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Accountm::class, 'accode', 'accode');
    }

    /** The counterparty / opposite ledger */
    public function oppositeAccount(): BelongsTo
    {
        return $this->belongsTo(Accountm::class, 'opaccode', 'accode');
    }

    /* ---------------- Scopes ---------------- */

    public function scopePosted($q)
    {
        return $q->where($this->qualifyColumn('status'), 'POSTED');
    }

    public function scopeForAccount($q, string $accode)
    {
        return $q->where($this->qualifyColumn('accode'), $accode);
    }

    public function scopeBetween($q, $from, $to)
    {
        return $q->whereBetween($this->qualifyColumn('voucher_date'), [$from, $to]);
    }

    public function scopeUpto($q, $date)
    {
        return $q->where($this->qualifyColumn('voucher_date'), '<=', $date);
    }

    /* ---------------- Helpers ---------------- */

    public function getIsDebitAttribute(): bool
    {
        return $this->drcr === 'D';
    }

    /**
     * Build a line array from a signed amount.
     * Use this so dr_amount / cr_amount / drcr never go out of sync.
     */
    public static function makeLine(string $accode, float $signedAmount, array $extra = []): array
    {
        $amount = round($signedAmount, 2);

        return array_merge([
            'accode' => $accode,
            'amount' => $amount,
            'drcr' => $amount >= 0 ? 'D' : 'C',
            'dr_amount' => $amount > 0 ? $amount : 0,
            'cr_amount' => $amount < 0 ? abs($amount) : 0,
        ], $extra);
    }
}
