<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'reg_no', 'branch_id', 'name', 'phone', 'email', 'password', 'must_change_password', 'source',
        'photo', 'dob', 'gender',
        'address', 'pincode', 'country_id', 'state_id', 'district_id', 'agent_id',
        'aadhaar', 'pan', 'kyc_doc', 'kyc_status',
        'nominee_name', 'nominee_relation', 'nominee_phone',
        'bank_name', 'account_no', 'ifsc',
        'referral_code', 'referred_by', 'wallet_amount', 'total_gram',
        'accode', 'fcm_token', 'join_date', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'join_date' => 'date',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'wallet_amount' => 'decimal:2',
            'total_gram' => 'decimal:3',
            'status' => 'boolean',
        ];
    }

    /* Relations */
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function country(): BelongsTo { return $this->belongsTo(Country::class); }
    public function state(): BelongsTo { return $this->belongsTo(State::class); }
    public function district(): BelongsTo { return $this->belongsTo(District::class); }
    public function ledger(): BelongsTo { return $this->belongsTo(Accountm::class, 'accode', 'accode'); }
    public function schemes(): HasMany { return $this->hasMany(CustomerScheme::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function point(): HasOne { return $this->hasOne(Point::class); }
    public function pointTransactions(): HasMany { return $this->hasMany(PointTransaction::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function kycDocuments(): HasMany { return $this->hasMany(KycDocument::class); }
    public function ledgerEntries(): HasMany { return $this->hasMany(CustomerLedger::class); }
    public function termsAcceptances(): HasMany { return $this->hasMany(TermsAcceptance::class); }

    /* Scopes */
    public function scopeActive($q) { return $q->where('status', true); }

    public function scopeSearch($q, ?string $term)
    {
        if (! $term) return $q;

        return $q->where(function ($w) use ($term) {
            $w->where('name', 'like', "%$term%")
              ->orWhere('reg_no', 'like', "%$term%")
              ->orWhere('phone', 'like', "%$term%")
              ->orWhere('email', 'like', "%$term%")
              // A customer ringing up quotes the number on their passbook,
              // not a reg no, so searching has to reach it.
              ->orWhereHas('schemes', fn ($s) => $s->where('book_no', 'like', "%$term%")
                  ->orWhere('account_no', 'like', "%$term%"));
        });
    }

    /* Helpers */
    public static function nextRegNo(): string
    {
        $prefix = \App\Models\Setting::get('customer_code_prefix', 'CUS');
        $last = (int) static::max('id');

        return $prefix.str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }

    public function getActiveSchemeCountAttribute(): int
    {
        return $this->schemes()->where('status', 'ACTIVE')->count();
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->schemes()->sum('total_paid');
    }
}
