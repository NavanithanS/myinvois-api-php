<?php

namespace Nava\MyInvois\Enums;

enum NotificationStatusEnum: int
{
    case NEW = 1;
    case PENDING = 2;
    case BATCHED = 3;
    case DELIVERED = 4;
    case ERROR = 5;

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

    public static function isValidCode(int $code): bool
    {
        return in_array($code, array_map(fn(self $c) => $c->value, self::cases()), true);
    }

    public static function fromCode(int $code): self
    {
        return self::from($code);
    }

    public static function fromName(string $name): self
    {
        $upper = strtoupper($name);
        foreach (self::cases() as $case) {
            if ($case->name === $upper) {
                return $case;
            }
        }
        throw new \ValueError("Invalid status name: $name");
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::ERROR], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::DELIVERED;
    }

    public function isError(): bool
    {
        return $this === self::ERROR;
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::NEW, self::PENDING, self::BATCHED], true);
    }

    public function getValidTransitions(): array
    {
        return match ($this) {
            self::NEW => [self::PENDING, self::BATCHED],
            self::PENDING => [self::BATCHED, self::DELIVERED, self::ERROR],
            self::BATCHED => [self::DELIVERED, self::ERROR],
            default => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->getValidTransitions(), true);
    }
}

