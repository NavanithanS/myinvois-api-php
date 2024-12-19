<?php

namespace Nava\MyInvois\Tests\Unit\Data;

use DateTimeImmutable;
use Nava\MyInvois\Data\DocumentType;
use Nava\MyInvois\Data\WorkflowParameter;
use Nava\MyInvois\Enums\DocumentTypeEnum;
use Nava\MyInvois\Tests\TestCase;

class DocumentTypeDataTest extends TestCase
{
    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validData = [
            'id' => 1,
            'invoiceTypeCode' => DocumentTypeEnum::INVOICE->value,
            'description' => 'Test Invoice',
            'activeFrom' => '2024-01-01T00:00:00Z',
            'activeTo' => null,
            'documentTypeVersions' => [],
            'workflowParameters' => [],
        ];
    }

    /** @test */
    public function it_creates_from_valid_data(): void
    {
        $type = DocumentType::fromArray($this->validData);

        $this->assertEquals(1, $type->id);
        $this->assertEquals(DocumentTypeEnum::INVOICE->value, $type->invoiceTypeCode);
        $this->assertEquals('Test Invoice', $type->description);
        $this->assertInstanceOf(DateTimeImmutable::class, $type->activeFrom);
        $this->assertNull($type->activeTo);
        $this->assertIsArray($type->documentTypeVersions);
        $this->assertIsArray($type->workflowParameters);
    }

    /** @test */
    public function it_creates_with_workflow_parameters(): void
    {
        $this->validData['workflowParameters'] = [
            [
                'id' => 124,
                'parameter' => 'rejectionDuration',
                'value' => 72,
                'activeFrom' => '2024-01-01T00:00:00Z',
                'activeTo' => null,
            ],
            [
                'id' => 125,
                'parameter' => 'submissionDuration',
                'value' => 48,
                'activeFrom' => '2024-01-01T00:00:00Z',
                'activeTo' => null,
            ],
        ];

        $type = DocumentType::fromArray($this->validData);

        $this->assertCount(2, $type->workflowParameters);
        $this->assertInstanceOf(WorkflowParameter::class, $type->workflowParameters[0]);
        $this->assertEquals('rejectionDuration', $type->workflowParameters[0]->parameter);
        $this->assertEquals(72, $type->workflowParameters[0]->value);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $requiredFields = ['id', 'invoiceTypeCode', 'description', 'activeFrom', 'documentTypeVersions'];

        foreach ($requiredFields as $field) {
            $invalidData = $this->validData;
            unset($invalidData[$field]);

            $this->expectException(\InvalidArgumentException::class);
            DocumentType::fromArray($invalidData);
        }
    }

    /** @test */
    public function it_validates_invoice_type_code(): void
    {
        $this->validData['invoiceTypeCode'] = 999; // Invalid code

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid invoice type code');

        DocumentType::fromArray($this->validData);
    }

    /** @test */
    public function it_correctly_determines_active_status(): void
    {
        $now = new DateTimeImmutable;
        $past = $now->modify('-1 year');
        $future = $now->modify('+1 year');

        // Active type (no end date)
        $type = new DocumentType([
            'id' => 1,
            'invoiceTypeCode' => DocumentTypeEnum::INVOICE->value,
            'description' => 'Active Type',
            'activeFrom' => $past,
            'activeTo' => null,
            'documentTypeVersions' => [],
            'workflowParameters' => [],
        ]);
        $this->assertTrue($type->isActive());

        // Active type (future end date)
        $type = new DocumentType([
            'id' => 1,
            'invoiceTypeCode' => DocumentTypeEnum::INVOICE->value,
            'description' => 'Active Type',
            'activeFrom' => $past,
            'activeTo' => $future,
            'documentTypeVersions' => [],
            'workflowParameters' => [],
        ]);
        $this->assertTrue($type->isActive());

        // Inactive type (past end date)
        $type = new DocumentType([
            'id' => 1,
            'invoiceTypeCode' => DocumentTypeEnum::INVOICE->value,
            'description' => 'Inactive Type',
            'activeFrom' => $past,
            'activeTo' => $past,
            'documentTypeVersions' => [],
            'workflowParameters' => [],
        ]);
        $this->assertFalse($type->isActive());
    }

    /** @test */
    public function it_returns_correct_enum_instance(): void
    {
        $type = DocumentType::fromArray($this->validData);
        $enum = $type->getEnum();

        $this->assertInstanceOf(DocumentTypeEnum::class, $enum);
        $this->assertEquals(DocumentTypeEnum::INVOICE, $enum);
        $this->assertEquals('Invoice', $enum->description());
    }
}
