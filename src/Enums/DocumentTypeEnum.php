<?php

namespace Nava\MyInvois\Enums;

/**
 * Represents the possible document types in the MyInvois system.
 */
enum DocumentTypeEnum: int
{
    /**
     * Regular Invoice
     */
    case INVOICE = 4;

    /**
     * Credit Note
     */
    case CREDIT_NOTE = 11;

    /**
     * Debit Note
     */
    case DEBIT_NOTE = 12;

    /**
     * Returns a human-readable description of the document type.
     */
    public function description(): string
    {
        return match ($this) {
            self::INVOICE => 'Invoice',
            self::CREDIT_NOTE => 'Credit Note',
            self::DEBIT_NOTE => 'Debit Note',
        };
    }

    /**
     * Get all available document type codes.
     *
     * @return array<int>
     */
    public static function getCodes(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
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
}
