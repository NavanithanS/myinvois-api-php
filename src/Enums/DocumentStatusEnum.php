<?php

namespace Nava\MyInvois\Enums;

enum DocumentStatusEnum: string
{
    case VALID = 'Valid';
    case INVALID = 'Invalid';
    case SUBMITTED = 'Submitted';
    case CANCELLED = 'Cancelled';
    case PENDING = 'Pending';
    case REJECTED = 'Rejected';

    public function description(): string
    {
        return match ($this) {
            self::VALID => 'Valid Document',
            self::INVALID => 'Invalid Document',
            self::SUBMITTED => 'Submitted Document',
            self::CANCELLED => 'Cancelled Document',
            self::PENDING => 'Pending Document',
            self::REJECTED => 'Rejected Document',
        };
    }

    public static function getValidStatuses(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }

    public static function fromName(string $name): self
    {
        // Name here is actually the value (e.g., 'Valid'), so use from(value)
        return self::from($name);
    }
}
