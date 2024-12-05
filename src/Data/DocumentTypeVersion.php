<?php

namespace Nava\MyInvois\Data;

use DateTimeImmutable;
use JsonSerializable;
use Spatie\DataTransferObject\DataTransferObject;
use Webmozart\Assert\Assert;

/**
 * Represents a document type version in the MyInvois system.
 */
class DocumentTypeVersion extends DataTransferObject implements JsonSerializable
{
    private const VALID_STATUSES = ['draft', 'published', 'deactivated'];

    public int $id;
    public string $name;
    public string $description;
    public DateTimeImmutable $activeFrom;
    public ?DateTimeImmutable $activeTo;
    public float $versionNumber;
    public string $status;

    /**
     * Create a new DocumentTypeVersion instance from an array.
     *
     * @param array $data Raw data from API
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        // Validate required fields
        Assert::keyExists($data, 'id', 'Version must have an ID');
        Assert::keyExists($data, 'name', 'Version must have a name');
        Assert::keyExists($data, 'description', 'Version must have a description');
        Assert::keyExists($data, 'activeFrom', 'Version must have an active from date');
        Assert::keyExists($data, 'versionNumber', 'Version must have a version number');
        Assert::keyExists($data, 'status', 'Version must have a status');

        // Validate status
        Assert::inArray($data['status'], self::VALID_STATUSES,
            sprintf('Status must be one of: %s', implode(', ', self::VALID_STATUSES))
        );

        // Validate version number
        Assert::numeric($data['versionNumber'], 'Version number must be numeric');
        Assert::greaterThan($data['versionNumber'], 0, 'Version number must be greater than 0');

        return new self([
            'id' => $data['id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'activeFrom' => new DateTimeImmutable($data['activeFrom']),
            'activeTo' => isset($data['activeTo']) ? new DateTimeImmutable($data['activeTo']) : null,
            'versionNumber' => (float) $data['versionNumber'],
            'status' => $data['status'],
        ]);
    }

    /**
     * Check if the version is currently active.
     */
    public function isActive(): bool
    {
        $now = new DateTimeImmutable();

        return 'published' === $this->status && $this->activeFrom <= $now
            && (null === $this->activeTo || $this->activeTo > $now);
    }

    /**
     * Get all valid status values.
     *
     * @return string[]
     */
    public static function getValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Implement JsonSerializable interface.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'activeFrom' => $this->activeFrom->format('c'),
            'activeTo' => $this->activeTo?->format('c'),
            'versionNumber' => $this->versionNumber,
            'status' => $this->status,
        ];
    }
}
