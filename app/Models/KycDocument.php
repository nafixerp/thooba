<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycDocument extends Model
{
    protected $fillable = [
        'customer_id', 'document_type', 'document_no', 'front_file', 'back_file',
        'status', 'verified_by', 'verified_at', 'remarks',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function verifications(): HasMany { return $this->hasMany(DocumentVerification::class); }
}
