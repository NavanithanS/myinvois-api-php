<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiKeyAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $this->extractApiKey($request);
        
        if (!$apiKey) {
            return $this->unauthorizedResponse('API key is required');
        }

        $keyModel = $this->validateApiKey($apiKey);
        
        if (!$keyModel) {
            return $this->unauthorizedResponse('Invalid API key');
        }

        if (!$keyModel->isActive()) {
            return $this->unauthorizedResponse('API key is inactive or expired');
        }

        if (!$keyModel->isIpAllowed($request->ip())) {
            return $this->unauthorizedResponse('IP address not allowed');
        }

        // Set authenticated user and API key
        $request->setUserResolver(fn() => $keyModel->user);
        $request->attributes->set('api_key', $keyModel);

        // Log the request
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $this->logApiUsage($request, $response, $keyModel, $startTime);
        
        // Update last used timestamp
        $keyModel->updateLastUsed();

        return $response;
    }

    private function extractApiKey(Request $request): ?string
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check X-API-Key header
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader) {
            return $apiKeyHeader;
        }

        // Check query parameter
        return $request->query('api_key');
    }

    private function validateApiKey(string $apiKey): ?ApiKey
    {
        // Extract key ID from the full key
        $parts = explode('.', $apiKey);
        if (count($parts) !== 2) {
            return null;
        }

        $keyId = $parts[0];
        $keyModel = ApiKey::where('key_id', $keyId)->first();

        if (!$keyModel) {
            return null;
        }

        // Verify the full key hash
        $expectedHash = hash('sha256', $apiKey);
        if (!hash_equals($keyModel->key_hash, $expectedHash)) {
            return null;
        }

        return $keyModel;
    }

    private function logApiUsage(Request $request, Response $response, ApiKey $apiKey, float $startTime): void
    {
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        ApiUsageLog::create([
            'user_id' => $apiKey->user_id,
            'api_key_id' => $apiKey->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => round($responseTime),
            'request_size' => strlen($request->getContent()),
            'response_size' => strlen($response->getContent()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_headers' => $this->sanitizeHeaders($request->headers->all()),
            'response_headers' => $this->sanitizeHeaders($response->headers->all()),
            'error_message' => $response->getStatusCode() >= 400 ? $response->getContent() : null,
            'created_at' => now(),
        ]);
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];
        
        return array_filter($headers, function($key) use ($sensitiveHeaders) {
            return !in_array(strtolower($key), $sensitiveHeaders);
        }, ARRAY_FILTER_USE_KEY);
    }

    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
        ], 401);
    }
}