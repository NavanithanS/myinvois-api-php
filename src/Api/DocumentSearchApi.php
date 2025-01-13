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

    private $MAX_PAGE_SIZE = 100;

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

            if (! isset($response['result']) || ! isset($response['metadata'])) {
                throw new ApiException('Invalid response format from search endpoint');
            }

            $this->logDebug('Search completed successfully', [
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
        // Validate page size
        if (isset($filters['pageSize'])) {
            Assert::range(
                $filters['pageSize'],
                1,
                self::$MAX_PAGE_SIZE,
                'Page size must be between 1 and '.self::$MAX_PAGE_SIZE
            );
        }

        // Validate date ranges
        $this->validateDateRange(
            $filters['submissionDateFrom'] ?? null,
            $filters['submissionDateTo'] ?? null,
            'submission date',
            30
        );

        $this->validateDateRange(
            $filters['issueDateFrom'] ?? null,
            $filters['issueDateTo'] ?? null,
            'issue date',
            30
        );

        // Add other validations as needed...
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
        $directFields = ['pageSize', 'pageNo', 'status', 'documentType'];
        foreach ($directFields as $field) {
            if (isset($filters[$field])) {
                $params[$field] = $filters[$field];
            }
        }

        return $params;
    }
}
