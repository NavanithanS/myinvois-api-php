<?php

namespace Nava\MyInvois\Data;

use DateTimeImmutable;
use JsonSerializable;
use Spatie\DataTransferObject\DataTransferObject;
use Webmozart\Assert\Assert;

/**
 * Represents a workflow parameter in the MyInvois system.
 */
class WorkflowParameter extends DataTransferObject implements JsonSerializable
{
    private const VALID_PARAMETERS = [
        'submissionDuration',
        'cancellationDuration',
        'rejectionDuration'
    ];

    public int $id;
    public string $parameter;
    public int $value;
    public DateTimeImmutable $activeFrom;
    public ?DateTimeImmutable $activeTo;

    /**
     * Create a new WorkflowParameter instance from an array.
     *
     * @param array $data Raw data from API
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        Assert::keyExists($data, 'id', 'Parameter must have an ID');
        Assert::keyExists($data, 'parameter', 'Parameter must have a name');
        Assert::keyExists($data, 'value', 'Parameter must have a value');
        Assert::keyExists($data, 'activeFrom', 'Parameter must have an active from date');
        
        Assert::inArray($data['parameter'], self::VALID_PARAMETERS, sprintf(
            'Parameter must be one of: %s',
            implode(', ', self::VALID_PARAMETERS)
        ));

        Assert::numeric($data['value'], 'Parameter value must be numeric');
        Assert::greaterThan($data['value'], 0, 'Parameter value must be greater than 0');

        return new self([
            'id' => $data['id'],
            'parameter' => $data['parameter'],
            'value' => $data['value'],
            'activeFrom' => new DateTimeImmutable($data['activeFrom']),
            'activeTo' => isset($data['activeTo']) ? new DateTimeImmutable($data['activeTo']) : null,
        ]);
    }

    /**
     * Check if the parameter is currently active.
     */
    public function isActive(): bool
    {
        $now = new DateTimeImmutable();
        return $this->activeFrom <= $now && (null === $this->activeTo || $this->activeTo > $now);
    }

    /**
     * Get all valid parameter names.
     *
     * @return string[]
     */
    public static function getValidParameters(): array
    {
        return self::VALID_PARAMETERS;
    }

    /**
     * Implement JsonSerializable interface.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'parameter' => $this->parameter,
            'value' => $this->value,
            'activeFrom' => $this->activeFrom->format('c'),
            'activeTo' => $this->activeTo?->format('c'),
        ];
    }
}