<?php

namespace Nava\MyInvois\Enums;

/**
 * Notification type identifiers in the MyInvois system.
 */
enum NotificationTypeEnum: int
{
    case PROFILE_DATA_VALIDATION = 3;
    case DOCUMENT_RECEIVED = 6;
    case DOCUMENT_VALIDATED = 7;
    case DOCUMENT_CANCELLED = 8;
    case USER_PROFILE_CHANGED = 10;
    case TAXPAYER_PROFILE_CHANGED = 11;
    case DOCUMENT_REJECTION_INITIATED = 15;
    case ERP_DATA_VALIDATION = 26;
    case DOCUMENTS_PROCESSING_SUMMARY = 33;
    case DOCUMENT_TEMPLATE_PUBLISHED = 34;
    case DOCUMENT_TEMPLATE_DELETION = 35;

    /**
     * Get the human-readable description of the notification type.
     */
    public function description(): string
    {
        return match ($this) {
            self::PROFILE_DATA_VALIDATION => 'Profile data validation',
            self::DOCUMENT_RECEIVED => 'Document received',
            self::DOCUMENT_VALIDATED => 'Document validated',
            self::DOCUMENT_CANCELLED => 'Document cancelled',
            self::USER_PROFILE_CHANGED => 'User profile changed',
            self::TAXPAYER_PROFILE_CHANGED => 'Taxpayer profile changed',
            self::DOCUMENT_REJECTION_INITIATED => 'Document rejection initiated',
            self::ERP_DATA_VALIDATION => 'ERP data validation',
            self::DOCUMENTS_PROCESSING_SUMMARY => 'Documents processing summary',
            self::DOCUMENT_TEMPLATE_PUBLISHED => 'Document Template Published',
            self::DOCUMENT_TEMPLATE_DELETION => 'Document Template Deletion',
        };
    }

    /**
     * Get all available notification type codes.
     *
     * @return array<int>
     */
    public static function getCodes(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Check if a code is valid.
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

/**
 * Notification status identifiers in the MyInvois system.
 */
enum NotificationStatusEnum: int
{
    case NEW = 1;
    case PENDING = 2;
    case BATCHED = 3;
    case DELIVERED = 4;
    case ERROR = 5;

    /**
     * Get a human-readable description of the status.
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
}
