<?php

namespace Nava\MyInvois\Tests\Unit\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Nava\MyInvois\Auth\IntermediaryAuthenticationClient;
use Nava\MyInvois\MyInvoisClient;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\NullLogger;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Exception\AuthenticationException;

class IntermediaryAuthenticationClientTest extends TestCase
{
    protected $mockHandler;
    protected $container = [];
    protected $client; // Match parent class type
    protected $validTin = 'C1234567890';
    protected $validResponse;

    protected function setUp(): void
    {
        // Don't call parent::setUp() since we need a different client setup
        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $history = Middleware::history($this->container);
        $handlerStack->push($history);

        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        // Create intermediary authentication client
        $authClient = new IntermediaryAuthenticationClient(
            'test_client',
            'test_secret',
            'https://test.myinvois.com',
            $httpClient,
            new Repository(new ArrayStore),
            [
                'logging' => ['enabled' => true],
                'cache' => ['enabled' => true],
            ],
            new NullLogger
        );

        // Create MyInvois client with the intermediary auth client
        $this->client = new MyInvoisClient(
            'test_client',
            'test_secret',
            new Repository(new ArrayStore),
            'https://test.myinvois.com',
            $httpClient,
            [
                'auth' => [
                    'client' => $authClient,
                ],
            ]
        );
    }

    /** @test */
    public function it_requires_tin_before_authentication(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Taxpayer TIN must be set');

        $this->client->authenticate();
    }

    /** @test */
    public function it_validates_tin_format(): void
    {
        $invalidTins = [
            'invalid-tin',
            'D1234567890', // Wrong prefix
            'C123456789', // Too short
            'C12345678901', // Too long
            'C123456789A', // Contains letter
            'CXXXXXXXXXX', // Non-numeric
            '', // Empty string
            'C', // Only prefix
        ];

        foreach ($invalidTins as $tin) {
            try {
                $this->client->onBehalfOf($tin);
                $this->fail("Expected ValidationException for TIN: {$tin}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid TIN format', $e->getMessage());
                $this->assertArrayHasKey('tin', $e->getErrors());
            }
        }
    }

    /** @test */
    public function it_accepts_valid_tin_format(): void
    {
        $validTins = [
            'C1234567890',
            'C0123456789',
            'C9876543210',
        ];

        foreach ($validTins as $tin) {
            try {
                $this->client->onBehalfOf($tin);
                $this->assertEquals($tin, $this->client->getCurrentTaxpayer());
            } catch (ValidationException $e) {
                $this->fail("Unexpected ValidationException for valid TIN: {$tin}");
            }
        }
    }

    /** @test */
    public function it_clears_token_when_switching_taxpayers(): void
    {
        $this->client->onBehalfOf('C1234567890');

        // Mock first successful authentication
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'token_1',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );

        $firstToken = $this->client->authenticate();

        // Switch taxpayer
        $this->client->onBehalfOf('C9876543210');

        // Mock second authentication
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'token_2',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );

        $secondToken = $this->client->authenticate();

        $this->assertNotEquals($firstToken['access_token'], $secondToken['access_token']);
    }

    /** @test */
    public function it_adds_onbehalfof_header_to_auth_request(): void
    {
        $this->client->onBehalfOf($this->validTin);

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );

        $this->client->authenticate();

        $request = $this->container[0]['request'];
        $this->assertEquals($this->validTin, $request->getHeader('onbehalfof')[0]);
        $this->assertEquals('application/x-www-form-urlencoded', $request->getHeader('Content-Type')[0]);
        $this->assertEquals('application/json', $request->getHeader('Accept')[0]);
    }

    /** @test */
    public function it_handles_intermediary_authorization_failure(): void
    {
        $this->client->onBehalfOf($this->validTin);

        $this->mockHandler->append(
            new Response(403, [], json_encode([
                'error' => 'forbidden',
                'error_description' => 'Intermediary not authorized for this taxpayer',
            ]))
        );

        try {
            $this->client->authenticate();
            $this->fail('Expected AuthenticationException was not thrown');
        } catch (AuthenticationException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertStringContainsString('Intermediary not authorized', $e->getMessage());
        }
    }

    /** @test */
    public function it_caches_tokens_separately_for_different_tins(): void
    {
        // First taxpayer authentication
        $this->client->onBehalfOf('C1234567890');
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'token_1',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );
        $token1 = $this->client->authenticate();

        // Second taxpayer authentication
        $this->client->onBehalfOf('C9876543210');
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'token_2',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );
        $token2 = $this->client->authenticate();

        // Switch back to first taxpayer
        $this->client->onBehalfOf('C1234567890');
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'token_3',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );
        $token3 = $this->client->authenticate();

        $this->assertNotEquals($token1['access_token'], $token2['access_token']);
        $this->assertNotEquals($token2['access_token'], $token3['access_token']);
    }

    /** @test */
    public function it_handles_invalid_taxpayer_error(): void
    {
        $this->client->onBehalfOf($this->validTin);

        $this->mockHandler->append(
            new Response(400, [], json_encode([
                'error' => 'invalid_request',
                'error_description' => 'Invalid taxpayer TIN',
            ]))
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid taxpayer TIN');

        $this->client->authenticate();
    }

    /** @test */
    public function it_includes_proper_auth_params(): void
    {
        $this->client->onBehalfOf($this->validTin);

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );

        $this->client->authenticate();

        $request = $this->container[0]['request'];
        $body = (string) $request->getBody();
        parse_str($body, $params);

        $this->assertEquals('test_client', $params['client_id']);
        $this->assertEquals('test_secret', $params['client_secret']);
        $this->assertEquals('client_credentials', $params['grant_type']);
        $this->assertEquals('InvoicingAPI', $params['scope']);
    }

    /** @test */
    public function it_refreshes_expired_token(): void
    {
        $this->client->onBehalfOf($this->validTin);

        // First token response with short expiry
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'token_1',
                'token_type' => 'Bearer',
                'expires_in' => 1,
                'scope' => 'InvoicingAPI',
            ]))
        );

        // Second token response after expiry
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'token_2',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'InvoicingAPI',
            ]))
        );

        // Get first token
        $token1 = $this->client->authenticate();

        // Wait for token to expire
        sleep(2);

        // Get second token
        $token2 = $this->client->authenticate();

        $this->assertNotEquals($token1['access_token'], $token2['access_token']);
        $this->assertCount(2, $this->container);
    }

}
