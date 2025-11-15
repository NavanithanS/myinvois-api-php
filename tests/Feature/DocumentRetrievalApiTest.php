<?php

namespace Nava\MyInvois\Tests\Feature;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\MyInvoisClient;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class DocumentRetrievalApiTest extends TestCase
{
    /** @test */
    public function it_can_retrieve_document(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock successful document response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'uuid' => 'F9D425P6DS7D8IU',
                'submissionUid' => 'HJSD135P2S7D8IU',
                'longId' => 'LIJAF97HJJKH8298KHADH09908570FDKK9S2LSIUHB377373',
                'internalId' => 'PZ-234-A',
                'typeName' => 'invoice',
                'typeVersionName' => '1.0',
                'issuerTin' => 'C2584563200',
                'issuerName' => 'AMS Setia Jaya Sdn. Bhd.',
                'receiverId' => '201901234567',
                'receiverName' => 'Receiver Company Sdn. Bhd.',
                'dateTimeIssued' => '2024-01-01T10:00:00Z',
                'dateTimeReceived' => '2024-01-01T10:01:00Z',
                'dateTimeValidated' => '2024-01-01T10:02:00Z',
                'totalExcludingTax' => '1000.00',
                'totalDiscount' => '100.00',
                'totalNetAmount' => '900.00',
                'totalPayableAmount' => '954.00',
                'status' => 'Valid',
                'createdByUserId' => 'general.ams@supplier.com',
                'document' => [
                    'content' => json_encode([
                        'invoiceNumber' => 'INV-001',
                        'items' => [],
                    ]),
                ],
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Retrieved document successfully'));

        $this->client->setLogger($logger);
        $document = $this->client->getDocument('F9D425P6DS7D8IU');

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString(
            '/api/v1.0/documents/F9D425P6DS7D8IU/raw',
            $request->getUri()->getPath()
        );

        // Verify response mapping
        $this->assertEquals('F9D425P6DS7D8IU', $document['uuid']);
        $this->assertEquals('invoice', $document['typeName']);
        $this->assertEquals('1.0', $document['typeVersionName']);
        $this->assertEquals('C2584563200', $document['issuerTin']);
        $this->assertEquals('Valid', $document['status']);

        // Verify date conversions
        $this->assertInstanceOf(DateTimeImmutable::class, $document['dateTimeIssued']);
        $this->assertInstanceOf(DateTimeImmutable::class, $document['dateTimeReceived']);
        $this->assertInstanceOf(DateTimeImmutable::class, $document['dateTimeValidated']);

        // Verify numeric conversions
        $this->assertEquals(1000.00, $document['totalExcludingTax']);
        $this->assertEquals(100.00, $document['totalDiscount']);
        $this->assertEquals(900.00, $document['totalNetAmount']);
        $this->assertEquals(954.00, $document['totalPayableAmount']);
    }

    /** @test */
    public function it_validates_uuid_format(): void
    {
        $invalidUuids = [
            '', // Empty
            'abc', // Too short
            '123456789012345678901', // Too long
            'F9D425P6DS7D8I!', // Invalid characters
            'f9d425p6ds7d8iu', // Lowercase
        ];

        foreach ($invalidUuids as $uuid) {
            try {
                $this->client->getDocument($uuid);
                $this->fail('Expected ValidationException for invalid UUID: '.$uuid);
            } catch (ValidationException $e) {
                $this->assertStringContainsString(
                    'UUID must be exactly 15 alphanumeric characters',
                    $e->getMessage()
                );
            }
        }
    }

    /** @test */
    public function it_handles_invalid_status_error(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'not_found',
                'message' => 'Document exists but has invalid status',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Document exists but has invalid status');
        $this->expectExceptionCode(404);

        $this->client->getDocument('F9D425P6DS7D8IU');
    }

    /** @test */
    public function it_handles_submitted_status_error(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'not_found',
                'message' => 'Document exists but is in submitted status',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Document exists but is still in submitted status');
        $this->expectExceptionCode(404);

        $this->client->getDocument('F9D425P6DS7D8IU');
    }

