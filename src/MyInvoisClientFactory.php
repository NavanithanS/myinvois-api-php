<?php

namespace Nava\MyInvois;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Cache;
use Nava\MyInvois\Auth\AuthenticationClient;
use Nava\MyInvois\Auth\IntermediaryAuthenticationClient;
use Nava\MyInvois\Contracts\MyInvoisClientFactoryInterface;
use Nava\MyInvois\Exception\ValidationException;

class MyInvoisClientFactory implements MyInvoisClientFactoryInterface
{
    private $defaultOptions = [];

    /**
     * Create a new MyInvois client instance.
     */
    public function make(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $baseUrl = null,
        ?GuzzleClient $httpClient = null,
        array $options = []
    ): MyInvoisClient {
        $clientId = $clientId ?? config('myinvois.client_id');
        $clientSecret = $clientSecret ?? config('myinvois.client_secret');
        $baseUrl = $baseUrl ?? config('myinvois.base_url', MyInvoisClient::PRODUCTION_URL);

        if (empty($clientId) || empty($clientSecret)) {
            throw new \InvalidArgumentException('MyInvois client ID and secret are required.');
        }

        $options = array_merge($this->defaultOptions, $options);

        $httpClient = $httpClient ?? new GuzzleClient([
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
            'http_errors' => true,
        ]);

        // Determine identity service URL based on base URL
        $identityUrl = str_contains($baseUrl, 'preprod')
        ? MyInvoisClient::IDENTITY_SANDBOX_URL
        : MyInvoisClient::IDENTITY_PRODUCTION_URL;

        // Create authentication client
        $authClient = new AuthenticationClient(
            $clientId,
            $clientSecret,
            $identityUrl,
            $httpClient,
            Cache::store(),
            [
                'cache' => [
                    'enabled' => $options['cache']['enabled'] ?? true,
                    'ttl' => $options['cache']['ttl'] ?? 3600,
                ],
                'logging' => [
                    'enabled' => $options['logging']['enabled'] ?? true,
                    'channel' => $options['logging']['channel'] ?? 'stack',
                ],
            ]
        );

        return new MyInvoisClient(
            $clientId,
            $clientSecret,
            app('cache')->store(),
            $httpClient,
            $baseUrl,
            json_encode(array_merge($options, [
                'auth' => [
                    'client' => $authClient,
                ],
            ]))
        );
    }

    /**
     * Create a client instance for the sandbox environment.
     */
    public function sandbox(
        ?string $clientId = null,
        ?string $clientSecret = null,
        array $option = []
    ): MyInvoisClient {
        return $this->make(
            $clientId,
            $clientSecret,
            MyInvoisClient::SANDBOX_URL
        );
    }

    /**
     * Create a client instance for the production environment.
     */
    public function production(
        ?string $clientId = null,
        ?string $clientSecret = null,
        array $options = []
    ): MyInvoisClient {
        return $this->make(
            $clientId,
            $clientSecret,
            MyInvoisClient::PRODUCTION_URL
        );
    }

    /**
     * Create a client instance for an intermediary system.
     *
     * @throws ValidationException If TIN format is invalid
     */
    public function intermediary(
        string $clientId,
        string $clientSecret,
        string $taxpayerTin,
        array $options = []
    ): MyInvoisClient {
        $baseUrl = $options['baseUrl'] ?? MyInvoisClient::PRODUCTION_URL;
        $identityUrl = str_contains($baseUrl, 'preprod')
        ? MyInvoisClient::IDENTITY_SANDBOX_URL
        : MyInvoisClient::IDENTITY_PRODUCTION_URL;

        $httpClient = new GuzzleClient([
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
            'http_errors' => true,
        ]);

        // Create intermediary authentication client
        $authClient = new IntermediaryAuthenticationClient(
            $clientId,
            $clientSecret,
            $identityUrl,
            $httpClient,
            Cache::store(),
            $this->getAuthConfig($config)
        );

        // Set the taxpayer TIN
        $authClient->onBehalfOf($taxpayerTin);

        return new MyInvoisClient(
            $clientId,
            $clientSecret,
            app('cache')->store(),
            $httpClient,
            $baseUrl,
            json_encode(array_merge($options, [
                'auth' => [
                    'client' => $authClient,
                ],
            ]))
        );
    }

    /**
     * Create an intermediary client instance for the sandbox environment.
     */
    public function intermediarySandbox(
        string $clientId,
        string $clientSecret,
        string $taxpayerTin,
        array $options = []
    ): MyInvoisClient {
        return $this->intermediary(
            $clientId,
            $clientSecret,
            $taxpayerTin,
            array_merge($options, [
                'baseUrl' => MyInvoisClient::SANDBOX_URL,
            ])
        );
    }

    /**
     * Set the default configuration options for all new client instances.
     */
    public function configure(array $options): void
    {
        $this->defaultOptions = $options;
    }

    /**
     * Get the current default configuration options.
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    /**
     * Create an HTTP client with configured options.
     */
    private function createHttpClient(array $options): GuzzleClient
    {
        return new GuzzleClient([
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
            'http_errors' => true,
        ]);
    }

    /**
     * Get base configuration for authentication clients.
     */
    private function getBaseAuthConfig(array $options): array
    {
        return [
            'cache' => [
                'enabled' => $options['cache']['enabled'] ?? true,
                'ttl' => $options['cache']['ttl'] ?? 3600,
                'store' => $options['cache']['store'] ?? 'file',
            ],
            'logging' => [
                'enabled' => $options['logging']['enabled'] ?? true,
                'channel' => $options['logging']['channel'] ?? 'stack',
            ],
            'http' => [
                'timeout' => $options['timeout'] ?? Config::DEFAULT_TIMEOUT,
                'connect_timeout' => $options['connect_timeout'] ?? Config::DEFAULT_CONNECT_TIMEOUT,
                'retry' => [
                    'times' => $options['retry']['times'] ?? Config::DEFAULT_RETRY_TIMES,
                    'sleep' => $options['retry']['sleep'] ?? Config::DEFAULT_RETRY_SLEEP,
                ],
            ],
        ];
    }
}
