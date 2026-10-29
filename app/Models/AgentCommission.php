<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    protected $fillable = [
        'commission_no', 'agent_id', 'payment_id', 'customer_scheme_id',
        'commission_rule_id', 'collected_amount', 'commission_amount', 'earned_on',
        'status', 'agent_payout_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'collected_amount' => 'decimal:2', 'commission_amount' => 'decimal:2',
            'earned_on' => 'date', 'paid_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function customerScheme(): BelongsTo { return $this->belongsTo(CustomerScheme::class); }
    public function rule(): BelongsTo { return $this->belongsTo(CommissionRule::class, 'commission_rule_id'); }
    public function payout(): BelongsTo { return $this->belongsTo(AgentPayout::class, 'agent_payout_id'); }

    public static function nextNo(): string
    {
        return 'COM'.date('ymd').str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
