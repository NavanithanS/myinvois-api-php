<?php

namespace Nava\MyInvois\Laravel;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Nava\MyInvois\Auth\AuthenticationClient;
use Nava\MyInvois\Auth\IntermediaryAuthenticationClient;
use Nava\MyInvois\Config;
use Nava\MyInvois\Contracts\AuthenticationClientInterface;
use Nava\MyInvois\Contracts\IntermediaryAuthenticationClientInterface;
use Nava\MyInvois\Contracts\MyInvoisClientFactoryInterface;
use Nava\MyInvois\MyInvoisClient;
use Nava\MyInvois\MyInvoisClientFactory;

class MyInvoisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/myinvois.php',
            'myinvois'
        );

        // Register GuzzleHttp client
        $this->app->singleton(GuzzleClient::class, function ($app) {
            return new GuzzleClient([
                'timeout' => config('myinvois.http.timeout', 30),
                'connect_timeout' => config('myinvois.http.connect_timeout', 10),
                'http_errors' => true,
            ]);
        });

        // Register Authentication Clients
        $this->registerAuthenticationClients();

        // Register Client Factory
        $this->app->singleton(MyInvoisClientFactoryInterface::class, function ($app) {
            return new MyInvoisClientFactory;
        });

        // Register Main Client
        $this->app->singleton(MyInvoisClient::class, function ($app) {
            return $app->make(MyInvoisClientFactoryInterface::class)->make();
        });

        // Register Facade Alias
        $this->app->alias(MyInvoisClient::class, 'myinvois');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/myinvois.php' => config_path('myinvois.php'),
            ], 'myinvois-config');
        }
    }

    protected function registerAuthenticationClients(): void
    {
        // Register regular Authentication Client
        $this->app->singleton(AuthenticationClientInterface::class, function ($app) {
            $config = $app['config']['myinvois'];

            // return new AuthenticationClient(
            //     clientId: $config['client_id'],
            //     clientSecret: $config['client_secret'],
            //     baseUrl: $config['auth']['url'] ?? Config::IDENTITY_PRODUCTION_URL,
            //     httpClient: $app->make(GuzzleClient::class),
            //     cache: Cache::store(),
            //     config: $this->getAuthConfig($config)
            // );

            return new AuthenticationClient(
                $config['client_id'],
                $config['client_secret'],
                $config['auth']['url'] ?? Config::IDENTITY_PRODUCTION_URL,
                $app->make(GuzzleClient::class),
                Cache::store(),
                $this->getAuthConfig($config)
            );
            
        });

        // Register Intermediary Authentication Client
        $this->app->singleton(IntermediaryAuthenticationClientInterface::class, function ($app) {
            $config = $app['config']['myinvois'];

            // return new IntermediaryAuthenticationClient(
            //     clientId: $config['client_id'],
            //     clientSecret: $config['client_secret'],
            //     baseUrl: $config['auth']['url'] ?? Config::IDENTITY_PRODUCTION_URL,
            //     httpClient: $app->make(GuzzleClient::class),
            //     cache: $app['cache']->store(), // Pass the cache store instance
            //     config: $this->getAuthConfig($config)
            // );

            return new IntermediaryAuthenticationClient(
                $config['client_id'],
                $config['client_secret'],
                $config['auth']['url'] ?? Config::IDENTITY_PRODUCTION_URL,
                $app->make(GuzzleClient::class),
                $app['cache']->store(), // Pass the cache store instance
                $this->getAuthConfig($config)
            );
        });
    }

    protected function getAuthConfig(array $config): array
    {
        return [
            'cache' => [
                'enabled' => $config['cache']['enabled'] ?? true,
                'ttl' => $config['cache']['ttl'] ?? Config::DEFAULT_TOKEN_TTL,
                'store' => $config['cache']['store'] ?? 'file',
            ],
            'logging' => [
                'enabled' => $config['logging']['enabled'] ?? true,
                'channel' => $config['logging']['channel'] ?? 'stack',
                'level' => $config['logging']['level'] ?? 'debug',
            ],
            'http' => [
                'timeout' => $config['http']['timeout'] ?? Config::DEFAULT_TIMEOUT,
                'connect_timeout' => $config['http']['connect_timeout'] ?? Config::DEFAULT_CONNECT_TIMEOUT,
                'retry' => [
                    'times' => $config['http']['retry']['times'] ?? Config::DEFAULT_RETRY_TIMES,
                    'sleep' => $config['http']['retry']['sleep'] ?? Config::DEFAULT_RETRY_SLEEP,
                ],
            ],
        ];
    }
}