    /** @test */
    public function it_handles_authorization_error(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(403, [], json_encode([
                'error' => 'forbidden',
                'message' => 'Not authorized to access this document',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Not authorized to access this document');
        $this->expectExceptionCode(403);

        $this->client->getDocument('F9D425P6DS7D8IU');
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
        $this->expectExceptionMessage('Invalid response format from document endpoint');

        $this->client->getDocument('F9D425P6DS7D8IU');
    }

    /** @test */
    public function it_generates_shareable_url(): void
    {
        $uuid = 'F9D425P6DS7D8IU';
        $longId = 'LIJAF97HJJKH8298KHADH09908570FDKK9S2LSIUHB377373';

        $url = $this->client->getDocumentShareableUrl($uuid, $longId);

        $expectedUrl = MyInvoisClient::SANDBOX_URL."/{$uuid}/share/{$longId}";
        $this->assertEquals($expectedUrl, $url);
    }

    /** @test */
    public function it_validates_long_id_format(): void
    {
        $uuid = 'F9D425P6DS7D8IU';
        $invalidLongIds = [
            '', // Empty
            '123', // Too short
            'invalid-chars!@#', // Invalid characters
            str_repeat('A', 39), // Too short
        ];

        foreach ($invalidLongIds as $longId) {
            try {
                $this->client->getDocumentShareableUrl($uuid, $longId);
                $this->fail('Expected ValidationException for invalid long ID: '.$longId);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid long ID format', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_optional_document_fields(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock response without optional fields
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'uuid' => 'F9D425P6DS7D8IU',
                'submissionUid' => 'HJSD135P2S7D8IU',
                'internalId' => 'PZ-234-A',
                'typeName' => 'invoice',
                'typeVersionName' => '1.0',
                'issuerTin' => 'C2584563200',
                'issuerName' => 'AMS Setia Jaya Sdn. Bhd.',
                'dateTimeIssued' => '2024-01-01T10:00:00Z',
                'dateTimeReceived' => '2024-01-01T10:01:00Z',
                'totalExcludingTax' => '1000.00',
                'totalDiscount' => '100.00',
                'totalNetAmount' => '900.00',
                'totalPayableAmount' => '954.00',
                'status' => 'Valid',
                'createdByUserId' => 'general.ams@supplier.com',
                'document' => [
                    'content' => json_encode(['invoiceNumber' => 'INV-001']),
                ],
            ]))
        );

        $document = $this->client->getDocument('F9D425P6DS7D8IU');

        // Verify optional fields are null/not set
        $this->assertArrayNotHasKey('receiverId', $document);
        $this->assertArrayNotHasKey('receiverName', $document);
        $this->assertArrayNotHasKey('dateTimeValidated', $document);
        $this->assertArrayNotHasKey('cancelDateTime', $document);
        $this->assertArrayNotHasKey('rejectRequestDateTime', $document);
        $this->assertArrayNotHasKey('documentStatusReason', $document);
    }

    /** @test */
    public function it_handles_cancelled_document(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock cancelled document response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'uuid' => 'F9D425P6DS7D8IU',
                'status' => 'Cancelled',
                'cancelDateTime' => '2024-01-02T10:00:00Z',
                'documentStatusReason' => 'Wrong buyer details',
                // ... other required fields ...
                'submissionUid' => 'HJSD135P2S7D8IU',
                'internalId' => 'PZ-234-A',
                'typeName' => 'invoice',
                'typeVersionName' => '1.0',
                'issuerTin' => 'C2584563200',
                'issuerName' => 'AMS Setia Jaya Sdn. Bhd.',
                'dateTimeIssued' => '2024-01-01T10:00:00Z',
                'dateTimeReceived' => '2024-01-01T10:01:00Z',
                'totalExcludingTax' => '1000.00',
                'totalDiscount' => '100.00',
                'totalNetAmount' => '900.00',
                'totalPayableAmount' => '954.00',
                'createdByUserId' => 'general.ams@supplier.com',
                'document' => [
                    'content' => json_encode(['invoiceNumber' => 'INV-001']),
                ],
            ]))
        );

        $document = $this->client->getDocument('F9D425P6DS7D8IU');

        $this->assertEquals('Cancelled', $document['status']);
        $this->assertInstanceOf(DateTimeImmutable::class, $document['cancelDateTime']);
        $this->assertEquals('Wrong buyer details', $document['documentStatusReason']);
    }
}
