<?php

namespace Nava\MyInvois\Api;

use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for document submission status operations.
 */
trait SubmissionStatusApi
{
    private const MAX_PAGE_SIZE = 100;

    private const MIN_POLL_INTERVAL = 3; // seconds

    protected ?LoggerInterface $logger = null;

    private array $lastPollTimes = [];

    /**
     * Get details of a document submission.
     *
     * @param  string  $submissionId  Unique submission identifier
     * @param  int|null  $pageNo  Optional page number
     * @param  int|null  $pageSize  Optional page size (max 100)
     * @return array{
     *     submissionUid: string,
     *     documentCount: int,
     *     dateTimeReceived: string,
     *     overallStatus: string,
     *     documentSummary: array<array{
     *         uuid: string,
     *         submissionUid: string,
     *         longId: ?string,
     *         internalId: string,
     *         typeName: string,
     *         typeVersionName: string,
     *         issuerTin: string,
     *         issuerName: string,
     *         receiverId: ?string,
     *         receiverName: ?string,
     *         dateTimeIssued: string,
     *         dateTimeReceived: string,
     *         dateTimeValidated: ?string,
     *         totalExcludingTax: float,
     *         totalDiscount: float,
     *         totalNetAmount: float,
     *         totalPayableAmount: float,
     *         status: string,
     *         cancelDateTime: ?string,
     *         rejectRequestDateTime: ?string,
     *         documentStatusReason: ?string,
     *         createdByUserId: string
     *     }>
     * }
     *
     * @throws ValidationException If input parameters are invalid
     * @throws ApiException If API request fails
     */
    public function getSubmissionStatus(
        string $submissionId,
        ?int $pageNo = null,
        ?int $pageSize = null
    ): array {
        try {
            $this->validateSubmissionId($submissionId);
            $this->validatePaginationParams($pageNo, $pageSize);
            $this->enforcePollingInterval($submissionId);

            $query = $this->buildQueryParams($pageNo, $pageSize);

            $this->logDebug('Retrieving submission status', [
                'submissionId' => $submissionId,
                'pageNo' => $pageNo,
                'pageSize' => $pageSize,
            ]);

            $response = $this->apiClient->request(
                'GET',
                "/api/v1.0/documentsubmissions/{$submissionId}",
                ['query' => $query]
            );

            $this->validateResponse($response);
            $this->logSubmissionStatus($submissionId, $response);

            return $response;

        } catch (ApiException $e) {
            $this->logError('Failed to retrieve submission status', [
                'submissionId' => $submissionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate submission ID format.
     *
     * @throws ValidationException
     */
    private function validateSubmissionId(string $submissionId): void
    {
        Assert::notEmpty($submissionId, 'Submission ID cannot be empty');
        Assert::regex($submissionId, '/^[A-Z0-9]+$/', 'Invalid submission ID format');
    }

    /**
     * Validate pagination parameters.
     *
     * @throws ValidationException
     */
    private function validatePaginationParams(?int $pageNo, ?int $pageSize): void
    {
        if ($pageNo !== null) {
            Assert::greaterThan($pageNo, 0, 'Page number must be greater than 0');
        }

        if ($pageSize !== null) {
            Assert::range($pageSize, 1, self::MAX_PAGE_SIZE,
                sprintf('Page size must be between 1 and %d', self::MAX_PAGE_SIZE)
            );
        }
    }

    /**
     * Enforce minimum polling interval for the same submission ID.
     *
     * @throws ApiException
     */
    private function enforcePollingInterval(string $submissionId): void
    {
        $now = microtime(true);
        $lastPollTime = $this->lastPollTimes[$submissionId] ?? 0;
        $timeSinceLastPoll = $now - $lastPollTime;

        if ($timeSinceLastPoll < self::MIN_POLL_INTERVAL) {
            throw new ApiException(
                sprintf(
                    'Please wait %d seconds between status checks for the same submission',
                    self::MIN_POLL_INTERVAL
                ),
                429
            );
        }

        $this->lastPollTimes[$submissionId] = $now;
    }

    /**
     * Build query parameters for the request.
     */
    private function buildQueryParams(?int $pageNo, ?int $pageSize): array
    {
        $query = [];

        if ($pageNo !== null) {
            $query['pageNo'] = $pageNo;
        }

        if ($pageSize !== null) {
            $query['pageSize'] = $pageSize;
        }

        return $query;
    }

    /**
     * Validate the API response format.
     *
     * @throws ApiException
     */
    private function validateResponse(array $response): void
    {
        $requiredFields = [
            'submissionUid',
            'documentCount',
            'dateTimeReceived',
            'overallStatus',
            'documentSummary',
        ];

        foreach ($requiredFields as $field) {
            if (! isset($response[$field])) {
                throw new ApiException("Invalid response format: missing {$field}");
            }
        }

        Assert::isArray($response['documentSummary'],
            'Invalid response format: documentSummary must be an array'
        );
    }

    /**
     * Log submission status details.
     */
    private function logSubmissionStatus(string $submissionId, array $response): void
    {
        $this->logDebug('Retrieved submission status', [
            'submissionId' => $submissionId,
            'status' => $response['overallStatus'],
            'documentCount' => $response['documentCount'],
            'documentsReturned' => count($response['documentSummary']),
        ]);

        // Log details about failed documents if any
        $failedDocuments = array_filter(
            $response['documentSummary'],
            fn ($doc) => strtolower($doc['status']) === 'invalid'
        );

        if (! empty($failedDocuments)) {
            $this->logError('Some documents in submission have failed', [
                'submissionId' => $submissionId,
                'failedCount' => count($failedDocuments),
                'failedDocuments' => array_map(
                    fn ($doc) => [
                        'uuid' => $doc['uuid'],
                        'internalId' => $doc['internalId'],
                        'status' => $doc['status'],
                    ],
                    $failedDocuments
                ),
            ]);
        }
    }

    /**
     * Check if submission processing is complete.
     */
    public function isSubmissionComplete(array $response): bool
    {
        $status = strtolower($response['overallStatus']);

        return in_array($status, ['valid', 'partially valid', 'invalid'], true);
    }

    /**
     * Get all documents from a submission by handling pagination automatically.
     *
     * @return array All documents from the submission
     */
    public function getAllSubmissionDocuments(string $submissionId): array
    {
        $pageNo = 1;
        $pageSize = self::MAX_PAGE_SIZE;
        $allDocuments = [];

        do {
            $response = $this->getSubmissionStatus($submissionId, $pageNo, $pageSize);
            $allDocuments = array_merge($allDocuments, $response['documentSummary']);
            $pageNo++;

            // Add delay between requests to respect rate limits
            if (count($response['documentSummary']) === $pageSize) {
                usleep(self::MIN_POLL_INTERVAL * 1000000);
            }
        } while (count($response['documentSummary']) === $pageSize);

        return $allDocuments;
    }
}
