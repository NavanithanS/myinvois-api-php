<?php

namespace Nava\MyInvois\Api;

use Nava\MyInvois\Data\DocumentType;
use Nava\MyInvois\Data\WorkflowParameter;
use Nava\MyInvois\Enums\DocumentType as DocumentTypeEnum;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for document type operations.
 *
 * This trait provides methods for interacting with document types in the MyInvois system.
 * It includes functionality for retrieving, validating, and managing document types,
 * their versions, and associated workflow parameters.
 */
trait DocumentTypesApi
{
    protected ?LoggerInterface $logger = null;

    /**
     * Retrieve all document types from the MyInvois system.
     *
     * @throws ApiException If the API request fails
     * @return DocumentType[]
     */
    public function getDocumentTypes(): array
    {
        try {
            $response = $this->apiClient->request('GET', '/api/v1.0/documenttypes');

            if (!isset($response['result']) || !is_array($response['result'])) {
                throw new ApiException('Invalid response format from document types endpoint');
            }

            $types = array_map(
                fn(array $type) => DocumentType::fromArray($type),
                $response['result']
            );

            $this->logDebug('Retrieved document types successfully', [
                'count' => count($types),
                'active_count' => count(array_filter($types, fn($type) => $type->isActive())),
            ]);

            return $types;
        } catch (ApiException $e) {
            $this->logError('Failed to retrieve document types', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get a specific document type by its ID.
     *
     * @param int $id The unique identifier of the document type
     * @return DocumentType
     * @throws ApiException If the API request fails
     * @throws ValidationException If the document type ID is invalid
     */
    public function getDocumentType(int $id): DocumentType
    {
        try {
            Assert::greaterThan($id, 0, 'Document type ID must be greater than 0');

            $response = $this->apiClient->request('GET', "/api/v1.0/documenttypes/{$id}");

            if (!isset($response['result'])) {
                throw new ApiException('Invalid response format from document type endpoint');
            }

            $type = DocumentType::fromArray($response['result']);

            $this->logDebug('Retrieved document type successfully', [
                'id' => $id,
                'type' => $type->invoiceTypeCode,
                'is_active' => $type->isActive(),
                'version_count' => count($type->documentTypeVersions),
                'parameter_count' => count($type->workflowParameters),
            ]);

            return $type;
        } catch (ApiException $e) {
            $this->logError('Failed to retrieve document type', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get active document types only.
     *
     * @return DocumentType[]
     * @throws ApiException If the API request fails
     */
    public function getActiveDocumentTypes(): array
    {
        $types = $this->getDocumentTypes();
        $activeTypes = array_filter($types, fn(DocumentType $type) => $type->isActive());

        $this->logDebug('Retrieved active document types', [
            'total' => count($types),
            'active' => count($activeTypes),
        ]);

        return array_values($activeTypes); // Reset array keys
    }

    /**
     * Find document types by their invoice type codes.
     *
     * @param int[] $codes Array of invoice type codes to search for
     * @return DocumentType[] Array of found document types indexed by code
     * @throws ValidationException If any code is invalid
     * @throws ApiException If the API request fails
     */
    public function findDocumentTypesByCodes(array $codes): array
    {
        try {
            $validCodes = array_map(fn($case) => $case->value, DocumentTypeEnum::cases());

            foreach ($codes as $code) {
                Assert::inArray($code, $validCodes, sprintf(
                    'Invalid document type code: %d. Valid codes are: %s',
                    $code,
                    implode(', ', $validCodes)
                ));
            }

            $types = $this->getDocumentTypes();
            $foundTypes = [];

            foreach ($types as $type) {
                if (in_array($type->invoiceTypeCode, $codes, true)) {
                    $foundTypes[$type->invoiceTypeCode] = $type;
                }
            }

            $this->logDebug('Found document types by codes', [
                'requested_codes' => $codes,
                'found_codes' => array_keys($foundTypes),
            ]);

            return $foundTypes;
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                $e->getMessage(),
                ['codes' => ['One or more provided codes are not valid document types.']]
            );
        }
    }

    /**
     * Find a single document type by its invoice type code.
     *
     * @param int $code The invoice type code to search for
     * @return DocumentType|null Returns null if no matching document type is found
     * @throws ValidationException If the code is invalid
     * @throws ApiException If the API request fails
     */
    public function findDocumentTypeByCode(int $code): ?DocumentType
    {
        $types = $this->findDocumentTypesByCodes([$code]);
        return $types[$code] ?? null;
    }

    /**
     * Validate if a document type code is currently active.
     *
     * @param int $code The invoice type code to validate
     * @return bool
     * @throws ValidationException If the code is invalid
     * @throws ApiException If the API request fails
     */
    public function isDocumentTypeActive(int $code): bool
    {
        $type = $this->findDocumentTypeByCode($code);
        $isActive = null !== $type && $type->isActive();

        $this->logDebug('Checked document type active status', [
            'code' => $code,
            'exists' => null !== $type,
            'is_active' => $isActive,
        ]);

        return $isActive;
    }

    /**
     * Get all supported document type codes.
     *
     * @return array<int>
     */
    public function getSupportedDocumentTypeCodes(): array
    {
        $codes = array_map(
            fn($case) => $case->value,
            DocumentTypeEnum::cases()
        );

        $this->logDebug('Retrieved supported document type codes', [
            'count' => count($codes),
        ]);

        return $codes;
    }

    /**
     * Validate if a document type version exists and is active.
     *
     * @param int $documentTypeId The document type ID
     * @param float $versionNumber The version number to validate
     * @return bool
     * @throws ValidationException If the document type ID is invalid
     * @throws ApiException If the API request fails
     */
    public function isDocumentTypeVersionActive(int $documentTypeId, float $versionNumber): bool
    {
        try {
            Assert::greaterThan($documentTypeId, 0, 'Document type ID must be greater than 0');
            Assert::greaterThan($versionNumber, 0, 'Version number must be greater than 0');

            $documentType = $this->getDocumentType($documentTypeId);

            foreach ($documentType->documentTypeVersions as $version) {
                if ($version->versionNumber === $versionNumber) {
                    $isActive = $version->isActive();
                    $this->logDebug('Checked version active status', [
                        'documentTypeId' => $documentTypeId,
                        'versionNumber' => $versionNumber,
                        'is_active' => $isActive,
                    ]);
                    return $isActive;
                }
            }

            $this->logDebug('Version not found', [
                'documentTypeId' => $documentTypeId,
                'versionNumber' => $versionNumber,
            ]);
            return false;

        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                $this->logDebug('Document type not found', ['id' => $documentTypeId]);
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get workflow parameter value for a document type.
     *
     * @param int $documentTypeId Document type ID
     * @param string $parameterName Parameter name
     * @return int|null Parameter value if found and active, null otherwise
     * @throws ApiException If API request fails
     * @throws ValidationException If validation fails
     */
    public function getWorkflowParameterValue(int $documentTypeId, string $parameterName): ?int
    {
        Assert::inArray($parameterName, WorkflowParameter::getValidParameters(),
            'Invalid workflow parameter name'
        );

        try {
            $documentType = $this->getDocumentType($documentTypeId);
            $parameter = $documentType->getWorkflowParameter($parameterName);

            if ($parameter) {
                $this->logDebug('Retrieved workflow parameter', [
                    'documentTypeId' => $documentTypeId,
                    'parameter' => $parameterName,
                    'value' => $parameter->value,
                    'is_active' => $parameter->isActive(),
                ]);
            }

            return $parameter?->value;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Get all active workflow parameters for a document type.
     *
     * @param int $documentTypeId Document type ID
     * @return WorkflowParameter[]
     * @throws ApiException If API request fails
     * @throws ValidationException If validation fails
     */
    public function getActiveWorkflowParameters(int $documentTypeId): array
    {
        try {
            $documentType = $this->getDocumentType($documentTypeId);
            $parameters = $documentType->getActiveWorkflowParameters();

            $this->logDebug('Retrieved active workflow parameters', [
                'documentTypeId' => $documentTypeId,
                'count' => count($parameters),
                'parameter_names' => array_map(fn($p) => $p->parameter, $parameters),
            ]);

            return $parameters;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Check if a document type has a specific active workflow parameter.
     *
     * @param int $documentTypeId Document type ID
     * @param string $parameterName Parameter name
     * @return bool
     * @throws ApiException If API request fails
     * @throws ValidationException If validation fails
     */
    public function hasActiveWorkflowParameter(int $documentTypeId, string $parameterName): bool
    {
        Assert::inArray($parameterName, WorkflowParameter::getValidParameters(),
            'Invalid workflow parameter name'
        );

        try {
            $documentType = $this->getDocumentType($documentTypeId);
            $parameter = $documentType->getWorkflowParameter($parameterName);

            $isActive = null !== $parameter && $parameter->isActive();

            $this->logDebug('Checked workflow parameter status', [
                'documentTypeId' => $documentTypeId,
                'parameter' => $parameterName,
                'exists' => null !== $parameter,
                'is_active' => $isActive,
            ]);

            return $isActive;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

}
