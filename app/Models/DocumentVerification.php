<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVerification extends Model
{
    protected $fillable = [
        'kyc_document_id', 'customer_id', 'status', 'verified_by', 'verified_at',
        'reference_no', 'remarks', 'meta',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'meta' => 'array'];
    }

    public function document(): BelongsTo { return $this->belongsTo(KycDocument::class, 'kyc_document_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
}
