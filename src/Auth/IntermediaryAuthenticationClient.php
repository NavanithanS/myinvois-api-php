<?php

namespace Nava\MyInvois\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nava\MyInvois\Contracts\IntermediaryAuthenticationClientInterface;
use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class IntermediaryAuthenticationClient extends AuthenticationClient implements IntermediaryAuthenticationClientInterface
{
    protected const TOKEN_CACHE_PREFIX = 'myinvois_intermediary_token_';

    private const TIN_PATTERN = '/^C\d{10}$/';

    private const TOKEN_REFRESH_BUFFER = 300; // 5 minutes before expiry

    private ?string $onBehalfOf = null;

    private array $cachedTokens = [];

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
            clientId: $clientId,
            clientSecret: $clientSecret,
            baseUrl: $baseUrl,
            httpClient: $httpClient,
            cache: $cache,
            config: $config,
            logger: $logger
        );

        $this->validateIntermediaryConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function onBehalfOf(string $tin): self
    {
        if (! preg_match(self::TIN_PATTERN, $tin)) {
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
    public function authenticate(): array
    {
        if (! $this->onBehalfOf) {
            $this->logError('Authentication attempted without setting taxpayer TIN');
            throw new ValidationException(
                'Taxpayer TIN must be set using onBehalfOf() before authenticating',
                ['tin' => ['TIN is required']]
            );
        }

        try {
            return parent::authenticate();
        } catch (GuzzleException $e) {
            throw $this->handleIntermediaryAuthError($e);
        }
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
        if (! $response) {
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
                    'Intermediary not authorized for this taxpayer: '.$errorMessage,
                    403,
                    $e
                );

            case 400:
                if (str_contains(strtolower($errorMessage), 'taxpayer')) {
                    return new ValidationException(
                        'Invalid taxpayer TIN: '.$errorMessage,
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
        if (! empty($missingScopes)) {
            throw new AuthenticationException(
                'Missing required scopes: '.implode(', ', $missingScopes),
                403
            );
        }
    }

    /**
     * Clear the current token when switching taxpayers.
     */
    private function clearCurrentToken(): void
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
        return static::TOKEN_CACHE_PREFIX.$this->clientId.'_'.$this->onBehalfOf;
    }

    /**
     * {@inheritdoc}
     */
    protected function shouldUseCache(): bool
    {
        return parent::shouldUseCache() && $this->onBehalfOf !== null;
    }

    /**
     * {@inheritdoc}
     */
    protected function isCurrentTokenValid(): bool
    {
        return parent::isCurrentTokenValid() && $this->onBehalfOf !== null;
    }
}
