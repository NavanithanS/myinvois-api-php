<?php

namespace Nava\MyInvois\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nava\MyInvois\Contracts\IntermediaryAuthenticationClientInterface;
use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Traits\RateLimitingTrait;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class IntermediaryAuthenticationClient extends AuthenticationClient implements IntermediaryAuthenticationClientInterface
{
    use RateLimitingTrait;

    protected const TOKEN_CACHE_PREFIX = 'myinvois_intermediary_token_';

    protected const TIN_PATTERN = '/^C\d{10,12}$/';

    protected const TOKEN_REFRESH_BUFFER = 300; // 5 minutes before expiry

    private const MAX_TOKEN_REQUESTS = 100; // Per hour

    private const RATE_LIMIT_WINDOW = 3600;

    private $onBehalfOf = null;

    private $cachedTokens = [];

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $baseUrl,
        GuzzleClient $httpClient,
        CacheRepository $cache,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        parent::__construct(
            $clientId,
            $clientSecret,
            $baseUrl,
            $httpClient,
            $cache,
            $config,
            $logger
        );

        $this->validateIntermediaryConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function onBehalfOf(string $tin): self
    {
        if (!preg_match(self::TIN_PATTERN, $tin)) {
            $this->logError('Invalid TIN format provided', ['tin' => $tin]);
            throw new ValidationException(
                'Invalid TIN format',
                ['tin' => ['TIN must start with C followed by 10 digits']]
            );
        }

        if ($this->onBehalfOf !== $tin) {
            $this->clearCurrentToken(); // Clear current token when switching taxpayers
        }

        $this->onBehalfOf = $tin;
        $this->logDebug('Set taxpayer TIN', ['tin' => $tin]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentTaxpayer(): ?string
    {
        return $this->onBehalfOf;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(string $tin): array
    {
        if (!empty($tin) && $tin !== $this->onBehalfOf) {
            $this->onBehalfOf($tin);
        }
        if (!$this->onBehalfOf) {
            $this->logError('Authentication attempted without setting taxpayer TIN');
            throw new ValidationException(
                'Taxpayer TIN must be set using onBehalfOf() before authenticating',
                ['tin' => ['TIN is required']]
            );
        }

        try {
            // Forward the TIN to parent for header inclusion
            return parent::authenticate($this->onBehalfOf);
        } catch (GuzzleException $e) {
            throw $this->handleIntermediaryAuthError($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken(): string
    {
        if (!$this->onBehalfOf) {
            throw new ValidationException(
                'Taxpayer TIN must be set using onBehalfOf() before getting access token',
                ['tin' => ['TIN is required']]
            );
        }

        $data = $this->authenticate($this->onBehalfOf);

        if (!isset($data['access_token'])) {
            throw new AuthenticationException('Authentication response missing access_token');
        }

        return $data['access_token'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthRequestHeaders(): array
    {
        return array_merge(
            parent::getAuthRequestHeaders(),
            ['onbehalfof' => $this->onBehalfOf]
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function validateAuthResponse(array $data): void
    {
        parent::validateAuthResponse($data);

        if (isset($data['scope'])) {
            $this->validateScope($data['scope']);
        }

        $this->logDebug('Validated intermediary authentication response', [
            'taxpayer_tin' => $this->onBehalfOf,
            'expires_in' => $data['expires_in'] ?? null,
        ]);
    }

    /**
     * Handle intermediary-specific authentication errors.
     */
    protected function handleIntermediaryAuthError(GuzzleException $e): \Throwable
    {
        $response = $e->getResponse();
        if (!$response) {
            return parent::handleAuthenticationError($e);
        }

        $body = $this->parseResponse($response);
        $errorMessage = $body['error_description'] ?? $body['error'] ?? 'Unknown authentication error';

        // Handle intermediary-specific error cases
        switch ($response->getStatusCode()) {
            case 403:
                $this->logError('Intermediary authorization failed', [
                    'error' => $errorMessage,
                    'taxpayer_tin' => $this->onBehalfOf,
                ]);

                return new AuthenticationException(
                    'Intermediary not authorized for this taxpayer: ' . $errorMessage,
                    403,
                    $e
                );

            case 400:
                if (str_contains(strtolower($errorMessage), 'taxpayer')) {
                    return new ValidationException(
                        'Invalid taxpayer TIN: ' . $errorMessage,
                        ['tin' => [$errorMessage]],
                        400,
                        $e
                    );
                }
                break;

            case 401:
                $this->clearCurrentToken();
                break;
        }

        return parent::handleAuthenticationError($e);
    }

    /**
     * Validate the scope from the authentication response.
     */
    private function validateScope(string $scope): void
    {
        $requiredScopes = ['InvoicingAPI'];
        $grantedScopes = explode(' ', $scope);

        $missingScopes = array_diff($requiredScopes, $grantedScopes);
        if (!empty($missingScopes)) {
            throw new AuthenticationException(
                'Missing required scopes: ' . implode(', ', $missingScopes),
                403
            );
        }
    }

    /**
     * Clear the current token when switching taxpayers.
     */
    protected function clearCurrentToken(): void
    {
        $this->logDebug('Clearing current token due to taxpayer change');

        if ($this->onBehalfOf) {
            if (isset($this->cachedTokens[$this->onBehalfOf])) {
                unset($this->cachedTokens[$this->onBehalfOf]);
            }
            $this->cache->forget($this->getCacheKey());
        }
    }

    /**
     * Validate intermediary-specific configuration.
     */
    private function validateIntermediaryConfig(): void
    {
        if (isset($this->config['intermediary'])) {
            Assert::isArray(
                $this->config['intermediary'],
                'Intermediary configuration must be an array'
            );

            if (isset($this->config['intermediary']['default_tin'])) {
                $this->onBehalfOf($this->config['intermediary']['default_tin']);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getCacheKey(): string
    {
        return static::TOKEN_CACHE_PREFIX . $this->clientId . '_' . $this->onBehalfOf;
    }

    /**
     * {@inheritdoc}
     */
    protected function shouldUseCache(): bool
    {
        return parent::shouldUseCache() && null !== $this->onBehalfOf;
    }

    /**
     * {@inheritdoc}
     */
    protected function isCurrentTokenValid(): bool
    {
        return parent::isCurrentTokenValid() && null !== $this->onBehalfOf;
    }
}
