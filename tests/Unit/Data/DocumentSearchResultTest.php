<?php

namespace Nava\MyInvois\Tests\Unit\Data;

use DateTimeImmutable;
use Nava\MyInvois\Data\DocumentSearchResult;
use Nava\MyInvois\Enums\DocumentStatusEnum;
use Nava\MyInvois\Enums\DocumentTypeEnum;
use Nava\MyInvois\Tests\TestCase;

class DocumentSearchResultTest extends TestCase
{
    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validData = [
            'uuid' => '42S512YACQBRSRHYKBXBTGQG22',
            'submissionUID' => 'XYE60M8ENDWA7V9TKBXBTGQG10',
            'longId' => 'YQH73576FY9VR57B',
            'internalId' => 'PZ-234-A',
            'typeName' => '01',
            'typeVersionName' => '1.0',
            'issuerTin' => 'C2584563200',
            'issuerName' => 'Test Company Sdn. Bhd.',
            'receiverId' => '770625015324',
            'receiverName' => 'Buyer Company Sdn. Bhd.',
            'dateTimeIssued' => '2024-01-01T10:00:00Z',
            'dateTimeReceived' => '2024-01-01T10:05:00Z',
            'dateTimeValidated' => '2024-01-01T10:10:00Z',
            'totalSales' => 1000.00,
            'totalDiscount' => 50.00,
            'netAmount' => 950.00,
            'total' => 1007.00,
            'status' => 'Valid',
            'createdByUserId' => 'C1234567890:9e21b10c-41c4-9323-c590-95abcb6e4e4d',
            'supplierTIN' => 'C2584563200',
            'supplierName' => 'Test Company Sdn. Bhd.',
            'submissionChannel' => 'ERP',
            'buyerName' => 'Buyer Company Sdn. Bhd.',
            'buyerTIN' => 'C1234567890',
        ];
    }

    /** @test */
    public function it_creates_from_valid_data(): void
    {
        $result = DocumentSearchResult::fromArray($this->validData);

        $this->assertEquals('42S512YACQBRSRHYKBXBTGQG22', $result->uuid);
        $this->assertEquals('XYE60M8ENDWA7V9TKBXBTGQG10', $result->submissionUID);
        $this->assertEquals('YQH73576FY9VR57B', $result->longId);
        $this->assertEquals('PZ-234-A', $result->internalId);
        $this->assertEquals('01', $result->typeName);
        $this->assertEquals('1.0', $result->typeVersionName);
        $this->assertEquals('C2584563200', $result->issuerTin);
        $this->assertEquals('Test Company Sdn. Bhd.', $result->issuerName);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->dateTimeIssued);
        $this->assertEquals(1000.00, $result->totalSales);
        $this->assertEquals(50.00, $result->totalDiscount);
        $this->assertEquals(950.00, $result->netAmount);
        $this->assertEquals(1007.00, $result->total);
        $this->assertEquals('Valid', $result->status);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $requiredFields = [
            'uuid', 'submissionUID', 'longId', 'internalId', 'typeName',
            'typeVersionName', 'issuerTin', 'issuerName', 'dateTimeIssued',
            'dateTimeReceived', 'dateTimeValidated', 'totalSales',
            'totalDiscount', 'netAmount', 'total', 'status', 'createdByUserId',
            'supplierTIN', 'supplierName', 'submissionChannel', 'buyerName', 'buyerTIN',
        ];

        foreach ($requiredFields as $field) {
            $invalidData = $this->validData;
            unset($invalidData[$field]);

            try {
                DocumentSearchResult::fromArray($invalidData);
                $this->fail("Expected exception for missing field: $field");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString("$field is required", $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_numeric_values(): void
    {
        $numericFields = ['totalSales', 'totalDiscount', 'netAmount', 'total'];

        foreach ($numericFields as $field) {
            // Test non-numeric value
            $invalidData = $this->validData;
            $invalidData[$field] = 'not-a-number';

            try {
                DocumentSearchResult::fromArray($invalidData);
                $this->fail("Expected exception for non-numeric $field");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must be numeric', $e->getMessage());
            }

            // Test negative value
            $invalidData[$field] = -100;

            try {
                DocumentSearchResult::fromArray($invalidData);
                $this->fail("Expected exception for negative $field");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('cannot be negative', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_tin_formats(): void
    {
        $tinFields = ['issuerTin', 'supplierTIN', 'buyerTIN'];
        $invalidTins = [
            'invalid',
            'D1234567890', // Wrong prefix
            'C123456789', // Too short
            'C12345678901', // Too long
            'CXXXXXXXXXX', // Non-numeric
        ];

        foreach ($tinFields as $field) {
            foreach ($invalidTins as $invalidTin) {
                $invalidData = $this->validData;
                $invalidData[$field] = $invalidTin;

                try {
                    DocumentSearchResult::fromArray($invalidData);
                    $this->fail("Expected exception for invalid $field: $invalidTin");
                } catch (\InvalidArgumentException $e) {
                    $this->assertStringContainsString('must start with C followed by 10 digits', $e->getMessage());
                }
            }
        }
    }

    /** @test */
    public function it_validates_submission_channel(): void
    {
        $invalidData = $this->validData;
        $invalidData['submissionChannel'] = 'InvalidChannel';

        try {
            DocumentSearchResult::fromArray($invalidData);
            $this->fail('Expected exception for invalid submission channel');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid submission channel', $e->getMessage());
        }
    }

    /** @test */
    public function it_handles_optional_fields(): void
    {
        $optionalFields = [
            'receiverId',
            'receiverName',
            'cancelDateTime',
            'rejectRequestDateTime',
            'documentStatusReason',
            'intermediaryName',
            'intermediaryTIN',
        ];

        foreach ($optionalFields as $field) {
            $data = $this->validData;
            unset($data[$field]);

            $result = DocumentSearchResult::fromArray($data);
            $this->assertNull($result->$field, "Optional field $field should be null when not provided");
        }
    }

    /** @test */
    public function it_converts_to_document_status_enum(): void
    {
        $result = DocumentSearchResult::fromArray($this->validData);
        $status = $result->getStatus();

        $this->assertInstanceOf(DocumentStatusEnum::class, $status);
        $this->assertEquals(DocumentStatusEnum::VALID, $status);
    }

    /** @test */
    public function it_detects_document_status(): void
    {
        // Test valid document
        $result = DocumentSearchResult::fromArray($this->validData);
        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isCancelled());

        // Test cancelled document
        $data = $this->validData;
        $data['status'] = 'Cancelled';
        $data['cancelDateTime'] = '2024-01-02T10:00:00Z';

        $result = DocumentSearchResult::fromArray($data);
        $this->assertTrue($result->isCancelled());
        $this->assertFalse($result->isValid());
    }

    /** @test */
    public function it_converts_to_document_type_enum(): void
    {
        $result = DocumentSearchResult::fromArray($this->validData);
        $type = $result->getDocumentType();

        $this->assertInstanceOf(DocumentTypeEnum::class, $type);
        $this->assertEquals(DocumentTypeEnum::INVOICE, $type);
    }

    /** @test */
    public function it_calculates_tax_amount(): void
    {
        $result = DocumentSearchResult::fromArray($this->validData);
        $taxAmount = $result->getTaxAmount();

        // total (1007.00) - netAmount (950.00) = 57.00
        $this->assertEquals(57.00, $taxAmount);
    }

    /** @test */
    public function it_detects_erp_submission(): void
    {
        // Test ERP submission
        $result = DocumentSearchResult::fromArray($this->validData);
        $this->assertTrue($result->isErpSubmission());

        // Test portal submission
        $data = $this->validData;
        $data['submissionChannel'] = 'Invoicing Portal';

        $result = DocumentSearchResult::fromArray($data);
        $this->assertFalse($result->isErpSubmission());
    }

    /** @test */
    public function it_detects_intermediary_submission(): void
    {
        // Test without intermediary
        $result = DocumentSearchResult::fromArray($this->validData);
        $this->assertFalse($result->hasIntermediary());

        // Test with intermediary
        $data = $this->validData;
        $data['intermediaryTIN'] = 'C9876543210';
        $data['intermediaryName'] = 'Intermediary Company';

        $result = DocumentSearchResult::fromArray($data);
        $this->assertTrue($result->hasIntermediary());
    }

    /** @test */
    public function it_generates_public_url(): void
    {
        $result = DocumentSearchResult::fromArray($this->validData);
        $baseUrl = 'https://myinvois.hasil.gov.my';

        $expectedUrl = sprintf(
            '%s/%s/share/%s',
            $baseUrl,
            $result->uuid,
            $result->longId
        );

        $this->assertEquals($expectedUrl, $result->getPublicUrl($baseUrl));
    }

    /** @test */
    public function it_handles_json_serialization(): void
    {
        $result = DocumentSearchResult::fromArray($this->validData);
        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertEquals($result->uuid, $decoded['uuid']);
        $this->assertEquals($result->submissionUID, $decoded['submissionUID']);
        $this->assertEquals(
            $result->dateTimeIssued->format('c'),
            $decoded['dateTimeIssued']
        );
        $this->assertEquals($result->totalSales, $decoded['totalSales']);
        $this->assertEquals($result->status, $decoded['status']);
    }
}
