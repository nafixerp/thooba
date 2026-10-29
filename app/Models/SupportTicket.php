<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_no', 'owner_type', 'owner_id', 'customer_id', 'agent_id', 'subject',
        'category', 'priority', 'status', 'assigned_to', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function owner(): MorphTo { return $this->morphTo(); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function replies(): HasMany { return $this->hasMany(SupportTicketReply::class); }

    public static function nextNo(): string
    {
        return 'TKT'.date('ymd').str_pad((string) ((int) static::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
