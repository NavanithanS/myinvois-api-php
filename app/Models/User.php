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
        'name',
        'email',
        'password',
        'role',
        'company_name',
        'phone',
        'permissions',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDeveloper(): bool
    {
        return $this->role === 'developer';
    }

    public function isReadonly(): bool
    {
        return $this->role === 'readonly';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    public function getActiveApiKeysCount(): int
    {
        return $this->apiKeys()->where('is_active', true)->count();
    }

    public function getTodayUsageCount(): int
    {
        return $this->usageLogs()
            ->whereDate('created_at', today())
            ->count();
    }
}