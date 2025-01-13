<?php

namespace Nava\MyInvois\Api;

use DateTimeInterface;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Traits\DateValidationTrait;
use Psr\Log\LoggerInterface;

/**
 * API trait for retrieving recent documents.
 */
trait RecentDocumentsApi
{
    use DateValidationTrait;

    protected $logger = null;

    /**
     * Get recent documents with optional filtering.
     *
     * @param  array  $filters  Optional filters
     * @return array Filtered documents
     *
     * @throws ApiException|ValidationException
     */
    public function getRecentDocuments(array $filters = []): array
    {
        try {
            $this->validateRecentDocumentsFilters($filters);
            $query = $this->prepareRecentDocumentsFilters($filters);

            $this->logDebug('Retrieving recent documents', [
                'filters' => $filters,
                'query' => $query,
            ]);

            $response = $this->apiClient->request(
                'GET',
                '/api/v1.0/documents/recent',
                ['query' => $query]
            );

            if (! isset($response['result']) || ! isset($response['metadata'])) {
                throw new ApiException('Invalid response format from recent documents endpoint');
            }

            $this->logDebug('Retrieved recent documents successfully', [
                'count' => count($response['result']),
                'total_count' => $response['metadata']['totalCount'] ?? 0,
                'total_pages' => $response['metadata']['totalPages'] ?? 0,
            ]);

            return $response;

        } catch (ApiException $e) {
            $this->logError('Failed to retrieve recent documents', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            throw $e;
        }
    }

    /**
     * Validate recent documents filter parameters.
     *
     * @throws ValidationException
     */
    private function validateRecentDocumentsFilters(array $filters): void
    {
        // Validate date ranges
        $this->validateDateRange(
            $filters['submissionDateFrom'] ?? null,
            $filters['submissionDateTo'] ?? null,
            'submission date',
            31
        );

        $this->validateDateRange(
            $filters['issueDateFrom'] ?? null,
            $filters['issueDateTo'] ?? null,
            'issue date',
            31
        );

        // Add other validations...
    }

    /**
     * Prepare filter parameters for the API request.
     *
     * @return array Prepared parameters
     */
    private function prepareRecentDocumentsFilters(array $filters): array
    {
        $query = [];

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
                    $query[$dateField] = $date->format('Y-m-d\TH:i:s\Z');
                } else {
                    $query[$dateField] = (new \DateTimeImmutable($date))->format('Y-m-d\TH:i:s\Z');
                }
            }
        }

        // Handle other direct fields
        $directFields = ['pageNo', 'pageSize', 'invoiceDirection', 'status', 'documentType'];
        foreach ($directFields as $field) {
            if (isset($filters[$field])) {
                $query[$field] = $filters[$field];
            }
        }

        return $query;
    }
}
