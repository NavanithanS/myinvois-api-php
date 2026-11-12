<?php

namespace Nava\MyInvois;

class Config
{
    // API URLs. The e-Invoice API and the identity service share the *-api host
    // (verified against preprod 2026-11-12: the portal hosts return 404 for /api/*).
    public const IDENTITY_PRODUCTION_URL = 'https://api.myinvois.hasil.gov.my';

    public const IDENTITY_SANDBOX_URL = 'https://preprod-api.myinvois.hasil.gov.my';

    public const PRODUCTION_URL = 'https://api.myinvois.hasil.gov.my';

    public const SANDBOX_URL = 'https://preprod-api.myinvois.hasil.gov.my';

    // MyInvois portal hosts, used only for document share/QR links.
    public const PORTAL_PRODUCTION_URL = 'https://myinvois.hasil.gov.my';

    public const PORTAL_SANDBOX_URL = 'https://preprod.myinvois.hasil.gov.my';

    /**
     * API base URL for a configured base URL. The portal hosts (which older
     * versions used and documented as the base URL) are mapped to their API host;
     * anything else is returned unchanged.
     */
    public static function apiBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $map = [
            self::PORTAL_PRODUCTION_URL => self::PRODUCTION_URL,
            self::PORTAL_SANDBOX_URL => self::SANDBOX_URL,
        ];

        return $map[strtolower($baseUrl)] ?? $baseUrl;
    }

    /**
     * Portal base URL for share/QR links: the API hosts map to their portal host;
     * anything else (e.g. an explicit portal URL) is returned unchanged.
     */
    public static function portalBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $map = [
            self::PRODUCTION_URL => self::PORTAL_PRODUCTION_URL,
            self::SANDBOX_URL => self::PORTAL_SANDBOX_URL,
        ];

        return $map[strtolower($baseUrl)] ?? $baseUrl;
    }

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

    // e-Invoice Voluntary Disclosure Programme (SVDP), SDK release note 8 Jul 2026.
    // Versions exist for all 8 document types (sdk.myinvois.hasil.gov.my/types/):
    // 1.2 = structure of 1.0 without digital signature; 1.3 = structure of 1.1 with signature.
    // Only usable during the programme; IssueDate may be backdated to the taxpayer's
    // phased mandatory implementation date (earliest 1 Aug 2024).
    public const SVDP_UNSIGNED_VERSION = '1.2';

    public const SVDP_SIGNED_VERSION = '1.3';

    public const SVDP_VERSIONS = [self::SVDP_UNSIGNED_VERSION, self::SVDP_SIGNED_VERSION];

    public const SVDP_EARLIEST_ISSUE_DATE = '2024-08-01';

    /** Last day of the programme (inclusive, Malaysia time). */
    public const SVDP_END_DATE = '2027-12-31';

    public const SVDP_TIMEZONE = 'Asia/Kuala_Lumpur';

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
     * Whether a version is an SVDP version (1.2 or 1.3).
     */
    public static function isSvdpVersion(string $version): bool
    {
        return in_array($version, self::SVDP_VERSIONS, true);
    }

    /**
     * Whether the SVDP programme is still running at $now (inclusive of
     * 31 Dec 2027, Malaysia time).
     *
     * @param  \DateTimeInterface|null  $now  Defaults to the current time
     */
    public static function isSvdpActive(?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $local = \DateTimeImmutable::createFromInterface($now)->setTimezone(new \DateTimeZone(self::SVDP_TIMEZONE));

        return $local->format('Y-m-d') <= self::SVDP_END_DATE;
    }

    /**
     * Check if a document version is supported.
     *
     * Regular versions come from the *_SUPPORTED_VERSIONS constants. SVDP
     * versions 1.2/1.3 are supported for every known document type, but only
     * while the programme is active.
     *
     * @param  string  $docType  Document type ('invoice', 'credit_note', 'debit_note', 'refund_note')
     * @param  string  $version  Version to check
     * @param  \DateTimeInterface|null  $now  Clock for the SVDP window (defaults to now)
     * @return bool True if version is supported
     */
    public static function isVersionSupported(string $docType, string $version, ?\DateTimeInterface $now = null): bool
    {
        if (self::isSvdpVersion($version)) {
            return in_array($docType, ['invoice', 'credit_note', 'debit_note', 'refund_note'], true)
                && self::isSvdpActive($now);
        }

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
