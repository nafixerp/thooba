<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'branch_id', 'role_id', 'name', 'username', 'email', 'password', 'phone', 'photo', 'role', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['SUPERADMIN', 'ADMIN']);
    }

    public function canAccounts(): bool
    {
        return in_array($this->role, ['SUPERADMIN', 'ADMIN', 'ACCOUNTANT']);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function accessRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
