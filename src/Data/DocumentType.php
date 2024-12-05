<?php

namespace Nava\MyInvois\Data;

use DateTimeImmutable;
use Nava\MyInvois\Enums\DocumentType as DocumentTypeEnum;
use Spatie\DataTransferObject\DataTransferObject;
use Webmozart\Assert\Assert;

/**
 * Represents a document type in the MyInvois system.
 */
class DocumentType extends DataTransferObject
{
    public int $id;
    public int $invoiceTypeCode;
    public string $description;
    public DateTimeImmutable $activeFrom;
    public ?DateTimeImmutable $activeTo;
    /** @var DocumentTypeVersion[] */
    public array $documentTypeVersions;
    /** @var WorkflowParameter[] */
    public array $workflowParameters;

    /**
     * Create a new DocumentType instance from an array.
     *
     * @param array $data Raw data from API
     * @throws \InvalidArgumentException If data validation fails
     */
    public static function fromArray(array $data): self
    {
        Assert::keyExists($data, 'id', 'Document type must have an ID');
        Assert::keyExists($data, 'invoiceTypeCode', 'Document type must have an invoice type code');
        Assert::keyExists($data, 'description', 'Document type must have a description');
        Assert::keyExists($data, 'activeFrom', 'Document type must have an active from date');
        Assert::keyExists($data, 'documentTypeVersions', 'Document type must have versions array');
        Assert::isArray($data['documentTypeVersions'], 'Document type versions must be an array');

        // Validate invoice type code matches enum
        $validCodes = array_map(fn($case) => $case->value, DocumentTypeEnum::cases());
        Assert::inArray($data['invoiceTypeCode'], $validCodes, 'Invalid invoice type code');

        return new self([
            'id' => $data['id'],
            'invoiceTypeCode' => $data['invoiceTypeCode'],
            'description' => $data['description'],
            'activeFrom' => new DateTimeImmutable($data['activeFrom']),
            'activeTo' => isset($data['activeTo']) ? new DateTimeImmutable($data['activeTo']) : null,
            'documentTypeVersions' => array_map(
                fn(array $version) => DocumentTypeVersion::fromArray($version),
                $data['documentTypeVersions']
            ),
            'workflowParameters' => array_map(
                fn(array $param) => WorkflowParameter::fromArray($param),
                $data['workflowParameters'] ?? []
            ),
        ]);
    }

    /**
     * Check if the document type is currently active.
     */
    public function isActive(): bool
    {
        $now = new DateTimeImmutable();
        return $this->activeFrom <= $now && (null === $this->activeTo || $this->activeTo > $now);
    }

    /**
     * Get all active versions of this document type.
     *
     * @return DocumentTypeVersion[]
     */
    public function getActiveVersions(): array
    {
        return array_filter($this->documentTypeVersions, fn($version) => $version->isActive());
    }

    /**
     * Get the latest active version of this document type.
     */
    public function getLatestVersion(): ?DocumentTypeVersion
    {
        $activeVersions = $this->getActiveVersions();
        if (empty($activeVersions)) {
            return null;
        }

        return array_reduce(
            $activeVersions,
            fn($carry, $version) => null === $carry || $version->versionNumber > $carry->versionNumber ? $version : $carry
        );
    }

    /**
     * Get workflow parameter by name.
     */
    public function getWorkflowParameter(string $name): ?WorkflowParameter
    {
        foreach ($this->workflowParameters as $param) {
            if ($param->parameter === $name && $param->isActive()) {
                return $param;
            }
        }
        return null;
    }

    /**
     * Get active workflow parameters.
     *
     * @return WorkflowParameter[]
     */
    public function getActiveWorkflowParameters(): array
    {
        return array_filter($this->workflowParameters, fn($param) => $param->isActive());
    }

    /**
     * Get the enum instance for this document type.
     */
    public function getEnum(): DocumentTypeEnum
    {
        return DocumentTypeEnum::from($this->invoiceTypeCode);
    }
}
