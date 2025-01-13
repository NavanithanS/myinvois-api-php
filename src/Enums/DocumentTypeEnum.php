<?php

namespace Nava\MyInvois\Enums;

/**
 * Represents the possible document types in the MyInvois system.
 */
class DocumentTypeEnum
{
    const INVOICE = 4;
    const CREDIT_NOTE = 11;
    const DEBIT_NOTE = 12;

    /**
     * Returns a human-readable description of the document type.
     */
    public static function description($type): string
    {
        switch ($type) {
            case self::INVOICE:
                return 'Invoice';
            case self::CREDIT_NOTE:
                return 'Credit Note';
            case self::DEBIT_NOTE:
                return 'Debit Note';
            default:
                throw new InvalidArgumentException("Invalid document type.");
        }
    }

    /**
     * Get all available document type codes.
     *
     * @return array<int>
     */
    public static function getCodes(): array
    {
        return [self::INVOICE, self::CREDIT_NOTE, self::DEBIT_NOTE];
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
}

