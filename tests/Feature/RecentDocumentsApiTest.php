<?php

namespace Nava\MyInvois\Tests\Feature;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class RecentDocumentsApiTest extends TestCase
{
    /** @test */
    public function it_can_get_recent_documents(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock recent documents response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    [
                        'uuid' => '42S512YACQBRSRHYKBXBTGQG22',
                        'submissionUID' => 'XYE60M8ENDWA7V9TKBXBTGQG10',
                        'longId' => 'YQH73576FY9VR57B',
                        'internalId' => 'PZ-234-A',
                        'typeName' => 'invoice',
                        'typeVersionName' => '1.0',
                        'issuerTin' => 'C2584563200',
                        'receiverId' => '201901234567',
                        'receiverName' => 'AMS Setia Jaya Sdn. Bhd.',
                        'dateTimeIssued' => '2024-01-01T13:15:00Z',
                        'dateTimeReceived' => '2024-01-01T14:20:00Z',
                        'dateTimeValidated' => '2024-01-01T14:20:00Z',
                        'totalSales' => 10.10,
                        'totalDiscount' => 50.00,
                        'netAmount' => 100.70,
                        'total' => 124.09,
                        'status' => 'Valid',
                    ],
                ],
                'metadata' => [
                    'totalPages' => 1,
                    'totalCount' => 1,
                ],
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Retrieved recent documents successfully'));

        $this->client->setLogger($logger);
        $response = $this->client->getRecentDocuments();

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/documents/recent', $request->getUri()->getPath());

        // Verify response structure
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('metadata', $response);
        $this->assertCount(1, $response['result']);
        $this->assertEquals(1, $response['metadata']['totalCount']);
        $this->assertEquals(1, $response['metadata']['totalPages']);

        // Verify document data
        $document = $response['result'][0];
        $this->assertEquals('42S512YACQBRSRHYKBXBTGQG22', $document['uuid']);
        $this->assertEquals('Valid', $document['status']);
        $this->assertEquals('C2584563200', $document['issuerTin']);
    }

    /** @test */
    public function it_can_filter_documents_by_date_range(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [],
                'metadata' => ['totalPages' => 0, 'totalCount' => 0],
            ]))
        );

        $filters = [
            'submissionDateFrom' => '2024-01-01T00:00:00Z',
            'submissionDateTo' => '2024-01-31T23:59:59Z',
        ];

        $response = $this->client->getRecentDocuments($filters);

        // Verify query parameters
        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertEquals('2024-01-01T00:00:00Z', $query['submissionDateFrom']);
        $this->assertEquals('2024-01-31T23:59:59Z', $query['submissionDateTo']);
    }

    /** @test */
    public function it_validates_page_size(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Page size must be between 1 and 100');

        $this->client->getRecentDocuments(['pageSize' => 101]);
    }

    /** @test */
    public function it_validates_invoice_direction(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invoice direction must be either "Sent" or "Received"');

        $this->client->getRecentDocuments(['invoiceDirection' => 'Invalid']);
    }

    /** @test */
    public function it_validates_document_status(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid document status');

        $this->client->getRecentDocuments(['status' => 'InvalidStatus']);
    }

    /** @test */
    public function it_validates_id_types(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid receiver ID type');

        $this->client->getRecentDocuments(['receiverIdType' => 'INVALID']);
    }

    /** @test */
    public function it_validates_tin_format(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid receiver TIN format');

        $this->client->getRecentDocuments(['receiverTin' => 'invalid-tin']);
    }

    /** @test */
    public function it_validates_date_range(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid submission date range');

        $this->client->getRecentDocuments([
            'submissionDateFrom' => '2024-02-01T00:00:00Z',
            'submissionDateTo' => '2024-01-01T00:00:00Z',
        ]);
    }

    /** @test */
    public function it_validates_date_window_limit(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Date range cannot exceed 31 days');

        $this->client->getRecentDocuments([
            'submissionDateFrom' => '2024-01-01T00:00:00Z',
            'submissionDateTo' => '2024-03-01T00:00:00Z',
        ]);
    }

    /** @test */
    public function it_validates_sent_direction_filters(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Issuer filters cannot be used with Sent direction');

        $this->client->getRecentDocuments([
            'invoiceDirection' => 'Sent',
            'issuerId' => '201901234567',
            'issuerIdType' => 'BRN',
        ]);
    }

    /** @test */
    public function it_validates_received_direction_filters(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Receiver filters cannot be used with Received direction');

        $this->client->getRecentDocuments([
            'invoiceDirection' => 'Received',
            'receiverId' => '201901234567',
            'receiverIdType' => 'BRN',
        ]);
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
        $this->expectExceptionMessage('Invalid response format from recent documents endpoint');

        $this->client->getRecentDocuments();
    }

    /** @test */
    public function it_handles_api_errors(): void
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
            ->with($this->stringContains('Failed to retrieve recent documents'));

        $this->client->setLogger($logger);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('An unexpected error occurred');

        $this->client->getRecentDocuments();
    }

    /** @test */
    public function it_accepts_datetime_objects_for_date_range(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [],
                'metadata' => ['totalPages' => 0, 'totalCount' => 0],
            ]))
        );

        $dateFrom = new DateTimeImmutable('2024-01-01T00:00:00Z');
        $dateTo = new DateTimeImmutable('2024-01-31T23:59:59Z');

        $response = $this->client->getRecentDocuments([
            'submissionDateFrom' => $dateFrom,
            'submissionDateTo' => $dateTo,
        ]);

        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertEquals($dateFrom->format('Y-m-d\TH:i:s\Z'), $query['submissionDateFrom']);
        $this->assertEquals($dateTo->format('Y-m-d\TH:i:s\Z'), $query['submissionDateTo']);
    }

    /** @test */
    public function it_requires_id_value_when_id_type_is_provided(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Receiver ID is required when ID type is provided');

        $this->client->getRecentDocuments([
            'receiverIdType' => 'BRN',
            // Missing receiverId
        ]);
    }

    /** @test */
    public function it_handles_pagination(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock paginated response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    [
                        'uuid' => '42S512YACQBRSRHYKBXBTGQG22',
                        'status' => 'Valid',
                    ],
                ],
                'metadata' => [
                    'totalPages' => 3,
                    'totalCount' => 25,
                ],
            ]))
        );

        $response = $this->client->getRecentDocuments([
            'pageNo' => 1,
            'pageSize' => 10,
        ]);

        // Verify pagination parameters
        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertEquals('1', $query['pageNo']);
        $this->assertEquals('10', $query['pageSize']);

        // Verify pagination metadata
        $this->assertEquals(3, $response['metadata']['totalPages']);
        $this->assertEquals(25, $response['metadata']['totalCount']);
    }
}
