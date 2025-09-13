<?php

namespace Nava\MyInvois\Enums;

use InvalidArgumentException;

/**
 * Represents the possible document statuses in the MyInvois system.
 */
class DocumentStatusEnum
{
    const VALID = 'Valid';
    const INVALID = 'Invalid';
    const SUBMITTED = 'Submitted';
    const CANCELLED = 'Cancelled';
    const PENDING = 'Pending';
    const REJECTED = 'Rejected';

    /**
     * Returns a human-readable description of the document status.
     */
    public static function description(string $status): string
    {
        switch ($status) {
            case self::VALID:
                return 'Valid Document';
            case self::INVALID:
                return 'Invalid Document';
            case self::SUBMITTED:
                return 'Submitted Document';
            case self::CANCELLED:
                return 'Cancelled Document';
            case self::PENDING:
                return 'Pending Document';
            case self::REJECTED:
                return 'Rejected Document';
            default:
                throw new InvalidArgumentException("Invalid document status: $status");
        }
    }

    /**
     * Get all available document status values.
     *
     * @return array<string>
     */
    public static function getValues(): array
    {
        return [
            self::VALID,
            self::INVALID,
            self::SUBMITTED,
            self::CANCELLED,
            self::PENDING,
            self::REJECTED,
        ];
    }

    /**
     * Determine if a status is valid.
     */
    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::getValues(), true);
    }

    /**
     * Create from status string.
     *
     * @throws \InvalidArgumentException If status is invalid
     */
    public static function fromStatus(string $status): string
    {
        if (self::isValidStatus($status)) {
            return $status;
        }
        throw new InvalidArgumentException("Invalid status: $status");
    }
}
