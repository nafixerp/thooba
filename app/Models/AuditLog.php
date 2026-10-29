<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'module', 'auditable_type',
        'auditable_id', 'before', 'after', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function actor(): MorphTo { return $this->morphTo(); }
    public function auditable(): MorphTo { return $this->morphTo(); }
}
