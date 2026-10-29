<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Level 3 - Ledger Master (Chart of Accounts leaf)
 *
 *   accountm.accode  ──1:N──►  daybook.accode     (posted ledger)
 *   accountm.accode  ──1:N──►  daybook.opaccode   (counterparty ledger)
 */
class Accountm extends Model
{
    protected $table = 'accountm';

    protected $primaryKey = 'accode';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'accode', 'name', 'alias', 'grcode', 'bshead', 'actype',
        'tplpos', 'schedule_group', 'schedule_pos', 'print_in_reports',
        'opening_balance', 'opening_drcr', 'opening_date',
        'phone', 'email', 'address', 'gstin', 'pan',
        'bank_account_no', 'bank_ifsc',
        'customer_id', 'agent_id', 'credit_limit', 'is_system', 'status',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'opening_date' => 'date',
        'is_system' => 'boolean',
        'status' => 'boolean',
        'tplpos' => 'integer',
        'schedule_pos' => 'integer',
        'print_in_reports' => 'boolean',
    ];

    /* ---------------- Relations ---------------- */

    public function group(): BelongsTo
    {
        return $this->belongsTo(Accountg::class, 'grcode', 'grcode');
    }

    public function base()
    {
        return $this->group?->base();
    }

    /** Entries where this ledger is the posted account */
    public function entries(): HasMany
    {
        return $this->hasMany(Daybook::class, 'accode', 'accode');
    }

    /** Entries where this ledger is the counterparty */
    public function oppositeEntries(): HasMany
    {
        return $this->hasMany(Daybook::class, 'opaccode', 'accode');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    /* ---------------- Scopes ---------------- */

    public function scopeActive($q)
    {
        return $q->where('status', true);
    }

    public function scopeType($q, string|array $type)
    {
        return $q->whereIn('actype', (array) $type);
    }

    public function scopeBalanceSheet($q)
    {
        return $q->where('bshead', 'BS');
    }

    public function scopeProfitLoss($q)
    {
        return $q->where('bshead', 'PL');
    }

    /** Ledgers belonging to one section of the Trading / P&L statement. */
    public function scopeTradingSection($q, int $tplpos)
    {
        return $q->where('bshead', 'PL')->where('tplpos', $tplpos);
    }

    /* ---------------- Helpers ---------------- */

    /** Opening balance as a signed number (+ = Debit, - = Credit) */
    public function getSignedOpeningAttribute(): float
    {
        return (float) ($this->opening_drcr === 'C'
            ? -abs($this->opening_balance)
            : abs($this->opening_balance));
    }

    /**
     * Closing balance (signed) up to a date.
     * + = Debit balance, - = Credit balance
     */
    public function balanceAsOn(?string $date = null): float
    {
        $q = Daybook::query()
            ->where('accode', $this->accode)
            ->where('status', 'POSTED');

        if ($date) {
            $q->whereDate('voucher_date', '<=', $date);
        }

        return round($this->signed_opening + (float) $q->sum('amount'), 2);
    }

    public function getBalanceAttribute(): float
    {
        return $this->balanceAsOn();
    }

    public static function formatDrCr(float $signed): string
    {
        if (abs($signed) < 0.005) {
            return '0.00';
        }

        return number_format(abs($signed), 2).' '.($signed > 0 ? 'Dr' : 'Cr');
    }

    /** Next free ledger code inside a group */
    public static function nextCode(string $grcode): string
    {
        $prefix = preg_replace('/\D/', '', $grcode) ?: '9';
        $last = static::where('grcode', $grcode)->max('accode');

        if ($last && is_numeric($last)) {
            return (string) ((int) $last + 1);
        }

        return $prefix.'001';
    }
}
