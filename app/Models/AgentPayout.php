<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPayout extends Model
{
    protected $fillable = [
        'agent_id', 'from_date', 'to_date', 'collected_amount', 'amount',
        'received_date', 'payment_mode', 'notes', 'status', 'daybook_sno',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'received_date' => 'date',
            'amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Daybookpart::class, 'daybook_sno', 'sno'); }
}
