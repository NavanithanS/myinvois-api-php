<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\MyInvoisClient;
use Orchestra\Testbench\TestCase;

class MyInvoisClientTest extends TestCase
{
    protected MockHandler $mockHandler;

    protected array $container = [];

    protected MyInvoisClient $client;

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

        // Create MyInvois client
        $this->client = new MyInvoisClient(
            clientId: 'test_client_id',
            clientSecret: 'test_client_secret',
            baseUrl: MyInvoisClient::SANDBOX_URL,
            cache: app('cache')->store(),
            config: [
                'httpClient' => $httpClient,
            ]
        );
    }

    /** @test */
    public function it_can_authenticate_successfully(): void
    {
        // Mock the OAuth token response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]))
        );

        // Mock successful API response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'status' => 'success',
                'data' => ['test' => true],
            ]))
        );

        // Make any API request to trigger authentication
        $response = $this->client->getApiStatus();

        // Assert the authentication request was made
        $request = $this->container[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertStringContainsString('/oauth/token', $request->getUri()->getPath());

        // Verify token was used in subsequent request
        $apiRequest = $this->container[1]['request'];
        $this->assertEquals(
            'Bearer test_token',
            $apiRequest->getHeader('Authorization')[0]
        );
    }

    /** @test */
    public function it_can_submit_invoice(): void
    {
        // Mock successful authentication
        $this->mockSuccessfulAuthentication();

        // Mock invoice submission response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'documentId' => 'DOC123',
                'status' => 'PENDING',
            ]))
        );

        $invoice = [
            'issueDate' => '2024-11-12',
            'dueDate' => '2024-12-12',
            'totalAmount' => 1000.50,
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unitPrice' => 1000.50,
                    'taxAmount' => 60.03,
                ],
            ],
        ];

        $response = $this->client->submitInvoice($invoice);

        // Assert request was made correctly
        $request = $this->getLastRequest();
        $this->assertEquals('POST', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/documentsubmissions', $request->getUri()->getPath());

        // Verify request body (wrapped in documents submission)
        $sentData = json_decode($this->container[1]['request']->getBody(), true);
        $this->assertArrayHasKey('documents', $sentData);
        $this->assertIsArray($sentData['documents']);
        $this->assertNotEmpty($sentData['documents']);
        $doc = $sentData['documents'][0];
        $this->assertArrayHasKey('document', $doc);
        $this->assertArrayHasKey('documentHash', $doc);
        $this->assertArrayHasKey('codeNumber', $doc);

        $decoded = json_decode(base64_decode($doc['document']), true);
        $this->assertEquals('2024-11-12', $decoded['issueDate']);
        $this->assertEquals(1000.50, $decoded['totalAmount']);

        // Verify response
        $this->assertEquals('DOC123', $response['documentId']);
        $this->assertEquals('PENDING', $response['status']);
    }

    /** @test */
    public function it_handles_validation_errors(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock validation error response
        $this->mockHandler->append(
            new Response(422, [], json_encode([
                'error' => 'validation_error',
                'message' => 'The given data was invalid.',
                'errors' => [
                    'issueDate' => ['The issue date field is required.'],
                ],
            ]))
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The given data was invalid.');

        $this->client->submitInvoice([
            'totalAmount' => 1000.50,
        ]);
    }

    /** @test */
    public function it_can_get_document_status(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'documentId' => 'DOC123',
                'status' => 'COMPLETED',
                'createdAt' => '2024-11-12T10:00:00Z',
            ]))
        );

        $response = $this->client->getDocumentStatus('DOC123');

        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/documents/DOC123', $request->getUri()->getPath());

        $this->assertEquals('COMPLETED', $response['status']);
    }

    /** @test */
    public function it_can_list_documents(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'data' => [
                    [
                        'documentId' => 'DOC123',
                        'status' => 'COMPLETED',
                    ],
                    [
                        'documentId' => 'DOC124',
                        'status' => 'PENDING',
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'total' => 2,
                ],
            ]))
        );

        $response = $this->client->listDocuments([
            'startDate' => '2024-01-01',
            'endDate' => '2024-12-31',
            'page' => 1,
        ]);

        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/documents', $request->getUri()->getPath());

        // Verify query parameters
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertEquals('2024-01-01', $query['startDate']);
        $this->assertEquals('2024-12-31', $query['endDate']);
        $this->assertEquals('1', $query['page']);

        // Verify response parsing
        $this->assertCount(2, $response['data']);
    }

    /** @test */
    public function it_can_cancel_document(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'documentId' => 'DOC123',
                'status' => 'CANCELLED',
                'cancelledAt' => '2024-11-12T11:00:00Z',
            ]))
        );

        $response = $this->client->cancelDocument('DOC123', 'Wrong information');

        $request = $this->getLastRequest();
        $this->assertEquals('PUT', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/documents/state/DOC123/state', $request->getUri()->getPath());

        // Verify request body
        $sentData = json_decode($this->container[1]['request']->getBody(), true);
        $this->assertEquals('Wrong information', $sentData['reason']);

        // Verify response
        $this->assertEquals('CANCELLED', $response['status']);
    }

    /** @test */
    public function it_can_get_document_pdf(): void
    {
        $this->mockSuccessfulAuthentication();

        $pdfContent = base64_encode('Fake PDF content');
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'content' => $pdfContent,
            ]))
        );

        $response = $this->client->getDocumentPdf('DOC123');

        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/documents/DOC123/pdf', $request->getUri()->getPath());
        $this->assertEquals('application/pdf', $request->getHeader('Accept')[0]);

        // Verify PDF content was decoded
        $this->assertEquals('Fake PDF content', $response);
    }

    /** @test */
    public function it_handles_rate_limiting(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(429, [], json_encode([
                'error' => 'too_many_requests',
                'message' => 'Rate limit exceeded',
                'retry_after' => 60,
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->client->getApiStatus();
    }

    /**
     * Helper method to mock successful authentication.
     */
    private function mockSuccessfulAuthentication(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'access_token' => 'test_token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]))
        );
    }

    /**
     * Helper method to get the last request made.
     */
    private function getLastRequest(): Request
    {
        return end($this->container)['request'];
    }

    /** @test */
    public function test_submit_refund_note_with_version(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(202, [], json_encode([
                'submissionUID' => 'SUB123456789',
                'documentId' => 'DOC123',
            ]))
        );

        $document = [
            'invoiceNumber' => 'RN-001',
            'issueDate' => '2024-01-01',
            // ... other document fields
        ];

        // Test with specific version
        $response = $this->client->submitRefundNote($document, '1.1');
        $this->assertEquals('SUB123456789', $response['submissionUID']);

        // Verify version was set correctly
        $request = json_decode($this->getLastRequest()->getBody()->getContents(), true);
        $this->assertEquals('1.1', $request['invoiceTypeCode']['listVersionID']);
        $this->assertEquals('04', $request['invoiceTypeCode']['value']);
    }

    /** @test */
    public function test_submit_refund_note_with_invalid_version(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unsupported refund note version');

        $this->client->submitRefundNote([], '2.0');
    }
}
