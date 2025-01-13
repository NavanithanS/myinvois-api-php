<?php

namespace Nava\MyInvois\Api;

use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for taxpayer operations.
 */
trait TaxpayerApi
{
    protected $logger = null;

    /**
     * Validate a taxpayer's TIN with additional identification.
     *
     * This method validates a Tax Identification Number (TIN) along with a secondary
     * form of identification before it can be used in document submissions.
     *
     * Important: This API should be used sparingly and results should be cached by
     * the ERP system to avoid excessive calls. Repeated calls may result in throttling.
     *
     * @param  string  $tin  Tax Identification Number to validate
     * @param  string  $idType  Type of secondary ID ('NRIC', 'PASSPORT', 'BRN', 'ARMY')
     * @param  string  $idValue  Value of the secondary ID
     * @return bool True if the TIN is valid, false otherwise
     *
     * @throws ValidationException If the input parameters are invalid
     * @throws ApiException If the API request fails
     */
    private $CACHE_PREFIX = 'myinvois_tin_validation_';

    private $CACHE_TTL = 86400; // 24 hours

    private $VALID_ID_TYPES = ['NRIC', 'PASSPORT', 'BRN', 'ARMY'];

    /**
     * Validate a taxpayer's TIN with additional identification.
     *
     * Important: This API should be used sparingly and results should be cached.
     * Repeated calls may result in throttling.
     *
     * @param  string  $tin  Tax Identification Number to validate
     * @param  string  $idType  Type of secondary ID ('NRIC', 'PASSPORT', 'BRN', 'ARMY')
     * @param  string  $idValue  Value of the secondary ID
     * @param  bool  $useCache  Whether to use cache (default: true)
     * @return bool True if the TIN is valid, false otherwise
     *
     * @throws ValidationException If the input parameters are invalid
     * @throws ApiException If the API request fails
     */
    public function validateTaxpayerTin(
        string $tin,
        string $idType,
        string $idValue,
        bool $useCache = true
    ): bool {
        try {
            $this->validateTinFormat($tin);
            $this->validateIdType($idType);
            $this->validateIdValue($idType, $idValue);

            // Check cache first if enabled
            if ($useCache && isset($this->cache)) {
                $cacheKey = $this->getCacheKey($tin, $idType, $idValue);
                $cachedResult = $this->cache->get($cacheKey);

                if ($cachedResult !== null) {
                    $this->logDebug('Using cached TIN validation result', [
                        'tin' => $tin,
                        'id_type' => $idType,
                        'cached' => true,
                    ], 'TaxpayerApi');

                    return $cachedResult;
                }
            }

            $this->logDebug('Validating taxpayer TIN', [
                'tin' => $tin,
                'id_type' => $idType,
            ], 'TaxpayerApi');

            $response = $this->apiClient->request(
                'GET',
                "/api/v1.0/taxpayer/validate/{$tin}",
                [
                    'query' => [
                        'idType' => strtoupper($idType),
                        'idValue' => $idValue,
                    ],
                ]
            );

            // Store successful result in cache
            if ($useCache && isset($this->cache)) {
                $cacheKey = $this->getCacheKey($tin, $idType, $idValue);
                $this->cache->put($cacheKey, true, self::$CACHE_TTL);
            }

            return true;

        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                $this->logDebug('TIN validation failed - TIN not found', [
                    'tin' => $tin,
                    'id_type' => $idType,
                ], 'TaxpayerApi');

                return false;
            }

            $this->logError('TIN validation failed', [
                'tin' => $tin,
                'id_type' => $idType,
                'error' => $e->getMessage(),
            ], 'TaxpayerApi');

            throw $e;
        }
    }

    /**
     * Validate TIN format.
     *
     * @throws ValidationException If TIN format is invalid
     */
    private function validateTinFormat(string $tin): void
    {
        Assert::notEmpty($tin, 'TIN cannot be empty');
        Assert::length($tin, 11, 'TIN must be exactly 11 characters');

        if (! preg_match('/^C\d{10}$/', $tin)) {
            throw new ValidationException(
                'Invalid TIN format',
                ['tin' => ['TIN must start with C followed by exactly 10 digits']],
                422
            );
        }
    }

    /**
     * Validate ID type.
     *
     * @throws ValidationException If ID type is invalid
     */
    private function validateIdType(string $idType): void
    {
        Assert::notEmpty($idType, 'ID type cannot be empty');

        $normalizedType = strtoupper(trim($idType));

        if (! in_array($normalizedType, self::$VALID_ID_TYPES, true)) {
            throw new ValidationException(
                'Invalid ID type',
                ['idType' => ['ID type must be one of: '.implode(', ', self::$VALID_ID_TYPES)]],
                422
            );
        }
    }

    /**
     * Validate ID value based on type.
     *
     * @throws ValidationException If ID value is invalid for the given type
     */
    private function validateIdValue(string $idType, string $idValue): void
    {
        Assert::notEmpty($idValue, 'ID value cannot be empty');

        $normalizedType = strtoupper($idType);
        $pattern = self::$ID_PATTERNS[$normalizedType] ?? null;

        if (! $pattern || ! preg_match($pattern, $idValue)) {
            $errorMessages = [
                'NRIC' => 'NRIC must be 12 digits',
                'PASSPORT' => 'Passport number must be a letter followed by 8 digits',
                'BRN' => 'Business registration number must be 12 digits',
                'ARMY' => 'Army number must be 12 digits',
            ];

            throw new ValidationException(
                sprintf('Invalid %s format', $normalizedType),
                ['idValue' => [$errorMessages[$normalizedType] ?? 'Invalid format']],
                422
            );
        }

        // Additional validation for specific types
        if ($normalizedType === 'PASSPORT') {
            $letter = substr($idValue, 0, 1);
            if (! ctype_upper($letter)) {
                throw new ValidationException(
                    'Invalid passport number format',
                    ['idValue' => ['Passport number must start with an uppercase letter']],
                    422
                );
            }
        }
    }

    /**
     * Generate cache key for TIN validation results.
     */
    private function getCacheKey(string $tin, string $idType, string $idValue): string
    {
        return sprintf(
            '%s_%s',
            self::TOKEN_CACHE_PREFIX,
            hash('sha256', "{$tin}:{$idType}:{$idValue}")
        );
    }

    /**
     * Get the rate limit key for the current client.
     */
    private function getRateLimitKey(): string
    {
        return sprintf('myinvois_ratelimit_%s', $this->clientId ?? 'default');
    }

    /**
     * Check if the current client is being rate limited.
     *
     * @throws ApiException if rate limit is exceeded
     */
    private function checkRateLimit(): void
    {
        if (! $this->cache) {
            return;
        }

        $key = $this->getRateLimitKey();
        $attempts = (int) $this->cache->get($key, 0);

        if ($attempts >= ($this->config['rate_limit']['max_attempts'] ?? 100)) {
            throw new ApiException(
                'Rate limit exceeded. Please reduce API calls frequency.',
                429
            );
        }

        $this->cache->put(
            $key,
            $attempts + 1,
            ($this->config['rate_limit']['window'] ?? 300) // 5 minutes default
        );
    }

    /**
     * Get all valid ID types.
     *
     * @return array<string>
     */
    public static function getValidIdTypes(): array
    {
        return self::$VALID_ID_TYPES;
    }

    /**
     * Get validation pattern for a specific ID type.
     *
     * @param  string  $idType  The ID type to get pattern for
     * @return string|null The regex pattern or null if type is invalid
     */
    public static function getValidationPattern(string $idType): ?string
    {
        return self::$ID_PATTERNS[strtoupper($idType)] ?? null;
    }
}
