<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'key_id',
        'key_hash',
        'scopes',
        'rate_limits',
        'ip_whitelist',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'rate_limits' => 'array',
        'ip_whitelist' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key_hash',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public static function generateKey(): array
    {
        $keyId = 'mk_' . Str::random(28);
        $secret = Str::random(32);
        $fullKey = $keyId . '.' . $secret;
        
        return [
            'key_id' => $keyId,
            'key_hash' => hash('sha256', $fullKey),
            'full_key' => $fullKey,
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->ip_whitelist)) {
            return true;
        }

        return in_array($ip, $this->ip_whitelist);
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function getTodayUsageCount(): int
    {
        return $this->usageLogs()
            ->whereDate('created_at', today())
            ->count();
    }
}