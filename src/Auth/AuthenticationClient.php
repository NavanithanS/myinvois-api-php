<?php

namespace Nava\MyInvois\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nava\MyInvois\Config;
use Nava\MyInvois\Contracts\AuthenticationClientInterface;
use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\NetworkException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Traits\LoggerTrait;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class AuthenticationClient implements AuthenticationClientInterface
{
    use LoggerTrait;

    protected const TOKEN_ENDPOINT = '/connect/token';
    protected const DEFAULT_SCOPE = 'InvoicingAPI';
    protected const TOKEN_CACHE_PREFIX = 'myinvois_token_';
    protected const TOKEN_REFRESH_BUFFER = 300; // 5 minutes before expiry
    private const MAX_TOKEN_REQUESTS = 100; // Per hour
    private const RATE_LIMIT_WINDOW = 3600;

    protected ?string $currentToken = null;
    protected ?int $tokenExpires = null;
    protected readonly string $clientId;
    protected readonly string $clientSecret;
    protected readonly string $baseUrl;
    protected readonly GuzzleClient $httpClient;
    protected readonly CacheRepository $cache;
    protected readonly array $config;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $baseUrl,
        GuzzleClient $httpClient,
        CacheRepository $cache,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        Assert::notEmpty($clientId, 'Client ID cannot be empty');
        Assert::notEmpty($clientSecret, 'Client secret cannot be empty');
        Assert::notEmpty($baseUrl, 'Base URL cannot be empty');

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->baseUrl = $baseUrl;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->config = array_merge([
            'logging' => ['enabled' => true],
            'http' => [
                'timeout' => Config::DEFAULT_TIMEOUT,
                'connect_timeout' => Config::DEFAULT_CONNECT_TIMEOUT,
                'retry' => [
                    'times' => Config::DEFAULT_RETRY_TIMES,
                    'sleep' => Config::DEFAULT_RETRY_SLEEP,
                ],
            ],
        ], $config);

        if ($logger) {
            $this->setLogger($logger);
        }
    }

    public function authenticate(): array
    {
        try {
            $this->logDebug('Starting authentication process');

            // Check cache first
            if ($this->shouldUseCache()) {
                $cached = $this->getTokenFromCache();
                if ($cached) {
                    $this->logDebug('Using cached token');
                    return $cached;
                }
            }

            $response = $this->executeAuthRequest();
            $data = $this->parseResponse($response);
            $this->validateAuthResponse($data);

            if ($this->shouldUseCache()) {
                $this->cacheToken($data);
            }

            $this->updateCurrentToken($data);

            $this->logDebug('Authentication successful', [
                'expires_in' => $data['expires_in'] ?? 3600,
            ]);

            return $data;

        } catch (GuzzleException $e) {
            $this->logError('Authentication request failed', [
                'error' => $e->getMessage(),
            ]);
            throw $this->handleAuthenticationError($e);
        }
    }

    protected function executeAuthRequest(): mixed
    {
        try {
            // Log request details
            $requestData = [
                'endpoint' => $this->getTokenUrl(),
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => substr($this->clientId, 0, 5) . '...',
                    'scope' => self::DEFAULT_SCOPE,
                ],
                'connect_timeout' => $this->config['http']['connect_timeout'],
                'timeout' => $this->config['http']['timeout'],
            ];

            $this->logDebug('Executing auth request', $requestData);

            $response = $this->httpClient->post($this->getTokenUrl(), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => self::DEFAULT_SCOPE,
                ],
                'connect_timeout' => $this->config['http']['connect_timeout'],
                'timeout' => $this->config['http']['timeout'],
                'http_errors' => true,
                'debug' => true,
                'verify' => true,
            ]);

            $this->logDebug('Auth request successful', [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
            ]);

            return $response;

        } catch (GuzzleException $e) {
            $context = [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];

            if ($e instanceof RequestException && $e->hasResponse()) {
                $response = $e->getResponse();
                $context['response_status'] = $response->getStatusCode();
                $context['response_body'] = (string) $response->getBody();
                $context['response_headers'] = $response->getHeaders();
            }

            $this->logError('Auth request failed', $context);
            throw $e;
        }
    }

    protected function parseResponse(mixed $response): array
    {
        try {
            $body = (string) $response->getBody();

            $this->logDebug('Raw auth response', [
                'body' => $body,
                'headers' => $response->getHeaders(),
            ]);

            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse auth response', [
                    'error' => json_last_error_msg(),
                    'raw_body' => $body,
                ]);
                throw new AuthenticationException(
                    'Invalid response format: ' . json_last_error_msg()
                );
            }

            if (!isset($data['access_token'])) {
                $this->logError('Invalid auth response structure', [
                    'received_keys' => array_keys($data),
                    'raw_response' => $body,
                ]);
                throw new AuthenticationException(
                    'Invalid response structure: missing access_token'
                );
            }

            return $data;

        } catch (\Throwable $e) {
            $this->logError('Auth response parsing failed', [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'raw_response' => isset($body) ? $body : 'No response body',
            ]);
            throw $e;
        }
    }

    protected function handleAuthenticationError(GuzzleException $e): \Throwable
    {
        $context = [
            'error_class' => get_class($e),
            'error_code' => $e->getCode(),
            'client_id' => substr($this->clientId, 0, 5) . '...',
            'base_url' => $this->baseUrl,
        ];

        if ($e instanceof RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            $body = (string) $response->getBody();

            try {
                $data = json_decode($body, true);
                $errorMessage = $data['error_description'] ?? $data['error'] ?? $body;

                $context['response_status'] = $response->getStatusCode();
                $context['error_message'] = $errorMessage;
                $context['error_data'] = $data;

                return match ($response->getStatusCode()) {
                    400 => new ValidationException(
                        'Invalid request format or parameters: ' . $errorMessage,
                        ['auth' => [$errorMessage]],
                        400,
                        $e
                    ),
                    401 => new AuthenticationException(
                        'Invalid credentials or expired token: ' . $errorMessage,
                        401,
                        $e
                    ),
                    403 => new AuthenticationException(
                        'Access denied - check permissions: ' . $errorMessage,
                        403,
                        $e
                    ),
                    404 => new AuthenticationException(
                        'Authentication endpoint not found - check URL: ' . $errorMessage,
                        404,
                        $e
                    ),
                    429 => new AuthenticationException(
                        'Rate limit exceeded: ' . $errorMessage,
                        429,
                        $e
                    ),
                    500 => new AuthenticationException(
                        'Server error during authentication: ' . $errorMessage,
                        500,
                        $e
                    ),
                    default => new AuthenticationException(
                        'Authentication failed: ' . $errorMessage,
                        $response->getStatusCode(),
                        $e
                    )
                };
            } catch (\Throwable $parseError) {
                $context['parse_error'] = $parseError->getMessage();
                $context['raw_response'] = $body;
            }
        } else {
            return new NetworkException(
                'Network error during authentication: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $this->logError('Authentication error occurred', $context);

        return new AuthenticationException(
            'Authentication failed: ' . $e->getMessage(),
            $e->getCode(),
            $e
        );
    }

    public function hasValidToken(): bool
    {
        if ($this->isCurrentTokenValid()) {
            return true;
        }

        if ($cached = $this->getTokenFromCache()) {
            $this->updateCurrentToken($cached);
            return true;
        }

        return false;
    }

    public function getAccessToken(): string
    {
        if (!$this->hasValidToken()) {
            $data = $this->authenticate();
            $this->updateCurrentToken($data);
        }

        return $this->currentToken;
    }

    protected function getTokenUrl(): string
    {
        return rtrim($this->baseUrl, '/') . self::TOKEN_ENDPOINT;
    }

    protected function shouldUseCache(): bool
    {
        return true;
    }

    protected function getTokenFromCache(): ?array
    {
        return $this->cache->get($this->getCacheKey());
    }

    protected function cacheToken(array $data): void
    {
        $this->cache->put(
            $this->getCacheKey(),
            $data,
            now()->addSeconds($data['expires_in'] - self::TOKEN_REFRESH_BUFFER)
        );
    }

    protected function updateCurrentToken(array $data): void
    {
        $this->currentToken = $data['access_token'];
        $this->tokenExpires = time() + ($data['expires_in'] ?? 3600);
    }

    protected function isCurrentTokenValid(): bool
    {
        return $this->currentToken && $this->tokenExpires &&
        time() < ($this->tokenExpires - self::TOKEN_REFRESH_BUFFER);
    }

    protected function getCacheKey(): string
    {
        return self::TOKEN_CACHE_PREFIX . $this->clientId;
    }

    protected function clearCurrentToken(): void
    {
        $this->currentToken = null;
        $this->tokenExpires = null;

        if (isset($this->cache)) {
            $this->cache->forget($this->getCacheKey());
        }
    }

    /**
     * Validate authentication response data.
     *
     * @param array $data Response data to validate
     * @throws AuthenticationException If response is invalid
     */
    protected function validateAuthResponse(array $data): void
    {
        if (!isset($data['access_token'])) {
            throw new AuthenticationException(
                'Invalid authentication response: missing access_token'
            );
        }

        if (!isset($data['token_type']) || strtolower($data['token_type']) !== 'bearer') {
            throw new AuthenticationException(
                'Invalid authentication response: invalid token_type'
            );
        }

        if (!isset($data['expires_in'])) {
            throw new AuthenticationException(
                'Invalid authentication response: missing expires_in'
            );
        }

        // Optional but recommended validation
        if (isset($data['scope'])) {
            $requiredScope = Config::DEFAULT_SCOPE;
            $grantedScopes = explode(' ', $data['scope']);
            if (!in_array($requiredScope, $grantedScopes, true)) {
                throw new AuthenticationException(
                    sprintf('Required scope "%s" was not granted', $requiredScope)
                );
            }
        }
    }

    protected function enforceRateLimit(): void
    {
        $key = sprintf('auth_rate_limit_%s', $this->clientId);
        $attempts = (int) $this->cache->get($key, 0);

        if ($attempts >= self::MAX_TOKEN_REQUESTS) {
            throw new RateLimitExceededException('Token request rate limit exceeded');
        }

        $this->cache->put($key, $attempts + 1, self::RATE_LIMIT_WINDOW);
    }

    // Add token refresh prediction to prevent expiry
    protected function shouldRefreshToken(): bool
    {
        return $this->tokenExpires &&
        ($this->tokenExpires - time()) < (self::TOKEN_REFRESH_BUFFER * 2);
    }
}
