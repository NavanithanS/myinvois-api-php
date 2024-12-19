<?php

namespace Nava\MyInvois\Contracts;

use GuzzleHttp\Client as GuzzleClient;
use Nava\MyInvois\MyInvoisClient;

/**
 * Contract for creating MyInvois client instances.
 */
interface MyInvoisClientFactoryInterface
{
    /**
     * Create a new MyInvois client instance.
     *
     * @param  string|null  $clientId  The client ID for API authentication (optional if using environment config)
     * @param  string|null  $clientSecret  The client secret for API authentication (optional if using environment config)
     * @param  string|null  $baseUrl  The base URL for the API (optional, defaults to production URL)
     * @param  GuzzleClient|null  $httpClient  Custom HTTP client instance
     * @param  array  $options  Additional configuration options
     *
     *     @option bool $cacheEnabled Whether to enable token caching (default: true)
     *     @option string $cacheStore The cache store to use (default: 'file')
     *     @option int $cacheTtl Cache TTL in seconds (default: 3600)
     *     @option bool $loggingEnabled Whether to enable request/response logging (default: true)
     *     @option string $logChannel The log channel to use (default: 'stack')
     *
     * @throws \InvalidArgumentException If required configuration is missing
     */
    public function make(
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $baseUrl = null,
        ?GuzzleClient $httpClient = null,
        array $options = []
    ): MyInvoisClient;

    /**
     * Create a client instance for the sandbox environment.
     *
     * @param  string|null  $clientId  Sandbox client ID
     * @param  string|null  $clientSecret  Sandbox client secret
     * @param  array  $options  Additional configuration options
     */
    public function sandbox(
        ?string $clientId = null,
        ?string $clientSecret = null,
        array $options = []
    ): MyInvoisClient;

    /**
     * Create a client instance for the production environment.
     *
     * @param  string|null  $clientId  Production client ID
     * @param  string|null  $clientSecret  Production client secret
     * @param  array  $options  Additional configuration options
     */
    public function production(
        ?string $clientId = null,
        ?string $clientSecret = null,
        array $options = []
    ): MyInvoisClient;

    /**
     * Create a client instance for an intermediary system.
     *
     * @param  string  $clientId  Intermediary client ID
     * @param  string  $clientSecret  Intermediary client secret
     * @param  string  $taxpayerTin  TIN of the taxpayer being represented
     * @param  array  $options  Additional configuration options
     *
     * @throws \InvalidArgumentException If required parameters are missing
     * @throws \Nava\MyInvois\Exception\ValidationException If TIN format is invalid
     */
    public function intermediary(
        string $clientId,
        string $clientSecret,
        string $taxpayerTin,
        array $options = []
    ): MyInvoisClient;

    /**
     * Create an intermediary client instance for the sandbox environment.
     *
     * @param  string  $clientId  Sandbox intermediary client ID
     * @param  string  $clientSecret  Sandbox intermediary client secret
     * @param  string  $taxpayerTin  TIN of the taxpayer being represented
     * @param  array  $options  Additional configuration options
     */
    public function intermediarySandbox(
        string $clientId,
        string $clientSecret,
        string $taxpayerTin,
        array $options = []
    ): MyInvoisClient;

    /**
     * Set the default configuration options for all new client instances.
     *
     * @param  array  $options  Default configuration options
     */
    public function configure(array $options): void;

    /**
     * Get the current default configuration options.
     */
    public function getDefaultOptions(): array;
}
