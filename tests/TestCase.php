<?php

namespace Nava\MyInvois\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Config;
use Nava\MyInvois\MyInvoisClient;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected $mockHandler;
    protected $container = [];
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a new mock handler
        $this->mockHandler = new MockHandler;

        // Create a handler stack with the mock
        $handlerStack = HandlerStack::create($this->mockHandler);

        // Add history middleware
        $history = Middleware::history($this->container);
        $handlerStack->push($history);

        // Create HTTP client with the handler
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        // Create MyInvois client with complete configuration
        $this->client = new MyInvoisClient(
            'test_client_id',
            'test_client_secret',
            $this->app['cache']->store(),
            $httpClient,
            MyInvoisClient::SANDBOX_URL,
            json_encode([
                'auth' => [
                    'url' => MyInvoisClient::IDENTITY_SANDBOX_URL,
                    'token_ttl' => 3600,
                    'token_refresh_buffer' => 300,
                ],
                'cache' => [
                    'enabled' => true,
                    'ttl' => 3600,
                ],
                'http' => [
                    'timeout' => Config::DEFAULT_TIMEOUT,
                    'connect_timeout' => Config::DEFAULT_CONNECT_TIMEOUT,
                    'retry' => [
                        'times' => Config::DEFAULT_RETRY_TIMES,
                        'sleep' => Config::DEFAULT_RETRY_SLEEP,
                    ],
                ],
                'logging' => [
                    'enabled' => true,
                    'channel' => 'testing',
                ],
            ])
        );
    }

    protected function mockSuccessfulAuthentication(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );
    }

    protected function getLastRequest(): Request
    {
        return end($this->container)['request'];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('myinvois.client_id', 'test_client_id');
        $app['config']->set('myinvois.client_secret', 'test_client_secret');
    }
}
