<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a bank statement the shop uploaded.
 *
 * The evidence a direct UPI payment is checked against - see
 * App\Services\BankReconciliationService.
 */
class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_id', 'txn_date', 'narration', 'ref_no',
        'debit', 'credit', 'balance', 'fingerprint',
        'payment_id', 'match_type', 'status', 'note', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** Money in - the only lines a customer payment can match. */
    public function scopeCredits($q)
    {
        return $q->where('credit', '>', 0);
    }

    public function scopeUnmatched($q)
    {
        return $q->where('status', 'UNMATCHED');
    }

    public function scopeMatched($q)
    {
        return $q->where('status', 'MATCHED');
    }

    /** Everything the narration might hide a reference in. */
    public function searchText(): string
    {
        return strtoupper(($this->narration ?? '').' '.($this->ref_no ?? ''));
    }

    /**
     * ORDER_NO and UTR are the bank quoting a reference only the real payment
     * could carry. AMOUNT_DATE is a guess that a human has to agree with.
     */
    public function isStrongMatch(): bool
    {
        return in_array($this->match_type, ['ORDER_NO', 'UTR'], true);
    }

    public static function fingerprintFor(
        string $date,
        float $debit,
        float $credit,
        ?string $narration,
        ?string $ref,
    ): string {
        return hash('sha256', implode('|', [
            $date,
            number_format($debit, 2, '.', ''),
            number_format($credit, 2, '.', ''),
            preg_replace('/\s+/', ' ', trim(strtoupper((string) $narration))),
            trim(strtoupper((string) $ref)),
        ]));
    }
}
