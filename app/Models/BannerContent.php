<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerContent extends Model
{
    protected $fillable = ['title', 'description', 'image', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('status', true);
    }
}
