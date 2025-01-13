<?php

namespace Nava\MyInvois\Enums;

/**
 * Notification type identifiers in the MyInvois system.
 */
class NotificationTypeEnum
{
    const PROFILE_DATA_VALIDATION = 3;
    const DOCUMENT_RECEIVED = 6;
    const DOCUMENT_VALIDATED = 7;
    const DOCUMENT_CANCELLED = 8;
    const USER_PROFILE_CHANGED = 10;
    const TAXPAYER_PROFILE_CHANGED = 11;
    const DOCUMENT_REJECTION_INITIATED = 15;
    const ERP_DATA_VALIDATION = 26;
    const DOCUMENTS_PROCESSING_SUMMARY = 33;
    const DOCUMENT_TEMPLATE_PUBLISHED = 34;
    const DOCUMENT_TEMPLATE_DELETION = 35;

    /**
     * Get the human-readable description of the notification type.
     */
    public static function description(int $type): string
    {
        switch ($type) {
            case self::PROFILE_DATA_VALIDATION:
                return 'Profile data validation';
            case self::DOCUMENT_RECEIVED:
                return 'Document received';
            case self::DOCUMENT_VALIDATED:
                return 'Document validated';
            case self::DOCUMENT_CANCELLED:
                return 'Document cancelled';
            case self::USER_PROFILE_CHANGED:
                return 'User profile changed';
            case self::TAXPAYER_PROFILE_CHANGED:
                return 'Taxpayer profile changed';
            case self::DOCUMENT_REJECTION_INITIATED:
                return 'Document rejection initiated';
            case self::ERP_DATA_VALIDATION:
                return 'ERP data validation';
            case self::DOCUMENTS_PROCESSING_SUMMARY:
                return 'Documents processing summary';
            case self::DOCUMENT_TEMPLATE_PUBLISHED:
                return 'Document Template Published';
            case self::DOCUMENT_TEMPLATE_DELETION:
                return 'Document Template Deletion';
            default:
                throw new InvalidArgumentException("Invalid notification type code.");
        }
    }

    /**
     * Get all available notification type codes.
     *
     * @return array<int>
     */
    public static function getCodes(): array
    {
        return [
            self::PROFILE_DATA_VALIDATION,
            self::DOCUMENT_RECEIVED,
            self::DOCUMENT_VALIDATED,
            self::DOCUMENT_CANCELLED,
            self::USER_PROFILE_CHANGED,
            self::TAXPAYER_PROFILE_CHANGED,
            self::DOCUMENT_REJECTION_INITIATED,
            self::ERP_DATA_VALIDATION,
            self::DOCUMENTS_PROCESSING_SUMMARY,
            self::DOCUMENT_TEMPLATE_PUBLISHED,
            self::DOCUMENT_TEMPLATE_DELETION,
        ];
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


/**
 * Notification status identifiers in the MyInvois system.
 */
class NotificationStatusEnum
{
    const NEW = 1;
    const PENDING = 2;
    const BATCHED = 3;
    const DELIVERED = 4;
    const ERROR = 5;

    /**
     * Get a human-readable description of the status.
     */
    public static function description(int $status): string
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
                throw new InvalidArgumentException("Invalid notification status code.");
        }
    }
}
