<?php

namespace Nava\MyInvois\Data;

use DateTimeImmutable;
use JsonSerializable;
use Nava\MyInvois\Enums\DocumentStatusEnum;
use Nava\MyInvois\Enums\DocumentTypeEnum;
use Spatie\DataTransferObject\DataTransferObject;
use Webmozart\Assert\Assert;

/**
 * Represents a document search result in the MyInvois system.
 */
class DocumentSearchResult extends DataTransferObject implements JsonSerializable
{
    public $uuid;

    public $submissionUID;

    public $longId;

    public $internalId;

    public $typeName;

    public $typeVersionName;

    public $issuerTin;

    public $issuerName;

    public $receiverId;

    public $receiverName;

    public $dateTimeIssued;

    public $dateTimeReceived;

    public $dateTimeValidated;

    public $totalSales;

    public $totalDiscount;

    public $netAmount;

    public $total;

    public $status;

    public $cancelDateTime;

    public $rejectRequestDateTime;

    public $documentStatusReason;

    public $createdByUserId;

    public $supplierTIN;

    public $supplierName;

    public $submissionChannel;

    public $intermediaryName;

    public $intermediaryTIN;

    public $buyerName;

    public $buyerTIN;

    /**
     * Create a new DocumentSearchResult instance from an array.
     *
     * @param  array  $data  Raw data from API
     *
     * @throws \InvalidArgumentException If data validation fails
     */
    public static function fromArray(array $data): self
    {
        // Validate required fields
        $requiredFields = [
            'uuid', 'submissionUID', 'longId', 'internalId', 'typeName',
            'typeVersionName', 'issuerTin', 'issuerName', 'dateTimeIssued',
            'dateTimeReceived', 'dateTimeValidated', 'totalSales',
            'totalDiscount', 'netAmount', 'total', 'status', 'createdByUserId',
            'supplierTIN', 'supplierName', 'submissionChannel', 'buyerName', 'buyerTIN',
        ];

        foreach ($requiredFields as $field) {
            Assert::keyExists($data, $field, sprintf('%s is required', ucfirst($field)));
        }

        // Validate numeric values
        $numericFields = ['totalSales', 'totalDiscount', 'netAmount', 'total'];
        foreach ($numericFields as $field) {
            Assert::numeric($data[$field], sprintf('%s must be numeric', ucfirst($field)));
            Assert::greaterThanEq($data[$field], 0, sprintf('%s cannot be negative', ucfirst($field)));
        }

        // Validate TIN formats
        $tinFields = ['issuerTin', 'supplierTIN', 'buyerTIN'];
        foreach ($tinFields as $field) {
            if (! preg_match('/^C\d{10}$/', $data[$field])) {
                throw new \InvalidArgumentException(
                    sprintf('%s must start with C followed by 10 digits', ucfirst($field))
                );
            }
        }

        if (isset($data['intermediaryTIN'])) {
            Assert::regex(
                $data['intermediaryTIN'],
                '/^C\d{10}$/',
                'Intermediary TIN must start with C followed by 10 digits'
            );
        }

        // Validate submission channel
        Assert::inArray(
            $data['submissionChannel'],
            ['ERP', 'Invoicing Portal', 'InvoicingMobileApp'],
            'Invalid submission channel'
        );

        // Validate document status
        Assert::inArray(
            $data['status'],
            DocumentStatusEnum::getValidStatuses(),
            'Invalid document status'
        );

        return new self([
            'uuid' => $data['uuid'],
            'submissionUID' => $data['submissionUID'],
            'longId' => $data['longId'],
            'internalId' => $data['internalId'],
            'typeName' => $data['typeName'],
            'typeVersionName' => $data['typeVersionName'],
            'issuerTin' => $data['issuerTin'],
            'issuerName' => $data['issuerName'],
            'receiverId' => $data['receiverId'] ?? null,
            'receiverName' => $data['receiverName'] ?? null,
            'dateTimeIssued' => new DateTimeImmutable($data['dateTimeIssued']),
            'dateTimeReceived' => new DateTimeImmutable($data['dateTimeReceived']),
            'dateTimeValidated' => new DateTimeImmutable($data['dateTimeValidated']),
            'totalSales' => (float) $data['totalSales'],
            'totalDiscount' => (float) $data['totalDiscount'],
            'netAmount' => (float) $data['netAmount'],
            'total' => (float) $data['total'],
            'status' => $data['status'],
            'cancelDateTime' => isset($data['cancelDateTime'])
            ? new DateTimeImmutable($data['cancelDateTime'])
            : null,
            'rejectRequestDateTime' => isset($data['rejectRequestDateTime'])
            ? new DateTimeImmutable($data['rejectRequestDateTime'])
            : null,
            'documentStatusReason' => $data['documentStatusReason'] ?? null,
            'createdByUserId' => $data['createdByUserId'],
            'supplierTIN' => $data['supplierTIN'],
            'supplierName' => $data['supplierName'],
            'submissionChannel' => $data['submissionChannel'],
            'intermediaryName' => $data['intermediaryName'] ?? null,
            'intermediaryTIN' => $data['intermediaryTIN'] ?? null,
            'buyerName' => $data['buyerName'],
            'buyerTIN' => $data['buyerTIN'],
        ]);
    }

