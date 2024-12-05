<?php

namespace Nava\MyInvois\Enums;

/**
 * Represents the possible notification statuses in the MyInvois system.
 */
enum NotificationStatusEnum: int {
    /**
     * Newly created notification
     */
    case NEW  = 1;

    /**
     * Notification pending delivery
     */
    case PENDING = 2;

    /**
     * Notification batched for delivery
     */
    case BATCHED = 3;

    /**
     * Notification successfully delivered
     */
    case DELIVERED = 4;

    /**
     * Error occurred during notification delivery
     */
    case ERROR = 5;

    /**
     * Returns a human-readable description of the notification status.
     */
    public function description(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::PENDING => 'Pending',
            self::BATCHED => 'Batched',
            self::DELIVERED => 'Delivered',
            self::ERROR => 'Error',
        };
    }

    /**
     * Get all available notification status codes.
     *
     * @return array<int>
     */
    public static function getCodes(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
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
     * @throws \ValueError If code is invalid
     */
    public static function fromCode(int $code): self
    {
        return self::from($code);
    }

    /**
     * Create from status name.
     *
     * @throws \ValueError If status name is invalid
     */
    public static function fromName(string $name): self
    {
        return match (strtoupper($name)) {
            'NEW' => self::NEW ,
            'PENDING' => self::PENDING,
            'BATCHED' => self::BATCHED,
            'DELIVERED' => self::DELIVERED,
            'ERROR' => self::ERROR,
            default => throw new \ValueError("Invalid status name: $name"),
        };
    }

    /**
     * Check if the status represents a final state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::ERROR], true);
    }

    /**
     * Check if the status represents a successful state.
     */
    public function isSuccessful(): bool
    {
        return self::DELIVERED === $this;
    }

    /**
     * Check if the status represents an error state.
     */
    public function isError(): bool
    {
        return self::ERROR === $this;
    }

    /**
     * Check if the status represents an in-progress state.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::NEW , self::PENDING, self::BATCHED], true);
    }

    /**
     * Get the list of valid status transitions from this status.
     *
     * @return array<int>
     */
    public function getValidTransitions(): array
    {
        return match ($this) {
            self::NEW => [self::PENDING, self::BATCHED],
            self::PENDING => [self::BATCHED, self::DELIVERED, self::ERROR],
            self::BATCHED => [self::DELIVERED, self::ERROR],
            self::DELIVERED, self::ERROR => [],
        };
    }

    /**
     * Check if a transition to the given status is valid.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->getValidTransitions(), true);
    }
}
