<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Level 2 - Account Group (Current Assets, Sundry Debtors, Indirect Expenses ...)
 */
class Accountg extends Model
{
    protected $table = 'accountg';

    protected $primaryKey = 'grcode';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'grcode', 'name', 'gbscode', 'parent_grcode', 'flip_grcode', 'bshead', 'actype', 'sortorder', 'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sortorder' => 'integer',
    ];

    public function base(): BelongsTo
    {
        return $this->belongsTo(Accountgbs::class, 'gbscode', 'gbscode');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_grcode', 'grcode');
    }

    /**
     * The group a reversed balance is reported under - a Sundry Debtor that
     * ends up in credit belongs with the Sundry Creditors and the other way
     * round. Legacy "SunDbCrAutoCorrect".
     */
    public function flipGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'flip_grcode', 'grcode');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_grcode', 'grcode');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Accountm::class, 'grcode', 'grcode');
    }

    public function scopeActive($q)
    {
        return $q->where('status', true);
    }

    /** Group + all its descendant group codes */
    public function descendantCodes(): array
    {
        $codes = [$this->grcode];
        foreach ($this->children as $child) {
            $codes = array_merge($codes, $child->descendantCodes());
        }

        return $codes;
    }
}
