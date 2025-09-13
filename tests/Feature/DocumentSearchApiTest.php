<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Data\DocumentSearchResult;
use Nava\MyInvois\Enums\DocumentStatusEnum;
use Nava\MyInvois\Enums\DocumentTypeEnum;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class DocumentSearchApiTest extends TestCase
{
    /** @test */
    public function it_can_search_documents_with_basic_filters(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock successful search response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'documents' => [
                    [
                        'uuid' => '42S512YACQBRSRHYKBXBTGQG22',
                        'submissionUID' => 'XYE60M8ENDWA7V9TKBXBTGQG10',
                        'longId' => 'YQH73576FY9VR57B',
                        'internalId' => 'PZ-234-A',
                        'typeName' => 'invoice',
                        'typeVersionName' => '1.0',
                        'issuerTin' => 'C2584563200',
                        'issuerName' => 'Test Company Sdn. Bhd.',
                        'dateTimeIssued' => '2024-01-01T10:00:00Z',
                        'dateTimeReceived' => '2024-01-01T10:05:00Z',
                        'dateTimeValidated' => '2024-01-01T10:10:00Z',
                        'totalSales' => 1000.00,
                        'totalDiscount' => 50.00,
                        'netAmount' => 950.00,
                        'total' => 1007.00,
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
        $logger->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                [$this->stringContains('Searching documents')],
                [$this->stringContains('Document search completed')]
            );

        $this->client->setLogger($logger);

        $filters = [
            'submissionDateFrom' => '2024-01-01T00:00:00Z',
            'submissionDateTo' => '2024-01-31T23:59:59Z',
            'status' => 'Valid',
            'pageSize' => 100,
            'pageNo' => 1,
        ];

        $result = $this->client->searchDocuments($filters);

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/documents/search', $request->getUri()->getPath());

        // Verify query parameters
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertEquals('2024-01-01T00:00:00Z', $query['submissionDateFrom']);
        $this->assertEquals('2024-01-31T23:59:59Z', $query['submissionDateTo']);
        $this->assertEquals('Valid', $query['status']);
        $this->assertEquals('100', $query['pageSize']);
        $this->assertEquals('1', $query['pageNo']);

        // Verify response structure
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertCount(1, $result['documents']);
        $this->assertInstanceOf(DocumentSearchResult::class, $result['documents'][0]);
        $this->assertEquals(1, $result['metadata']['totalCount']);
    }

    /** @test */
    public function it_requires_date_range_filters(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Either submission dates or issue dates must be provided');

        $this->client->searchDocuments([
            'status' => 'Valid',
        ]);
    }

    /** @test */
    public function it_validates_date_range_pairs(): void
    {
        // Test missing end date
        try {
            $this->client->searchDocuments([
                'submissionDateFrom' => '2024-01-01T00:00:00Z',
            ]);
            $this->fail('Expected ValidationException for missing end date');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Both start and end dates are required', $e->getMessage());
        }

        // Test invalid date order
        try {
            $this->client->searchDocuments([
                'submissionDateFrom' => '2024-02-01T00:00:00Z',
                'submissionDateTo' => '2024-01-01T00:00:00Z',
            ]);
            $this->fail('Expected ValidationException for invalid date order');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Start date must be before end date', $e->getMessage());
        }

        // Test exceeding max range
        try {
            $this->client->searchDocuments([
                'submissionDateFrom' => '2024-01-01T00:00:00Z',
                'submissionDateTo' => '2024-03-01T00:00:00Z',
            ]);
            $this->fail('Expected ValidationException for exceeding max range');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Date range cannot exceed 30 days', $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_page_size(): void
    {
        $invalidPageSizes = [0, -1, 101];

        foreach ($invalidPageSizes as $pageSize) {
            try {
                $this->client->searchDocuments([
                    'submissionDateFrom' => '2024-01-01T00:00:00Z',
                    'submissionDateTo' => '2024-01-31T23:59:59Z',
                    'pageSize' => $pageSize,
                ]);
                $this->fail('Expected ValidationException for invalid page size: '.$pageSize);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Page size must be between 1 and 100', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_invoice_direction(): void
    {
        try {
            $this->client->searchDocuments([
                'submissionDateFrom' => '2024-01-01T00:00:00Z',
                'submissionDateTo' => '2024-01-31T23:59:59Z',
                'invoiceDirection' => 'Invalid',
            ]);
            $this->fail('Expected ValidationException for invalid invoice direction');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Invalid invoice direction', $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_document_status(): void
    {
        try {
            $this->client->searchDocuments([
                'submissionDateFrom' => '2024-01-01T00:00:00Z',
                'submissionDateTo' => '2024-01-31T23:59:59Z',
                'status' => 'Invalid Status',
            ]);
            $this->fail('Expected ValidationException for invalid document status');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Invalid document status', $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_document_type(): void
    {
        try {
            $this->client->searchDocuments([
                'submissionDateFrom' => '2024-01-01T00:00:00Z',
                'submissionDateTo' => '2024-01-31T23:59:59Z',
                'documentType' => '99',
            ]);
            $this->fail('Expected ValidationException for invalid document type');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Invalid document type', $e->getMessage());
        }
    }

    /** @test */
    public function it_validates_search_query(): void
    {
        try {
            $this->client->searchDocuments([
                'submissionDateFrom' => '2024-01-01T00:00:00Z',
                'submissionDateTo' => '2024-01-31T23:59:59Z',
                'searchQuery' => 'Invalid@Query#',
            ]);
            $this->fail('Expected ValidationException for invalid search query');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Search query contains invalid characters', $e->getMessage());
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
        $this->expectExceptionMessage('Invalid response format from search endpoint');

        $this->client->searchDocuments([
            'submissionDateFrom' => '2024-01-01T00:00:00Z',
            'submissionDateTo' => '2024-01-31T23:59:59Z',
        ]);
    }

    /** @test */
    public function it_accepts_datetime_objects_for_dates(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'documents' => [],
                'metadata' => [
                    'totalPages' => 0,
                    'totalCount' => 0,
                ],
            ]))
        );

        $from = new \DateTimeImmutable('2024-01-01T00:00:00Z');
        $to = new \DateTimeImmutable('2024-01-31T23:59:59Z');

        $result = $this->client->searchDocuments([
            'submissionDateFrom' => $from,
            'submissionDateTo' => $to,
        ]);

        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertEquals($from->format('Y-m-d\TH:i:s\Z'), $query['submissionDateFrom']);
        $this->assertEquals($to->format('Y-m-d\TH:i:s\Z'), $query['submissionDateTo']);
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
            ->with($this->stringContains('Document search failed'));

        $this->client->setLogger($logger);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('An unexpected error occurred');

        $this->client->searchDocuments([
            'submissionDateFrom' => '2024-01-01T00:00:00Z',
            'submissionDateTo' => '2024-01-31T23:59:59Z',
        ]);
    }

    /** @test */
    public function it_can_search_with_all_filter_combinations(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'documents' => [],
                'metadata' => [
                    'totalPages' => 0,
                    'totalCount' => 0,
                ],
            ]))
        );

        $filters = [
            'uuid' => '42S512YACQBRSRHYKBXBTGQG22',
            'submissionDateFrom' => '2024-01-01T00:00:00Z',
            'submissionDateTo' => '2024-01-31T23:59:59Z',
            'issueDateFrom' => '2024-01-01T00:00:00Z',
            'issueDateTo' => '2024-01-31T23:59:59Z',
            'invoiceDirection' => 'Sent',
            'status' => DocumentStatusEnum::VALID,
            'documentType' => DocumentTypeEnum::INVOICE,
            'searchQuery' => 'test-query',
            'pageSize' => 50,
            'pageNo' => 1,
        ];

        $result = $this->client->searchDocuments($filters);

        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        // Verify all parameters were properly included
        foreach ($filters as $key => $value) {
            $this->assertArrayHasKey($key, $query);
            $this->assertEquals($value, $query[$key]);
        }
    }
}