    /**
     * Get the document status enum instance.
     */
    public function getStatus(): DocumentStatusEnum
    {
        return DocumentStatusEnum::fromName($this->status);
    }

    /**
     * Check if the document has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->getStatus() === DocumentStatusEnum::CANCELLED;
    }

    /**
     * Check if the document is valid.
     */
    public function isValid(): bool
    {
        return $this->getStatus() === DocumentStatusEnum::VALID;
    }

    /**
     * Get document type enum instance.
     */
    public function getDocumentType(): DocumentTypeEnum
    {
        $typeMap = [
            '01' => DocumentTypeEnum::INVOICE,
            '02' => DocumentTypeEnum::CREDIT_NOTE,
            '03' => DocumentTypeEnum::DEBIT_NOTE,
        ];

        return $typeMap[$this->typeName] ?? DocumentTypeEnum::INVOICE;
    }

    /**
     * Get total tax amount.
     */
    public function getTaxAmount(): float
    {
        return $this->total - $this->netAmount;
    }

    /**
     * Check if the document was submitted through ERP.
     */
    public function isErpSubmission(): bool
    {
        return $this->submissionChannel === 'ERP';
    }

    /**
     * Check if the document was submitted through intermediary.
     */
    public function hasIntermediary(): bool
    {
        return $this->intermediaryTIN !== null;
    }

    /**
     * Get the document URL for public access.
     */
    public function getPublicUrl(string $baseUrl): string
    {
        return sprintf('%s/%s/share/%s', rtrim($baseUrl, '/'), $this->uuid, $this->longId);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'uuid' => $this->uuid,
            'submissionUID' => $this->submissionUID,
            'longId' => $this->longId,
            'internalId' => $this->internalId,
            'typeName' => $this->typeName,
            'typeVersionName' => $this->typeVersionName,
            'issuerTin' => $this->issuerTin,
            'issuerName' => $this->issuerName,
            'receiverId' => $this->receiverId,
            'receiverName' => $this->receiverName,
            'dateTimeIssued' => $this->dateTimeIssued->format('Y-m-d H:i:s'),
            'dateTimeReceived' => $this->dateTimeReceived->format('Y-m-d H:i:s'),
            'dateTimeValidated' => $this->dateTimeValidated->format('Y-m-d H:i:s'),
            'totalSales' => $this->totalSales,
            'totalDiscount' => $this->totalDiscount,
            'netAmount' => $this->netAmount,
            'total' => $this->total,
            'status' => $this->status,
            'cancelDateTime' => $this->cancelDateTime ? $this->cancelDateTime->format('Y-m-d H:i:s') : null,
            'rejectRequestDateTime' => $this->rejectRequestDateTime ? $this->rejectRequestDateTime->format('Y-m-d H:i:s') : null,
            'documentStatusReason' => $this->documentStatusReason,
            'createdByUserId' => $this->createdByUserId,
            'supplierTIN' => $this->supplierTIN,
            'supplierName' => $this->supplierName,
            'submissionChannel' => $this->submissionChannel,
            'intermediaryName' => $this->intermediaryName,
            'intermediaryTIN' => $this->intermediaryTIN,
            'buyerName' => $this->buyerName,
            'buyerTIN' => $this->buyerTIN,
        ];
    }
}
