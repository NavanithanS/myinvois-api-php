<?php

namespace Nava\MyInvois\Traits;

use Nava\MyInvois\Exception\ValidationException;
use Webmozart\Assert\Assert;

/**
 * Common trait for UUID validation functionality.
 */
trait UuidValidationTrait
{
    /**
     * Validate UUID format.
     *
     * Validates that a UUID:
     * - Is not empty
     * - Is exactly 15 characters
     * - Contains only uppercase alphanumeric characters (A-Z, 0-9)
     * - Has valid format for MyInvois system
     *
     * @param  string  $uuid  UUID to validate
     *
     * @throws ValidationException If UUID format is invalid
     */
    protected function validateUuid(string $uuid): void
    {
        if ($uuid === '' || !preg_match('/^[A-Z0-9]{15}$/', $uuid)) {
            throw new ValidationException(
                'Invalid UUID format: UUID must be exactly 15 alphanumeric characters',
                ['uuid' => ['UUID must be exactly 15 alphanumeric characters']]
            );
        }
    }

    /**
     * Format a UUID to ensure it meets MyInvois standards.
     *
     * @param  string  $uuid  UUID to format
     * @return string Formatted UUID
     *
     * @throws ValidationException If UUID cannot be formatted correctly
     */
    protected function formatUuid(string $uuid): string
    {
        // Remove any whitespace
        $uuid = trim($uuid);

        // Convert to uppercase
        $uuid = strtoupper($uuid);

        // Validate the formatted UUID
        $this->validateUuid($uuid);

        return $uuid;
    }

    /**
     * Check if a string is a valid MyInvois UUID.
     *
     * @param  string  $uuid  UUID to check
     * @return bool True if valid, false otherwise
     */
    protected function isValidUuid(string $uuid): bool
    {
        try {
            $this->validateUuid($uuid);

            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    /**
     * Extract document type from UUID.
     *
     * MyInvois UUIDs contain encoded information about the document type
     * in specific character positions.
     *
     * @param  string  $uuid  Valid UUID to analyze
     * @return string|null Document type code or null if cannot be determined
     *
     * @throws ValidationException If UUID is invalid
     */
    protected function getDocumentTypeFromUuid(string $uuid): ?string
    {
        $this->validateUuid($uuid);

        // Document type is encoded in characters 4-5
        $typeCode = substr($uuid, 3, 2);

        $documentTypes = [
            'IN' => 'invoice',
            'CN' => 'credit_note',
            'DN' => 'debit_note',
            'RN' => 'refund_note',
        ];

        return $documentTypes[$typeCode] ?? null;
    }

    protected function validateUuidVersion(string $uuid): void
    {
        $version = $this->getUuidVersion($uuid);
        if (! in_array($version, ['1', '4'])) {
            throw new ValidationException(
                'Invalid UUID version',
                ['uuid' => ['UUID must be version 1 or 4']]
            );
        }
    }

    // Add checksum validation
    protected function validateUuidChecksum(string $uuid): void
    {
        if (! $this->checksumValid($uuid)) {
            throw new ValidationException(
                'Invalid UUID checksum',
                ['uuid' => ['UUID has invalid checksum']]
            );
        }
    }

    // Add namespace validation
    protected function validateUuidNamespace(string $uuid, string $expectedNamespace): void
    {
        $namespace = $this->extractNamespace($uuid);
        if ($namespace !== $expectedNamespace) {
            throw new ValidationException(
                'Invalid UUID namespace',
                ['uuid' => ["UUID must be in namespace: {$expectedNamespace}"]]
            );
        }
    }
}
