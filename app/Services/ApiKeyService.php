<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ApiKeyService
{
    public function createApiKey(User $user, array $data): array
    {
        $keyData = ApiKey::generateKey();
        
        $apiKey = ApiKey::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'key_id' => $keyData['key_id'],
            'key_hash' => $keyData['key_hash'],
            'scopes' => $data['scopes'] ?? [],
            'rate_limits' => $data['rate_limits'] ?? null,
            'ip_whitelist' => $data['ip_whitelist'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return [
            'api_key' => $apiKey,
            'full_key' => $keyData['full_key'], // Only returned once
        ];
    }

    public function revokeApiKey(ApiKey $apiKey): bool
    {
        return $apiKey->update(['is_active' => false]);
    }

    public function updateApiKey(ApiKey $apiKey, array $data): bool
    {
        $updateData = array_intersect_key($data, array_flip([
            'name', 'scopes', 'rate_limits', 'ip_whitelist', 'expires_at'
        ]));

        return $apiKey->update($updateData);
    }

    public function regenerateApiKey(ApiKey $apiKey): array
    {
        $keyData = ApiKey::generateKey();
        
        $apiKey->update([
            'key_id' => $keyData['key_id'],
            'key_hash' => $keyData['key_hash'],
            'last_used_at' => null,
        ]);

        return [
            'api_key' => $apiKey->fresh(),
            'full_key' => $keyData['full_key'],
        ];
    }

    public function getApiKeyUsageStats(ApiKey $apiKey, string $period = 'today'): array
    {
        $query = $apiKey->usageLogs();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        $total = $query->count();
        $successful = $query->clone()->successful()->count();
        $errors = $query->clone()->errors()->count();
        $avgResponseTime = $query->avg('response_time_ms');

        return [
            'total_requests' => $total,
            'successful_requests' => $successful,
            'error_requests' => $errors,
            'success_rate' => $total > 0 ? ($successful / $total) * 100 : 0,
            'avg_response_time_ms' => round($avgResponseTime ?? 0, 2),
        ];
    }

    public function getUserApiKeys(User $user, bool $activeOnly = false)
    {
        $query = $user->apiKeys();
        
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function validateApiKeyScopes(ApiKey $apiKey, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $scope) {
            if (!$apiKey->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }
}