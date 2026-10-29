<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'code', 'name', 'manager_name', 'phone', 'email', 'address', 'city',
        'state', 'country', 'pincode', 'latitude', 'longitude', 'status',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'status' => 'boolean'];
    }

    public function customers(): HasMany { return $this->hasMany(Customer::class); }
    public function agents(): HasMany { return $this->hasMany(Agent::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }

    public function scopeActive($q) { return $q->where('status', true); }
}
