<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $fillable = [
        'notifiable_type', 'notifiable_id', 'title', 'message', 'type', 'dedupe_key',
        'data', 'read_at', 'scheduled_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array', 'read_at' => 'datetime',
            'scheduled_at' => 'datetime', 'sent_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo { return $this->morphTo(); }
}
