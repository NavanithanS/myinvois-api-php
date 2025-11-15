<?php

namespace Nava\MyInvois\Traits;

use Nava\MyInvois\Exception\ApiException;
use Psr\SimpleCache\CacheInterface;

trait RateLimitingTrait
{
    /** @var CacheInterface */
    protected $cache;

    /**
     * Check and enforce rate limiting for a specific operation.
     *
     * @param string $key Unique identifier for the rate limit
     * @param array $config Rate limit configuration
     *   - max_requests: Maximum number of requests allowed
     *   - window: Time window in seconds
     * @return bool True if request is allowed, false otherwise
     *
     * @throws ApiException If rate limit is exceeded
     */
    protected function checkRateLimit(
        string $key,
        array $config = []
    ): bool {
        // Default rate limit configuration
        $defaultConfig = [
            'max_requests' => 100, // 100 requests
            'window' => 3600, // per hour
            'cache_prefix' => 'myinvois_ratelimit_',
        ];
        $config = array_merge($defaultConfig, $config);

        // Generate a unique cache key
        $cacheKey = $config['cache_prefix'] . $key;

        // Get current request count
        if (!isset($this->cache)) {
            $this->cache = app('cache')->store();
        }
        $requestCount = (int) $this->cache->get($cacheKey, 0);
        
        // Check if rate limit is exceeded
        if ($requestCount >= $config['max_requests']) {
            throw new ApiException(
                sprintf(
                    'Rate limit exceeded: %d requests per %d seconds',
                    $config['max_requests'],
                    $config['window']
                ),
                429// Too Many Requests status code
            );
        }

        // Increment request count
        $this->cache->set(
            $cacheKey,
            $requestCount + 1,
            $config['window']
        );

        return true;
    }

    /**
     * Create a rate limit configuration for a specific method.
     *
     * @param string $method Method name
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return array Rate limit configuration
     */
    protected function createRateLimitConfig(
        string $method,
        int $maxRequests = 100,
        int $windowSeconds = 3600
    ): array {
        return [
            'max_requests' => $maxRequests,
            'window' => $windowSeconds,
            'cache_prefix' => 'myinvois_method_ratelimit_' . $method,
        ];
    }
}
