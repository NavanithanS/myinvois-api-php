<?php

namespace Nava\MyInvois\Data;

use DateTimeImmutable;
use Nava\MyInvois\Enums\DocumentTypeEnum;
use Spatie\DataTransferObject\DataTransferObject;
use Webmozart\Assert\Assert;

/**
 * Represents a document type in the MyInvois system.
 */
class DocumentType extends DataTransferObject
{
    public $id;

    public $invoiceTypeCode;

    public $description;

    public $activeFrom;

    public $activeTo;

    /** @var array */
    public $documentTypeVersions;

    /** @var array */
    public $workflowParameters;

    /**
     * Create a new DocumentType instance from an array.
     *
     * @param  array  $data  Raw data from API
     *
     * @throws \InvalidArgumentException If data validation fails
     */
    public static function fromArray(array $data): self
    {
        Assert::keyExists($data, 'id', 'Document type must have an ID');
        // Description is optional in some responses
        // Active from can be omitted in some responses
        Assert::keyExists($data, 'documentTypeVersions', 'Document type must have versions array');
        Assert::isArray($data['documentTypeVersions'], 'Document type versions must be an array');

        // Validate invoice type code matches enum
        $validCodes = array_map(function ($case) {
            return $case->value;
        }, DocumentTypeEnum::cases());

        if (isset($data['invoiceTypeCode'])) {
            $code = $data['invoiceTypeCode'] instanceof DocumentTypeEnum ? $data['invoiceTypeCode']->value : $data['invoiceTypeCode'];
            Assert::inArray($code, $validCodes, 'Invalid invoice type code');
        }

        return new self([
            'id' => $data['id'],
            'invoiceTypeCode' => isset($data['invoiceTypeCode'])
                ? ($data['invoiceTypeCode'] instanceof DocumentTypeEnum
                    ? $data['invoiceTypeCode']
                    : (int) $data['invoiceTypeCode'])
                : null,
            'description' => $data['description'] ?? null,
            'activeFrom' => isset($data['activeFrom']) ? new DateTimeImmutable($data['activeFrom']) : new DateTimeImmutable(),
            'activeTo' => isset($data['activeTo']) ? new DateTimeImmutable($data['activeTo']) : null,
            'documentTypeVersions' => array_map(
                function (array $version) {
                    return DocumentTypeVersion::fromArray($version);
                },
                $data['documentTypeVersions']
            ),
            'workflowParameters' => array_map(
                function (array $param) {
                    return WorkflowParameter::fromArray($param);
                },
                $data['workflowParameters'] ?? []
            ),
        ]);
    }

    /**
     * Check if the document type is currently active.
     */
    public function isActive(): bool
    {
        $now = new DateTimeImmutable;

        return $this->activeFrom <= $now && ($this->activeTo === null || $this->activeTo > $now);
    }

    /**
     * Get all active versions of this document type.
     *
     * @return DocumentTypeVersion[]
     */
    public function getActiveVersions(): array
    {
        return array_filter($this->documentTypeVersions, function ($version) {
            return $version->isActive();
        });
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
            function ($carry, $version) {
                return $carry === null || $version->versionNumber > $carry->versionNumber ? $version : $carry;
            }
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
        return array_filter($this->workflowParameters, function ($param) {
            return $param->isActive();
        });
    }

    /**
     * Get the enum instance for this document type.
     */
    public function getEnum(): DocumentTypeEnum
    {
        return $this->invoiceTypeCode instanceof DocumentTypeEnum
            ? $this->invoiceTypeCode
            : DocumentTypeEnum::from((int) $this->invoiceTypeCode);
    }
}
