<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Level 1 - Group Base  (Assets / Liabilities / Income / Expense)
 *
 * @property string $gbscode
 */
class Accountgbs extends Model
{
    protected $table = 'accountgbs';

    protected $primaryKey = 'gbscode';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gbscode', 'name', 'nature', 'bshead', 'normal_side', 'sortorder', 'expand', 'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'expand' => 'boolean',
        'sortorder' => 'integer',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(Accountg::class, 'gbscode', 'gbscode');
    }

    public function ledgers()
    {
        return $this->hasManyThrough(
            Accountm::class,
            Accountg::class,
            'gbscode',   // FK on accountg
            'grcode',    // FK on accountm
            'gbscode',   // local key on accountgbs
            'grcode'     // local key on accountg
        );
    }

    public function scopeActive($q)
    {
        return $q->where('status', true);
    }

    public function scopeBalanceSheet($q)
    {
        return $q->where('bshead', 'BS');
    }

    public function scopeProfitLoss($q)
    {
        return $q->where('bshead', 'PL');
    }
}
