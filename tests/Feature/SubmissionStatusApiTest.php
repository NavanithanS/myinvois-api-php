<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class SubmissionStatusApiTest extends TestCase
{
    private string $validSubmissionId = 'HJSD135P2S7D8IU';
    private array $successResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up a valid response structure
        $this->successResponse = [
            'submissionUid' => $this->validSubmissionId,
            'documentCount' => 2,
            'dateTimeReceived' => '2024-01-01T10:00:00Z',
            'overallStatus' => 'in progress',
            'documentSummary' => [
                [
                    'uuid' => 'DOC123',
                    'submissionUid' => $this->validSubmissionId,
                    'longId' => 'LIJAF97HJJKH8298KHADH09908570FDKK9S2LSIUHB377373',
                    'internalId' => 'INV-001',
                    'typeName' => 'invoice',
                    'typeVersionName' => '1.0',
                    'issuerTin' => 'C2584563200',
                    'issuerName' => 'Test Company Sdn Bhd',
                    'receiverId' => '201901234567',
                    'receiverName' => 'Receiver Company Sdn Bhd',
                    'dateTimeIssued' => '2024-01-01T09:00:00Z',
                    'dateTimeReceived' => '2024-01-01T09:01:00Z',
                    'dateTimeValidated' => '2024-01-01T09:02:00Z',
                    'totalExcludingTax' => 1000.00,
                    'totalDiscount' => 50.00,
                    'totalNetAmount' => 950.00,
                    'totalPayableAmount' => 1007.00,
                    'status' => 'Valid',
                    'cancelDateTime' => null,
                    'rejectRequestDateTime' => null,
                    'documentStatusReason' => null,
                    'createdByUserId' => 'test.user@company.com',
                ],
            ],
        ];
    }

    /** @test */
    public function it_can_get_submission_status(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode($this->successResponse))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Retrieved submission status'));

        $this->client->setLogger($logger);
        $response = $this->client->getSubmissionStatus($this->validSubmissionId);

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString(
            "/api/v1.0/documentsubmissions/{$this->validSubmissionId}",
            $request->getUri()->getPath()
        );

        // Verify response
        $this->assertEquals($this->validSubmissionId, $response['submissionUid']);
        $this->assertEquals(2, $response['documentCount']);
        $this->assertEquals('in progress', $response['overallStatus']);
        $this->assertCount(1, $response['documentSummary']);
    }

    /** @test */
    public function it_validates_submission_id(): void
    {
        $invalidIds = [
            '', // Empty
            'invalid@id', // Contains special characters
            'lowercase123', // Contains lowercase
            '12345', // Only numbers
        ];

        foreach ($invalidIds as $id) {
            try {
                $this->client->getSubmissionStatus($id);
                $this->fail('Expected ValidationException for invalid submission ID: ' . $id);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid submission ID format', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_pagination_parameters(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Page size must be between 1 and 100');

        $this->client->getSubmissionStatus($this->validSubmissionId, 1, 101);
    }

    /** @test */
    public function it_enforces_polling_interval(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->successResponse))
        );

        // First request should succeed
        $this->client->getSubmissionStatus($this->validSubmissionId);

        // Second immediate request should fail
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Please wait 3 seconds between status checks');

        $this->client->getSubmissionStatus($this->validSubmissionId);
    }

    /** @test */
    public function it_handles_invalid_response_format(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'invalid' => 'response',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid response format');

        $this->client->getSubmissionStatus($this->validSubmissionId);
    }

    /** @test */
    public function it_correctly_determines_submission_completion(): void
    {
        $incompleteStatuses = ['in progress'];
        $completeStatuses = ['valid', 'partially valid', 'invalid'];

        foreach ($incompleteStatuses as $status) {
            $response = $this->successResponse;
            $response['overallStatus'] = $status;
            $this->assertFalse($this->client->isSubmissionComplete($response));
        }

        foreach ($completeStatuses as $status) {
            $response = $this->successResponse;
            $response['overallStatus'] = $status;
            $this->assertTrue($this->client->isSubmissionComplete($response));
        }
    }

    /** @test */
    public function it_can_get_all_submission_documents(): void
    {
        $this->mockSuccessfulAuthentication();

        // First page response
        $page1Response = $this->successResponse;
        $page1Response['documentSummary'][0]['uuid'] = 'DOC1';
        $this->mockHandler->append(
            new Response(200, [], json_encode($page1Response))
        );

        // Second page response
        $page2Response = $this->successResponse;
        $page2Response['documentSummary'][0]['uuid'] = 'DOC2';
        $this->mockHandler->append(
            new Response(200, [], json_encode($page2Response))
        );

        // Empty final page
        $page3Response = $this->successResponse;
        $page3Response['documentSummary'] = [];
        $this->mockHandler->append(
            new Response(200, [], json_encode($page3Response))
        );

        $allDocuments = $this->client->getAllSubmissionDocuments($this->validSubmissionId);

        $this->assertCount(2, $allDocuments);
        $this->assertEquals('DOC1', $allDocuments[0]['uuid']);
        $this->assertEquals('DOC2', $allDocuments[1]['uuid']);
    }

    /** @test */
    public function it_handles_failed_documents(): void
    {
        $this->mockSuccessfulAuthentication();

        // Create response with a failed document
        $response = $this->successResponse;
        $response['overallStatus'] = 'partially valid';
        $response['documentSummary'][0]['status'] = 'Invalid';

        $this->mockHandler->append(
            new Response(200, [], json_encode($response))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Some documents in submission have failed'));

        $this->client->setLogger($logger);
        $result = $this->client->getSubmissionStatus($this->validSubmissionId);

        $this->assertEquals('partially valid', $result['overallStatus']);
        $this->assertEquals('Invalid', $result['documentSummary'][0]['status']);
    }

    /** @test */
    public function it_includes_pagination_parameters(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode($this->successResponse))
        );

        $this->client->getSubmissionStatus($this->validSubmissionId, 2, 50);

        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertEquals('2', $query['pageNo']);
        $this->assertEquals('50', $query['pageSize']);
    }

    /** @test */
    public function it_handles_api_errors(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'not_found',
                'message' => 'Submission not found',
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to retrieve submission status'));

        $this->client->setLogger($logger);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Submission not found');

        $this->client->getSubmissionStatus($this->validSubmissionId);
    }
}
