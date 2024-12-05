<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class DocumentRejectionApiTest extends TestCase
{
    /** @test */
    public function it_can_reject_document_successfully(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock successful rejection response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'uuid' => 'F9D425P6DS7D8IU',
                'status' => 'Requested for Rejection',
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Document rejection successful'));

        $this->client->setLogger($logger);
        $response = $this->client->rejectDocument(
            'F9D425P6DS7D8IU',
            'Wrong invoice details'
        );

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('PUT', $request->getMethod());
        $this->assertStringContainsString(
            '/api/v1.0/documents/state/F9D425P6DS7D8IU/state',
            $request->getUri()->getPath()
        );

        // Verify request body
        $requestBody = json_decode((string) $request->getBody(), true);
        $this->assertEquals('Rejected', $requestBody['status']);
        $this->assertEquals('Wrong invoice details', $requestBody['reason']);

        // Verify response
        $this->assertEquals('F9D425P6DS7D8IU', $response['uuid']);
        $this->assertEquals('Requested for Rejection', $response['status']);
    }

    /** @test */
    public function it_validates_rejection_parameters(): void
    {
        // Test empty document ID
        try {
            $this->client->rejectDocument('', 'Test reason');
            $this->fail('Expected ValidationException for empty document ID');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Document ID is required', $e->getMessage());
            $this->assertArrayHasKey('documentId', $e->getErrors());
        }

        // Test empty reason
        try {
            $this->client->rejectDocument('F9D425P6DS7D8IU', '');
            $this->fail('Expected ValidationException for empty reason');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Rejection reason is required', $e->getMessage());
            $this->assertArrayHasKey('reason', $e->getErrors());
        }

        // Test reason too long
        $longReason = str_repeat('a', 501);
        try {
            $this->client->rejectDocument('F9D425P6DS7D8IU', $longReason);
            $this->fail('Expected ValidationException for long reason');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Rejection reason is too long', $e->getMessage());
            $this->assertArrayHasKey('reason', $e->getErrors());
        }
    }

    /** @test */
    public function it_handles_rejection_period_expired(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(400, [], json_encode([
                'error' => 'OperationPeriodOver',
                'message' => 'Rejection period has expired',
            ]))
        );

        try {
            $this->client->rejectDocument('F9D425P6DS7D8IU', 'Test reason');
            $this->fail('Expected ValidationException for expired rejection period');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Rejection period has expired', $e->getMessage());
            $this->assertArrayHasKey('time', $e->getErrors());
        }
    }

    /** @test */
    public function it_handles_incorrect_document_state(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(400, [], json_encode([
                'error' => 'IncorrectState',
                'message' => 'Document must be in valid state',
            ]))
        );

        try {
            $this->client->rejectDocument('F9D425P6DS7D8IU', 'Test reason');
            $this->fail('Expected ValidationException for incorrect state');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Document cannot be rejected', $e->getMessage());
            $this->assertArrayHasKey('state', $e->getErrors());
        }
    }

    /** @test */
    public function it_handles_active_referencing_documents(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(400, [], json_encode([
                'error' => 'ActiveReferencingDocuments',
                'message' => 'Document has active references',
            ]))
        );

        try {
            $this->client->rejectDocument('F9D425P6DS7D8IU', 'Test reason');
            $this->fail('Expected ValidationException for active references');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Document has active references', $e->getMessage());
            $this->assertArrayHasKey('references', $e->getErrors());
        }
    }

    /** @test */
    public function it_handles_unauthorized_rejection(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(403, [], json_encode([
                'error' => 'Forbidden',
                'message' => 'Not authorized to reject this document',
            ]))
        );

        try {
            $this->client->rejectDocument('F9D425P6DS7D8IU', 'Test reason');
            $this->fail('Expected ValidationException for unauthorized rejection');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Not authorized to reject document', $e->getMessage());
            $this->assertArrayHasKey('auth', $e->getErrors());
        }
    }

    /** @test */
    public function it_handles_invalid_response_format(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'invalid' => 'format',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid response format from rejection endpoint');

        $this->client->rejectDocument('F9D425P6DS7D8IU', 'Test reason');
    }

    /** @test */
    public function it_logs_rejection_errors(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(500, [], json_encode([
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred',
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Document rejection failed'),
                $this->callback(function ($context) {
                    return isset($context['document_id'])
                    && isset($context['status_code'])
                    && isset($context['error']);
                })
            );

        $this->client->setLogger($logger);

        $this->expectException(ApiException::class);
        $this->client->rejectDocument('F9D425P6DS7D8IU', 'Test reason');
    }
}
