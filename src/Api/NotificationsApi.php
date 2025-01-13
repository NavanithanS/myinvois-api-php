<?php

namespace Nava\MyInvois\Api;

use DateTimeInterface;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for notification operations.
 */
trait NotificationsApi
{
    protected $logger = null;

    /**
     * Get notifications with optional filtering.
     *
     * @param  array  $filters  Optional filters for notifications
     *
     *     @option DateTimeInterface|string $dateFrom Start date for notifications
     *     @option DateTimeInterface|string $dateTo End date for notifications
     *     @option int $type Type of notifications (see NotificationTypeEnum)
     *     @option string $language Language of notifications ('ms' or 'en')
     *     @option int $status Status of notifications (see NotificationStatusEnum)
     *     @option int $pageNo Page number for pagination
     *     @option int $pageSize Number of items per page (max 100)
     *
     * @return array{
     *     result: array,
     *     metadata: array{hasNext: bool}
     * }
     *
     * @throws ApiException|ValidationException
     */
    public function getNotifications(array $filters = []): array
    {
        try {
            $this->validateNotificationFilters($filters);

            $query = $this->prepareNotificationFilters($filters);

            $response = $this->apiClient->request(
                'GET',
                '/api/v1.0/notifications/taxpayer',
                ['query' => $query]
            );

            if (! isset($response['result']) || ! isset($response['metadata'])) {
                throw new ApiException('Invalid response format from notifications endpoint');
            }

            $this->logDebug('Retrieved notifications successfully', [
                'count' => count($response['result']),
                'has_next' => $response['metadata']['hasNext'] ?? false,
                'filters' => $filters,
            ]);

            return $response;
        } catch (ApiException $e) {
            $this->logError('Failed to retrieve notifications', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            throw $e;
        }
    }

    /**
     * Validate notification filter parameters.
     *
     * @param  array  $filters  Filter parameters to validate
     *
     * @throws ValidationException
     */
    private function validateNotificationFilters(array $filters): void
    {
        if (isset($filters['pageSize'])) {
            Assert::range($filters['pageSize'], 1, 100, 'Page size must be between 1 and 100');
        }

        if (isset($filters['pageNo'])) {
            Assert::greaterThan($filters['pageNo'], 0, 'Page number must be greater than 0');
        }

        if (isset($filters['language'])) {
            Assert::inArray($filters['language'], ['ms', 'en'], 'Language must be either "ms" or "en"');
        }

        if (isset($filters['type'])) {
            Assert::inArray($filters['type'], [3, 6, 7, 8, 10, 11, 15, 26, 33, 34, 35],
                'Invalid notification type'
            );
        }

        if (isset($filters['status'])) {
            Assert::inArray($filters['status'], [1, 2, 3, 4, 5],
                'Invalid notification status'
            );
        }

        // Validate date range
        if (isset($filters['dateFrom'], $filters['dateTo'])) {
            $dateFrom = $filters['dateFrom'] instanceof DateTimeInterface
            ? $filters['dateFrom']
            : new \DateTimeImmutable($filters['dateFrom']);

            $dateTo = $filters['dateTo'] instanceof DateTimeInterface
            ? $filters['dateTo']
            : new \DateTimeImmutable($filters['dateTo']);

            if ($dateFrom > $dateTo) {
                throw new ValidationException(
                    'Start date must be before end date',
                    ['dateFrom' => ['Start date must be before end date']]
                );
            }
        }
    }

    /**
     * Prepare notification filter parameters for the API request.
     *
     * @param  array  $filters  Raw filter parameters
     * @return array Prepared query parameters
     */
    private function prepareNotificationFilters(array $filters): array
    {
        $query = [];

        // Handle date filters
        foreach (['dateFrom', 'dateTo'] as $dateField) {
            if (isset($filters[$dateField])) {
                $date = $filters[$dateField];
                if ($date instanceof DateTimeInterface) {
                    $query[$dateField] = $date->format('Y-m-d\TH:i:s\Z');
                } else {
                    $query[$dateField] = (new \DateTimeImmutable($date))->format('Y-m-d\TH:i:s\Z');
                }
            }
        }

        // Handle other filters
        $directFields = ['type', 'language', 'status', 'pageNo', 'pageSize'];
        foreach ($directFields as $field) {
            if (isset($filters[$field])) {
                $query[$field] = $filters[$field];
            }
        }

        return $query;
    }
}
