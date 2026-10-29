<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Agent extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'agent_code', 'branch_id', 'name', 'phone', 'email', 'username', 'password',
        'must_change_password', 'aadhaar', 'pan',
        'address', 'image', 'commission_percent', 'bank_name', 'account_no',
        'ifsc', 'accode', 'fcm_token', 'join_date', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'commission_percent' => 'decimal:2',
            'status' => 'boolean',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function customers(): HasMany { return $this->hasMany(Customer::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customerSchemes(): HasMany { return $this->hasMany(CustomerScheme::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function payouts(): HasMany { return $this->hasMany(AgentPayout::class); }
    public function commissions(): HasMany { return $this->hasMany(AgentCommission::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function ledger(): BelongsTo { return $this->belongsTo(Accountm::class, 'accode', 'accode'); }

    public function scopeActive($q) { return $q->where('status', true); }

    public static function nextCode(): string
    {
        return 'AGT'.str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    /** Total approved collection in a period */
    public function collection($from = null, $to = null): float
    {
        return (float) $this->payments()
            ->where('status', 'APPROVED')
            ->when($from && $to, fn ($q) => $q->whereBetween('payment_date', [$from, $to]))
            ->sum('total');
    }

    public function pendingCommission(): float
    {
        $earned = $this->collection() * ($this->commission_percent / 100);
        $paid = (float) $this->payouts()->where('status', 'PAID')->sum('amount');

        return round($earned - $paid, 2);
    }
}
