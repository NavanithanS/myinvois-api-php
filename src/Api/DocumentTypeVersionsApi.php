<?php

namespace Nava\MyInvois\Api;

use Nava\MyInvois\Data\DocumentTypeVersion;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for document type version operations.
 */
trait DocumentTypeVersionsApi
{
    protected $logger = null;

    /**
     * Get a specific document type version.
     *
     * @param  int  $documentTypeId  The document type ID
     * @param  int  $versionId  The version ID
     *
     * @throws ApiException If the API request fails
     * @throws ValidationException If the input parameters are invalid
     */
    public function getDocumentTypeVersion(int $documentTypeId, int $versionId): DocumentTypeVersion
    {
        try {
            Assert::greaterThan($documentTypeId, 0, 'Document type ID must be greater than 0');
            Assert::greaterThan($versionId, 0, 'Version ID must be greater than 0');

            $response = $this->apiClient->request(
                'GET',
                "/api/v1.0/documenttypes/{$documentTypeId}/versions/{$versionId}"
            );

            if (! isset($response['result'])) {
                throw new ApiException('Invalid response format from document type version endpoint');
            }

            $version = DocumentTypeVersion::fromArray($response['result']);

            $this->logDebug('Retrieved document type version successfully', [
                'documentTypeId' => $documentTypeId,
                'versionId' => $versionId,
                'version' => $version->versionNumber,
                'status' => $version->status,
                'is_active' => $version->isActive(),
            ]);

            return $version;
        } catch (ApiException $e) {
            $this->logError('Failed to retrieve document type version', [
                'documentTypeId' => $documentTypeId,
                'versionId' => $versionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Find a specific version of a document type by version number.
     *
     * @param  int  $documentTypeId  The document type ID
     * @param  float  $versionNumber  The version number to find
     * @return DocumentTypeVersion|null The version if found, null otherwise
     *
     * @throws ApiException If the API request fails
     */
    public function findDocumentTypeVersion(int $documentTypeId, float $versionNumber): ?DocumentTypeVersion
    {
        try {
            $documentType = $this->getDocumentType($documentTypeId);

            foreach ($documentType->documentTypeVersions as $version) {
                if ($version->versionNumber === $versionNumber) {
                    $this->logDebug('Found document type version', [
                        'documentTypeId' => $documentTypeId,
                        'versionNumber' => $versionNumber,
                        'status' => $version->status,
                        'is_active' => $version->isActive(),
                    ]);

                    return $version;
                }
            }

            $this->logDebug('Document type version not found', [
                'documentTypeId' => $documentTypeId,
                'versionNumber' => $versionNumber,
            ]);

            return null;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get the active versions for a specific document type.
     *
     * @param  int  $documentTypeId  The document type ID
     * @return DocumentTypeVersion[]
     *
     * @throws ApiException If the API request fails
     */
    public function getActiveDocumentTypeVersions(int $documentTypeId): array
    {
        try {
            $documentType = $this->getDocumentType($documentTypeId);
            $activeVersions = array_filter(
                $documentType->documentTypeVersions,
                function (DocumentTypeVersion $version) {
                    return $version->isActive();
                }
            );

            $this->logDebug('Retrieved active document type versions', [
                'documentTypeId' => $documentTypeId,
                'total_versions' => count($documentType->documentTypeVersions),
                'active_versions' => count($activeVersions),
                'version_numbers' => array_map(
                    function ($v) {
                        return $v->versionNumber;
                    },
                    $activeVersions
                ),
            ]);

            return array_values($activeVersions);
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Get the latest version for a specific document type.
     *
     * @param  int  $documentTypeId  The document type ID
     * @return DocumentTypeVersion|null The latest version if found, null otherwise
     *
     * @throws ApiException If the API request fails
     */
    public function getLatestDocumentTypeVersion(int $documentTypeId): ?DocumentTypeVersion
    {
        try {
            $activeVersions = $this->getActiveDocumentTypeVersions($documentTypeId);
            if (empty($activeVersions)) {
                return null;
            }

            $latestVersion = array_reduce(
                $activeVersions,
                function ($carry, $version) {
                    return $carry === null || $version->versionNumber > $carry->versionNumber
                        ? $version
                        : $carry;
                }
            );

            $this->logDebug('Retrieved latest document type version', [
                'documentTypeId' => $documentTypeId,
                'version' => $latestVersion->versionNumber,
                'status' => $latestVersion->status,
            ]);

            return $latestVersion;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Decode schema content from base64.
     *
     * @param  string  $format  Either 'json' or 'xml'
     * @return string Decoded schema content
     *
     * @throws ValidationException If format is invalid or schema is missing
     */
    public function getDocumentTypeVersionSchema(DocumentTypeVersion $version, string $format): string
    {
        Assert::inArray($format, ['json', 'xml'], 'Format must be either "json" or "xml"');

        $schemaField = $format === 'json' ? 'jsonSchema' : 'xmlSchema';
        $schema = $version->{$schemaField} ?? null;

        if (! $schema) {
            throw new ValidationException(
                "No {$format} schema available for version {$version->versionNumber}",
                [$format => ['Schema not available']]
            );
        }

        $decoded = base64_decode($schema, true);
        if ($decoded === false) {
            throw new ValidationException(
                "Invalid base64 encoded {$format} schema",
                [$format => ['Invalid schema encoding']]
            );
        }

        $this->logDebug('Retrieved document type version schema', [
            'version' => $version->versionNumber,
            'format' => $format,
            'schema_size' => strlen($decoded),
        ]);

        return $decoded;
    }

    /**
     * Validate document type version is supported.
     *
     * @param  string  $type  Document type (invoice, credit note, debit note)
     * @param  string  $version  Version number
     * @return bool True if supported
     *
     * @throws ValidationException If version is not supported
     */
    private function validateVersion(string $type, string $version): bool
    {
        switch ($type) {
            case 'debit':
                $supportedVersions = Config::DEBIT_NOTE_SUPPORTED_VERSIONS;
                break;
            default:
                throw new ValidationException('Unsupported document type');
        }

        if (! in_array($version, $supportedVersions)) {
            throw new ValidationException(
                sprintf('Version %s is not supported for %s notes', $version, $type)
            );
        }

        return true;
    }

    /**
     * Get supported versions for a document type.
     *
     * @param  string  $type  Document type
     * @return array Supported versions
     */
    public function getSupportedVersions(string $type): array
    {
        $supportedVersions = [];
        switch ($type) {
            case 'debit':
                $supportedVersions = Config::DEBIT_NOTE_SUPPORTED_VERSIONS;
                break;
            default:
                $supportedVersions = [];
                break;
        }

        return $supportedVersions;
    }
}
