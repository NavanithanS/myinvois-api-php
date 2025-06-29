<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'client_id',
        'client_secret_hash',
        'redirect_uris',
        'scopes',
        'grant_type',
        'is_active',
        'environment',
        'rate_limits',
        'last_used_at',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'scopes' => 'array',
        'rate_limits' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret_hash',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    public static function generateCredentials(): array
    {
        $clientId = 'app_' . Str::random(28);
        $clientSecret = Str::random(40);
        
        return [
            'client_id' => $clientId,
            'client_secret_hash' => hash('sha256', $clientSecret),
            'client_secret' => $clientSecret,
        ];
    }

    public function verifySecret(string $secret): bool
    {
        return hash('sha256', $secret) === $this->client_secret_hash;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}