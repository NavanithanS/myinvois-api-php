<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class DocumentDetailsApiTest extends TestCase
{
    private $validUuid = 'F9D425P6DS7D8IU';

    private $validResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up valid response data
        $this->validResponse = [
            'uuid' => $this->validUuid,
            'submissionUid' => 'HJSD135P2S7D8IU',
            'longId' => 'LIJAF97HJJKH8298KHADH09908570FDKK9S2LSIUHB377373',
            'internalId' => 'PZ-234-A',
            'typeName' => 'invoice',
            'typeVersionName' => '1.0',
            'issuerTin' => 'C2584563200',
            'issuerName' => 'AMS Setia Jaya Sdn. Bhd.',
            'receiverId' => '201901234567',
            'receiverName' => 'Tech Solutions Sdn. Bhd.',
            'dateTimeIssued' => '2024-01-01T10:00:00Z',
            'dateTimeReceived' => '2024-01-01T10:05:00Z',
            'dateTimeValidated' => '2024-01-01T10:10:00Z',
            'totalExcludingTax' => '10.10',
            'totalDiscount' => '50.00',
            'totalNetAmount' => '100.70',
            'totalPayableAmount' => '124.09',
            'status' => 'Valid',
            'createdByUserId' => 'general.ams@supplier.com',
            'validationResults' => [
                'status' => 'Valid',
                'validationSteps' => [
                    [
                        'name' => 'GS1 code validator',
                        'status' => 'Valid',
                    ],
                ],
            ],
        ];
    }

    /** @test */
    public function it_can_get_document_details(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock document details response
        $this->mockHandler->append(
            new Response(200, [], json_encode($this->validResponse))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Retrieved document details successfully'));

        $this->client->setLogger($logger);
        $details = $this->client->getDocumentDetails($this->validUuid);

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString(
            "/api/v1.0/documents/{$this->validUuid}/details",
            $request->getUri()->getPath()
        );

        // Verify response mapping
        $this->assertEquals($this->validUuid, $details['uuid']);
        $this->assertEquals('HJSD135P2S7D8IU', $details['submissionUid']);
        $this->assertEquals('invoice', $details['typeName']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $details['dateTimeIssued']);
        $this->assertEquals(10.10, $details['totalExcludingTax']);
        $this->assertEquals('Valid', $details['status']);
    }

    /** @test */
    public function it_validates_uuid_format(): void
    {
        $invalidUuids = [
            '', // Empty
            'abc', // Too short
            'invalid-uuid-format', // Invalid format
            'F9D425P6DS7D8IUX', // Too long
            'f9d425p6ds7d8iu', // Lowercase
            'F9D425P6DS7D8I#', // Invalid characters
        ];

        foreach ($invalidUuids as $uuid) {
            try {
                $this->client->getDocumentDetails($uuid);
                $this->fail('Expected ValidationException for invalid UUID: ' . $uuid);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid UUID format', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_document_not_found(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock not found response
        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'not_found',
                'message' => 'Document not found or access not authorized',
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to retrieve document details'));

        $this->client->setLogger($logger);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Document not found or access not authorized');
        $this->expectExceptionCode(404);

        $this->client->getDocumentDetails($this->validUuid);
    }

    /** @test */
    public function it_can_get_validation_results(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock document details response with validation results
        $this->validResponse['validationResults'] = [
            'status' => 'Invalid',
            'validationSteps' => [
                [
                    'name' => 'Schema validator',
                    'status' => 'Valid',
                ],
                [
                    'name' => 'Business rules validator',
                    'status' => 'Invalid',
                    'error' => [
                        'code' => 'BR001',
                        'message' => 'Total amount mismatch',
                    ],
                ],
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($this->validResponse))
        );

        $results = $this->client->getDocumentValidationResults($this->validUuid);

        $this->assertEquals('Invalid', $results['status']);
        $this->assertCount(2, $results['validationSteps']);
        $this->assertEquals('Business rules validator', $results['validationSteps'][1]['name']);
        $this->assertEquals('Invalid', $results['validationSteps'][1]['status']);
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
        $this->expectExceptionMessage('Invalid response format from document details endpoint');

        $this->client->getDocumentDetails($this->validUuid);
    }

    /** @test */
    public function it_can_generate_document_public_url(): void
    {
        $this->mockSuccessfulAuthentication();

        $uuid = 'F9D425P6DS7D8IU';
        $longId = 'LIJAF97HJJKH8298KHADH09908570FDKK9S2LSIUHB377373';
        $baseUrl = 'https://myinvois.hasil.gov.my';

        // Mock API response for the document details
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'uuid' => $uuid,
                'longId' => $longId,
                'typeName' => 'invoice',
                'typeVersionName' => '1.0',
                'issuerTin' => 'C2584563200',
                'issuerName' => 'Test Company Sdn. Bhd.',
                'dateTimeIssued' => '2024-12-23T10:00:00Z',
                'dateTimeReceived' => '2024-12-23T10:05:00Z',
                'totalExcludingTax' => '1000.00',
                'totalDiscount' => '100.00',
                'totalNetAmount' => '900.00',
                'totalPayableAmount' => '954.00',
                'status' => 'Valid',
                'createdByUserId' => 'test@example.com',
            ]))
        );

        // Create a new client instance with the desired base URL
        $this->client = new \Nava\MyInvois\MyInvoisClient(
            'test_client',
            'test_secret',
            $this->app['cache']->store(),
            new GuzzleClient(['handler' => HandlerStack::create($this->mockHandler)]),
            '', 
            $baseUrl
          
        );

        $url = $this->client->generateDocumentPublicUrl($uuid, $longId);

        $expectedUrl = "{$baseUrl}/{$uuid}/share/{$longId}";
        $this->assertEquals($expectedUrl, $url);
    }

    /** @test */
    public function it_validates_long_id_format(): void
    {
        $invalidLongIds = [
            '', // Empty
            'abc', // Too short
            'invalid-long-id', // Invalid format
            'LIJAF97HJJKH', // Too short
            'lijaf97hjjkh8298khadh09908570fdkk9s2lsiuhb377373', // Lowercase
            'LIJAF97HJJKH8298KHADH09908570FDKK9S2LSIUHB377373#', // Invalid characters
        ];

        foreach ($invalidLongIds as $longId) {
            try {
                $this->client->generateDocumentPublicUrl($this->validUuid, $longId);
                $this->fail('Expected ValidationException for invalid long ID: ' . $longId);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid long ID format', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_maps_monetary_values_correctly(): void
    {
        $this->mockSuccessfulAuthentication();

        // Test various decimal formats
        $this->validResponse['totalExcludingTax'] = '1234.56';
        $this->validResponse['totalDiscount'] = '0.00';
        $this->validResponse['totalNetAmount'] = '1234.5600';
        $this->validResponse['totalPayableAmount'] = '1234';

        $this->mockHandler->append(
            new Response(200, [], json_encode($this->validResponse))
        );

        $details = $this->client->getDocumentDetails($this->validUuid);

        $this->assertIsFloat($details['totalExcludingTax']);
        $this->assertEquals(1234.56, $details['totalExcludingTax']);
        $this->assertEquals(0.0, $details['totalDiscount']);
        $this->assertEquals(1234.56, $details['totalNetAmount']);
        $this->assertEquals(1234.0, $details['totalPayableAmount']);
    }

    /** @test */
    public function it_handles_null_optional_fields(): void
    {
        $this->mockSuccessfulAuthentication();

        // Remove optional fields
        unset($this->validResponse['receiverId']);
        unset($this->validResponse['receiverName']);
        unset($this->validResponse['dateTimeValidated']);
        unset($this->validResponse['cancelDateTime']);
        unset($this->validResponse['rejectRequestDateTime']);
        unset($this->validResponse['documentStatusReason']);

        $this->mockHandler->append(
            new Response(200, [], json_encode($this->validResponse))
        );

        $details = $this->client->getDocumentDetails($this->validUuid);

        $this->assertNull($details['receiverId']);
        $this->assertNull($details['receiverName']);
        $this->assertNull($details['dateTimeValidated']);
        $this->assertNull($details['cancelDateTime']);
        $this->assertNull($details['rejectRequestDateTime']);
        $this->assertNull($details['documentStatusReason']);
    }

    /** @test */
    public function it_validates_date_formats(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->validResponse['dateTimeIssued'] = 'invalid-date';

        $this->mockHandler->append(
            new Response(200, [], json_encode($this->validResponse))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Failed to map document details');

        $this->client->getDocumentDetails($this->validUuid);
    }

    /** @test */
    public function it_validates_validation_results_structure(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->validResponse['validationResults'] = [
            'status' => 'InvalidStatus', // Invalid status
            'validationSteps' => [
                [
                    'status' => 'Valid', // Missing name
                ],
            ],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($this->validResponse))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Failed to map document details');

        $this->client->getDocumentDetails($this->validUuid);
    }
}
