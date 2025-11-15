<?php

namespace Nava\MyInvois;

class Config
{
    // API URLs
    public const IDENTITY_PRODUCTION_URL = 'https://api.myinvois.hasil.gov.my';

    public const IDENTITY_SANDBOX_URL = 'https://preprod-api.myinvois.hasil.gov.my';

    public const PRODUCTION_URL = 'https://myinvois.hasil.gov.my';

    public const SANDBOX_URL = 'https://preprod.myinvois.hasil.gov.my';

    // Version constants
    public const INVOICE_CURRENT_VERSION = '1.1'; // Updated to latest version

    public const INVOICE_SUPPORTED_VERSIONS = ['1.0', '1.1'];

    public const CREDIT_NOTE_CURRENT_VERSION = '1.1';

    public const CREDIT_NOTE_SUPPORTED_VERSIONS = ['1.0', '1.1'];

    public const CREDIT_NOTE_TYPE_CODE = '02';

    public const DEBIT_NOTE_CURRENT_VERSION = '1.1';

    public const DEBIT_NOTE_SUPPORTED_VERSIONS = ['1.0', '1.1'];

    public const REFUND_NOTE_CURRENT_VERSION = '1.1';

    public const REFUND_NOTE_SUPPORTED_VERSIONS = ['1.0', '1.1']; // Add support for new version

    public const REFUND_NOTE_TYPE_CODE = '04';

    // Authentication settings
    public const DEFAULT_SCOPE = 'InvoicingAPI';

    public const TOKEN_ENDPOINT = '/connect/token';

    public const TOKEN_CACHE_PREFIX = 'myinvois_token_';

    public const INTERMEDIARY_TOKEN_CACHE_PREFIX = 'myinvois_intermediary_token_';

    public const DEFAULT_TOKEN_TTL = 3600; // 1 hour

    public const TOKEN_REFRESH_BUFFER = 300; // 5 minutes before expiry

    // HTTP settings
    public const DEFAULT_TIMEOUT = 30;

    public const DEFAULT_CONNECT_TIMEOUT = 10;

    public const DEFAULT_RETRY_TIMES = 3;

    public const DEFAULT_RETRY_SLEEP = 1000; // milliseconds

    public const MAX_RETRY_SLEEP = 10000; // 10 seconds max sleep between retries

    // Validation patterns
    public const TIN_PATTERN = '/^C\d{10}$/';

    /**
     * Check if a document version is supported.
     *
     * @param  string  $docType  Document type ('invoice', 'credit_note', 'debit_note')
     * @param  string  $version  Version to check
     * @return bool True if version is supported
     */
    public static function isVersionSupported(string $docType, string $version): bool
    {
        $supportedVersions = [];

        switch ($docType) {
            case 'invoice':
                $supportedVersions = self::INVOICE_SUPPORTED_VERSIONS;
                break;
            case 'credit_note':
                $supportedVersions = self::CREDIT_NOTE_SUPPORTED_VERSIONS;
                break;
            case 'debit_note':
                $supportedVersions = self::DEBIT_NOTE_SUPPORTED_VERSIONS;
                break;
            case 'refund_note':
                $supportedVersions = self::REFUND_NOTE_SUPPORTED_VERSIONS;
                break;
            default:
                $supportedVersions = [];
                break;
        }
        
        return in_array($version, $supportedVersions, true);
        
    }

    /**
     * Get current version for a document type.
     *
     * @param  string  $docType  Document type ('invoice', 'credit_note', 'debit_note')
     * @return string Current version
     *
     * @throws \InvalidArgumentException If document type is invalid
     */
    public static function getCurrentVersion(string $docType): string
    {
        switch ($docType) {
            case 'invoice':
                return self::INVOICE_CURRENT_VERSION;
            case 'credit_note':
                return self::CREDIT_NOTE_CURRENT_VERSION;
            case 'debit_note':
                return self::DEBIT_NOTE_CURRENT_VERSION;
            case 'refund_note':
                return self::REFUND_NOTE_CURRENT_VERSION;
            default:
                throw new \InvalidArgumentException(
                    'Invalid document type. Must be one of: invoice, credit_note, debit_note'
                );
        }
        
    }
}
