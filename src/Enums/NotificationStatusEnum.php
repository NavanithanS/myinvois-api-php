<?php

namespace Nava\MyInvois\Enums;

/**
 * Represents the possible notification statuses in the MyInvois system.
 */
class NotificationStatusEnum
{
    const NEW = 1;
    const PENDING = 2;
    const BATCHED = 3;
    const DELIVERED = 4;
    const ERROR = 5;

    /**
     * Returns a human-readable description of the notification status.
     */
    public static function description($status): string
    {
        switch ($status) {
            case self::NEW:
                return 'New';
            case self::PENDING:
                return 'Pending';
            case self::BATCHED:
                return 'Batched';
            case self::DELIVERED:
                return 'Delivered';
            case self::ERROR:
                return 'Error';
            default:
                throw new InvalidArgumentException("Invalid notification status.");
        }
    }

    /**
     * Get all available notification status codes.
     *
     * @return array<int>
     */
    public static function getCodes(): array
    {
        return [
            self::NEW,
            self::PENDING,
            self::BATCHED,
            self::DELIVERED,
            self::ERROR
        ];
    }

    /**
     * Determine if a code is valid.
     */
    public static function isValidCode(int $code): bool
    {
        return in_array($code, self::getCodes(), true);
    }

    /**
     * Create from code.
     *
     * @throws \InvalidArgumentException If code is invalid
     */
    public static function fromCode(int $code)
    {
        if (self::isValidCode($code)) {
            return $code;
        }
        throw new InvalidArgumentException("Invalid code: $code");
    }

    /**
     * Create from status name.
     *
     * @throws \InvalidArgumentException If status name is invalid
     */
    public static function fromName(string $name)
    {
        switch (strtoupper($name)) {
            case 'NEW':
                return self::NEW;
            case 'PENDING':
                return self::PENDING;
            case 'BATCHED':
                return self::BATCHED;
            case 'DELIVERED':
                return self::DELIVERED;
            case 'ERROR':
                return self::ERROR;
            default:
                throw new InvalidArgumentException("Invalid status name: $name");
        }
    }

    /**
     * Check if the status represents a final state.
     */
    public static function isFinal($status): bool
    {
        return in_array($status, [self::DELIVERED, self::ERROR], true);
    }

    /**
     * Check if the status represents a successful state.
     */
    public static function isSuccessful($status): bool
    {
        return $status === self::DELIVERED;
    }

    /**
     * Check if the status represents an error state.
     */
    public static function isError($status): bool
    {
        return $status === self::ERROR;
    }

    /**
     * Check if the status represents an in-progress state.
     */
    public static function isInProgress($status): bool
    {
        return in_array($status, [self::NEW, self::PENDING, self::BATCHED], true);
    }

    /**
     * Get the list of valid status transitions from this status.
     *
     * @return array<int>
     */
    public static function getValidTransitions($status): array
    {
        switch ($status) {
            case self::NEW:
                return [self::PENDING, self::BATCHED];
            case self::PENDING:
                return [self::BATCHED, self::DELIVERED, self::ERROR];
            case self::BATCHED:
                return [self::DELIVERED, self::ERROR];
            case self::DELIVERED:
            case self::ERROR:
                return [];
            default:
                throw new InvalidArgumentException("Invalid status: $status");
        }
    }

    /**
     * Check if a transition to the given status is valid.
     */
    public static function canTransitionTo($currentStatus, $newStatus): bool
    {
        return in_array($newStatus, self::getValidTransitions($currentStatus), true);
    }
}

