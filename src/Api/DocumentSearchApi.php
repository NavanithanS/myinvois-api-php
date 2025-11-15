<?php

namespace Nava\MyInvois\Api;

use DateTimeInterface;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Traits\DateValidationTrait;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for document search operations.
 */
trait DocumentSearchApi
{
    use DateValidationTrait;

    protected $logger = null;

    private const MAX_PAGE_SIZE = 100;

    /**
     * Search for documents.
     *
     * @param  array  $filters  Search filters
     * @return array Search results
     *
     * @throws ValidationException|ApiException
     */
    public function searchDocuments(array $filters = []): array
    {
        try {
            $this->validateSearchFilters($filters);
            $query = $this->prepareSearchParams($filters);

            $this->logDebug('Searching documents', [
                'filters' => $filters,
                'query' => $query,
            ]);

            $response = $this->apiClient->request(
                'GET',
                '/api/v1.0/documents/search',
                ['query' => $query]
            );

            if (! isset($response['documents']) || ! isset($response['metadata'])) {
                throw new ApiException('Invalid response format from search endpoint');
            }

            // Normalize documents to DocumentSearchResult DTOs when present
            if (is_array($response['documents'])) {
                $response['documents'] = array_map(function (array $doc) {
                    // Ensure required keys for DTO
                    $doc += [
                        'createdByUserId' => $doc['createdByUserId'] ?? 'unknown',
                        'submissionChannel' => $doc['submissionChannel'] ?? 'ERP',
                        'supplierTIN' => $doc['supplierTIN'] ?? 'C0000000000',
                        'supplierName' => $doc['supplierName'] ?? 'Unknown Supplier',
                        'buyerName' => $doc['buyerName'] ?? 'Unknown Buyer',
                        'buyerTIN' => $doc['buyerTIN'] ?? 'C0000000000',
                    ];
                    return \Nava\MyInvois\Data\DocumentSearchResult::fromArray($doc);
                }, $response['documents']);
            }

            $this->logDebug('Document search completed', [
                'total_count' => $response['metadata']['totalCount'] ?? 0,
                'total_pages' => $response['metadata']['totalPages'] ?? 0,
            ]);

            return $response;

        } catch (ApiException $e) {
            $this->logError('Document search failed', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            throw $e;
        }
    }

    /**
     * Validate search filters.
     *
     * @throws ValidationException
     */
    private function validateSearchFilters(array $filters): void
    {
        // Validate date ranges first for specific errors
        $submissionRange = $this->validateDateRange(
            $filters['submissionDateFrom'] ?? null,
            $filters['submissionDateTo'] ?? null,
            'submission date',
            30
        );

        $issueRange = $this->validateDateRange(
            $filters['issueDateFrom'] ?? null,
            $filters['issueDateTo'] ?? null,
            'issue date',
            30
        );

        // Require at least one full date range to be provided
        if ($submissionRange['start'] === null && $issueRange['start'] === null) {
            throw new ValidationException('Either submission dates or issue dates must be provided');
        }

        // Validate page size
        if (isset($filters['pageSize'])) {
            $pageSize = (int) $filters['pageSize'];
            if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
                throw new ValidationException('Page size must be between 1 and '.self::MAX_PAGE_SIZE);
            }
        }

        // Validate invoice direction
        if (isset($filters['invoiceDirection'])) {
            $validDirections = ['Sent', 'Received'];
            if (! in_array($filters['invoiceDirection'], $validDirections, true)) {
                throw new ValidationException('Invalid invoice direction');
            }
        }

        // Validate document status
        if (isset($filters['status']) && is_string($filters['status'])) {
            if (! in_array($filters['status'], \Nava\MyInvois\Enums\DocumentStatusEnum::getValidStatuses(), true)) {
                throw new ValidationException('Invalid document status');
            }
        }

        // Validate document type code
        if (isset($filters['documentType'])) {
            $validCodes = \Nava\MyInvois\Enums\DocumentTypeEnum::getCodes();
            $code = $filters['documentType'] instanceof \Nava\MyInvois\Enums\DocumentTypeEnum
                ? $filters['documentType']->value
                : (int) $filters['documentType'];
            if (! in_array($code, $validCodes, true)) {
                throw new ValidationException('Invalid document type');
            }
        }

        // Validate search query
        if (isset($filters['searchQuery'])) {
            if (! preg_match('/^[A-Za-z0-9_\- ]+$/', (string) $filters['searchQuery'])) {
                throw new ValidationException('Search query contains invalid characters');
            }
        }
    }

    /**
     * Prepare search parameters.
     *
     * @return array Prepared parameters
     */
    private function prepareSearchParams(array $filters): array
    {
        $params = [];

        // Handle date fields
        foreach ([
            'submissionDateFrom',
            'submissionDateTo',
            'issueDateFrom',
            'issueDateTo',
        ] as $dateField) {
            if (isset($filters[$dateField])) {
                $date = $filters[$dateField];
                if ($date instanceof DateTimeInterface) {
                    $params[$dateField] = $date->format('Y-m-d\TH:i:s\Z');
                } else {
                    $params[$dateField] = (new \DateTimeImmutable($date))->format('Y-m-d\TH:i:s\Z');
                }
            }
        }

        // Add other direct parameters
        $directFields = ['pageSize', 'pageNo', 'status', 'documentType', 'uuid', 'searchQuery', 'invoiceDirection'];
        foreach ($directFields as $field) {
            if (isset($filters[$field])) {
                $value = $filters[$field];
                if ($value instanceof \Nava\MyInvois\Enums\DocumentStatusEnum) {
                    $value = $value->value;
                } elseif ($value instanceof \Nava\MyInvois\Enums\DocumentTypeEnum) {
                    $value = (string) $value->value;
                }
                $params[$field] = $value;
            }
        }

        return $params;
    }
}
