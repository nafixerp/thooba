<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomMessage extends Model
{
    protected $fillable = ['title', 'message', 'channel', 'target', 'target_id', 'sent_at', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('status', true);
    }
}
