<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_no', 'customer_id', 'customer_scheme_id', 'installment_id', 'agent_id',
        'amount', 'tax', 'total', 'rate', 'gram', 'wallet_amount',
        'payment_date', 'payment_type', 'payment_mode', 'txn_ref', 'screenshot',
        'bank_id', 'status', 'approved_by', 'approved_at', 'reject_reason',
        'daybook_sno', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'approved_at' => 'datetime',
            'amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'rate' => 'decimal:2',
            'gram' => 'decimal:3',
            'wallet_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function customerScheme(): BelongsTo { return $this->belongsTo(CustomerScheme::class); }
    public function installment(): BelongsTo { return $this->belongsTo(Installment::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function bank(): BelongsTo { return $this->belongsTo(Bank::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Daybookpart::class, 'daybook_sno', 'sno'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function refunds() { return $this->hasMany(Refund::class); }

    /**
     * The gateway orders that produced this payment. A direct UPI order carries
     * the reference the bank narration is matched against.
     */
    public function paymentOrders() { return $this->hasMany(PaymentOrder::class); }

    /** The bank statement line that proved this payment arrived, if any. */
    public function statementLine() { return $this->hasOne(BankStatementLine::class); }

    public function scopeApproved($q) { return $q->where('status', 'APPROVED'); }
    public function scopePending($q) { return $q->where('status', 'PENDING'); }

    public static function nextReceiptNo(): string
    {
        $prefix = \App\Models\Setting::get('receipt_code_prefix', 'RCT');

        return $prefix.date('ymd').str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
