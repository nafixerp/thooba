<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    protected $fillable = [
        'branch_id', 'scheme_id', 'name', 'basis', 'rate', 'effective_from',
        'effective_to', 'status',
    ];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date', 'status' => 'boolean'];
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function scheme(): BelongsTo { return $this->belongsTo(Scheme::class); }

    public function scopeActive($q) { return $q->where('status', true); }
}
