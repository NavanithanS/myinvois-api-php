<?php

namespace Nava\MyInvois;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nava\MyInvois\Api\DocumentDetailsApi;
use Nava\MyInvois\Api\DocumentRejectionApi;
use Nava\MyInvois\Api\DocumentRetrievalApi;
use Nava\MyInvois\Api\DocumentSearchApi;
use Nava\MyInvois\Api\DocumentSubmissionApi;
use Nava\MyInvois\Api\DocumentTypesApi;
use Nava\MyInvois\Api\DocumentTypeVersionsApi;
use Nava\MyInvois\Api\NotificationsApi;
use Nava\MyInvois\Api\RecentDocumentsApi;
use Nava\MyInvois\Api\SubmissionStatusApi;
use Nava\MyInvois\Api\TaxpayerApi;
use Nava\MyInvois\Auth\AuthenticationClient;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Http\ApiClient;
use Nava\MyInvois\Traits\DateValidationTrait;
use Nava\MyInvois\Traits\LoggerTrait;
use Nava\MyInvois\Traits\UuidValidationTrait;
use Webmozart\Assert\Assert;

/**
 * MyInvois API Client
 *
 * This client library provides a robust interface to the MyInvois API for
 * submitting and managing tax documents in Malaysia.
 *
 * Key features:
 * - Authentication and token management
 * - Document submission and retrieval
 * - Validation and error handling
 * - Rate limiting and retry logic
 *
 * @package Nava\MyInvois
 * @author Nava
 * @license MIT
 */

class MyInvoisClient
{

    private readonly ApiClient $apiClient;
    private readonly string $clientId;
    private readonly array $config;
    private readonly CacheRepository $cache;

    use LoggerTrait;
    use DateValidationTrait;
    use UuidValidationTrait;

    use DocumentDetailsApi;
    use DocumentRetrievalApi;
    use TaxpayerApi;
    use SubmissionStatusApi;
    use RecentDocumentsApi;
    use NotificationsApi;
    use DocumentTypeVersionsApi;
    use DocumentTypesApi;
    use DocumentSubmissionApi;
    use DocumentSearchApi;
    use DocumentRejectionApi;

    public const PRODUCTION_URL = 'https://myinvois.hasil.gov.my';
    public const SANDBOX_URL = 'https://preprod.myinvois.hasil.gov.my';
    public const IDENTITY_PRODUCTION_URL = 'https://api.myinvois.hasil.gov.my/connect/token';
    public const IDENTITY_SANDBOX_URL = 'https://preprod-api.myinvois.hasil.gov.my/connect/token';

    public function __construct(
        string $clientId,
        string $clientSecret,
        CacheRepository $cache,
        GuzzleClient $httpClient,
        string $baseUrl = self::PRODUCTION_URL,
        array $config = []
    ) {
        Assert::notEmpty($clientId, 'Client ID cannot be empty');
        Assert::notEmpty($clientSecret, 'Client secret cannot be empty');

        $this->clientId = $clientId;
        $this->config = $config;
        $this->cache = $cache;

        // Determine the identity service URL based on the API URL
        $identityUrl = str_contains($baseUrl, 'preprod')
        ? self::IDENTITY_SANDBOX_URL
        : self::IDENTITY_PRODUCTION_URL;

        $authClient = new AuthenticationClient(
            clientId: $clientId,
            clientSecret: $clientSecret,
            baseUrl: $identityUrl,
            httpClient: $httpClient,
            cache: $cache,
            config: array_merge([
                'cache' => [
                    'enabled' => $config['cache']['enabled'] ?? true,
                    'ttl' => $config['cache']['ttl'] ?? 3600,
                ],
                'logging' => [
                    'enabled' => $config['logging']['enabled'] ?? true,
                    'channel' => $config['logging']['channel'] ?? 'stack',
                ],
            ], $config)
        );

        // Configure the API client with authentication settings
        $this->apiClient = new ApiClient(
            clientId: $clientId,
            clientSecret: $clientSecret,
            baseUrl: $baseUrl,
            httpClient: $httpClient,
            cache: $this->cache,
            authClient: $authClient,
            config: array_merge($config, [
                'auth' => [
                    'url' => $identityUrl,
                ],
                'cache' => [
                    'enabled' => $config['cache']['enabled'] ?? true,
                    'store' => $cache,
                    'ttl' => $config['cache']['ttl'] ?? 3600,
                ],
            ])
        );
    }

    /**
     * Submit a new invoice document.
     *
     * @param array $invoice Invoice data following MyInvois schema
     * @return array Response containing the document ID and status
     * @throws ValidationException|ApiException
     */
    public function submitInvoice(array $invoice): array
    {
        $this->validateInvoiceData($invoice);

        $preparer = new InvoiceDataPreparer();
        $preparedInvoice = $preparer->prepare($invoice);

        return $this->apiClient->request('POST', '/documents', [
            'json' => $preparedInvoice,
        ]);
    }

    /**
     * Get the status of a document.
     *
     * @param string $documentId The document ID
     * @return array Document status and details
     * @throws ApiException
     */
    public function getDocumentStatus(string $documentId): array
    {
        $cacheKey = "document_status_{$documentId}";

        return $this->cache->get($cacheKey, function () use ($documentId) {
            return $this->apiClient->request('GET', "/documents/{$documentId}");
        });
    }

    /**
     * List documents with optional filtering.
     *
     * @param array $filters Optional filters
     *     @option string $startDate Start date (YYYY-MM-DD)
     *     @option string $endDate End date (YYYY-MM-DD)
     *     @option string $status Document status
     *     @option int $page Page number
     *     @option int $perPage Items per page
     * @return array Paginated list of documents
     * @throws ApiException
     */
    public function listDocuments(array $filters = []): array
    {
        $preparer = new DocumentFilterPreparer();
        $preparedFilters = $preparer->prepare($filters);

        return $this->apiClient->request('GET', '/documents', [
            'query' => $preparedFilters,
        ]);
    }

    /**
     * Cancel a document.
     *
     * @param string $documentId The document ID to cancel
     * @param string $reason Reason for cancellation
     * @return array Cancellation status
     * @throws ApiException
     */
    public function cancelDocument(string $documentId, string $reason): array
    {
        return $this->apiClient->request('POST', "/documents/{$documentId}/cancel", [
            'json' => [
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Get document PDF.
     *
     * @param string $documentId The document ID
     * @return string Binary PDF content
     * @throws ApiException
     */
    public function getDocumentPdf(string $documentId): string
    {
        $response = $this->apiClient->request('GET', "/documents/{$documentId}/pdf", [
            'headers' => [
                'Accept' => 'application/pdf',
            ],
        ]);

        return base64_decode($response['content']);
    }

    /**
     * Get document events history.
     *
     * @param string $documentId The document ID
     * @return array List of document events
     * @throws ApiException
     */
    public function getDocumentHistory(string $documentId): array
    {
        return $this->apiClient->request('GET', "/documents/{$documentId}/history");
    }

    /**
     * Validate invoice data without submitting.
     *
     * @param array $invoice Invoice data to validate
     * @return array Validation results
     * @throws ApiException
     */
    public function validateInvoice(array $invoice): array
    {
        return $this->apiClient->request('POST', '/documents/validate', [
            'json' => $this->prepareInvoiceData($invoice),
        ]);
    }

    /**
     * Get current API status and service health.
     *
     * @return array API status information
     * @throws ApiException
     */
    public function getApiStatus(): array
    {
        return $this->apiClient->request('GET', '/status');
    }

    private function validateInvoiceData(array $invoice): void
    {
        Assert::notEmpty($invoice['issueDate'] ?? null, 'Invoice issueDate is required');
        Assert::notEmpty($invoice['totalAmount'] ?? null, 'Invoice totalAmount is required');
        // Add more validation rules as needed
    }

    private function formatAmount(float $amount): string
    {
        return sprintf('%.2f', $amount);
    }

    private function prepareInvoiceData(array $invoice): array
    {
        return [
            'issueDate' => date('Y-m-d', strtotime($invoice['issueDate'] ?? '')),
            'dueDate' => $invoice['dueDate'] ? date('Y-m-d', strtotime($invoice['dueDate'])) : null,
            'serviceDate' => $invoice['serviceDate'] ? date('Y-m-d', strtotime($invoice['serviceDate'])) : null,
            'totalAmount' => $this->formatAmount($invoice['totalAmount'] ?? 0),
            'taxAmount' => $this->formatAmount($invoice['taxAmount'] ?? 0),
            'discountAmount' => $this->formatAmount($invoice['discountAmount'] ?? 0),
            'items' => $this->prepareInvoiceItems($invoice['items'] ?? []),
        ];
    }

    private function prepareInvoiceItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'description' => $item['description'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'unitPrice' => $this->formatAmount($item['unitPrice'] ?? 0),
                'taxAmount' => $this->formatAmount($item['taxAmount'] ?? 0),
            ];
        }, $items);
    }

    /**
     * Prepare filters for listing documents.
     *
     * @param array $filters Raw filters
     * @return array Prepared filters
     */
    private function prepareListFilters(array $filters): array
    {
        $prepared = [];

        // Handle date filters
        foreach (['startDate', 'endDate'] as $dateField) {
            if (isset($filters[$dateField])) {
                $prepared[$dateField] = date('Y-m-d', strtotime($filters[$dateField]));
            }
        }

        // Handle pagination
        $prepared['page'] = $filters['page'] ?? 1;
        $prepared['perPage'] = min($filters['perPage'] ?? 50, 100); // Limit max per page

        // Handle other filters
        if (isset($filters['status'])) {
            $prepared['status'] = $filters['status'];
        }

        return $prepared;
    }

    public function submitDebitNote(array $document, string $version = null): array
    {
        // Use provided version or fallback to current version
        $version = $version ?? Config::DEBIT_NOTE_CURRENT_VERSION;

        // Validate version is supported
        if (!in_array($version, Config::DEBIT_NOTE_SUPPORTED_VERSIONS)) {
            throw new ValidationException('Unsupported debit note version');
        }

        // Add version to document
        $document['invoiceTypeCode'] = [
            'value' => '03', // Debit note type code
            'listVersionID' => $version,
        ];

        // Submit document using existing logic
        return $this->submitDocument($document);
    }

    /**
     * Submit a refund note document.
     *
     * @param array $document Refund note data following MyInvois schema
     * @param ?string $version Version to use (defaults to current version)
     * @return array Submission response
     * @throws ValidationException|ApiException
     */
    public function submitRefundNote(array $document, ?string $version = null): array
    {
        // Use provided version or current version
        $version = $version ?? Config::REFUND_NOTE_CURRENT_VERSION;

        // Validate version is supported
        if (!Config::isVersionSupported('refund_note', $version)) {
            throw new ValidationException('Unsupported refund note version');
        }

        // Add version to document
        $document['invoiceTypeCode'] = [
            'value' => Config::REFUND_NOTE_TYPE_CODE,
            'listVersionID' => $version,
        ];

        // Submit using base submission logic
        return $this->submitDocument($document);
    }
}

// Create a new class for invoice data preparation
class InvoiceDataPreparer
{
    public function prepare(array $invoice): array
    {
        $this->validate($invoice);

        // Format dates
        foreach (['issueDate', 'dueDate', 'serviceDate'] as $dateField) {
            if (isset($invoice[$dateField])) {
                $invoice[$dateField] = date('Y-m-d', strtotime($invoice[$dateField]));
            }
        }

        // Format amounts
        foreach (['totalAmount', 'taxAmount', 'discountAmount'] as $amountField) {
            if (isset($invoice[$amountField])) {
                $invoice[$amountField] = sprintf('%.2f', $invoice[$amountField]);
            }
        }

        // Prepare items if present
        if (isset($invoice['items'])) {
            $invoice['items'] = array_map([$this, 'prepareItem'], $invoice['items']);
        }

        return $invoice;
    }

    private function validate(array $invoice): void
    {
        Assert::notEmpty($invoice['issueDate'] ?? null, 'Issue date is required');
        Assert::notEmpty($invoice['totalAmount'] ?? null, 'Total amount is required');
        Assert::numeric($invoice['totalAmount'] ?? null, 'Total amount must be numeric');
        Assert::greaterThan($invoice['totalAmount'] ?? 0, 0, 'Total amount must be greater than 0');

        if (isset($invoice['items'])) {
            Assert::isArray($invoice['items'], 'Items must be an array');
            foreach ($invoice['items'] as $item) {
                $this->validateItem($item);
            }
        }
    }

    private function validateItem(array $item): void
    {
        Assert::notEmpty($item['description'] ?? null, 'Item description is required');
        Assert::numeric($item['quantity'] ?? null, 'Item quantity must be numeric');
        Assert::numeric($item['unitPrice'] ?? null, 'Item unit price must be numeric');
    }

    private function prepareItem(array $item): array
    {
        return [
            'description' => $item['description'],
            'quantity' => (int) $item['quantity'],
            'unitPrice' => sprintf('%.2f', $item['unitPrice']),
            'taxAmount' => sprintf('%.2f', $item['taxAmount'] ?? 0),
            'totalAmount' => sprintf('%.2f', $item['quantity'] * $item['unitPrice']),
        ];
    }
}

// Create a new class for document filter preparation
class DocumentFilterPreparer
{
    public function prepare(array $filters): array
    {
        $prepared = [];

        // Handle date filters
        foreach (['startDate', 'endDate'] as $dateField) {
            if (isset($filters[$dateField])) {
                $prepared[$dateField] = date('Y-m-d', strtotime($filters[$dateField]));
            }
        }

        // Handle pagination with validation
        $prepared['page'] = max(1, $filters['page'] ?? 1);
        $prepared['perPage'] = min(max(1, $filters['perPage'] ?? 50), 100);

        // Handle status filter with validation
        if (isset($filters['status'])) {
            Assert::inArray($filters['status'], [
                'PENDING', 'COMPLETED', 'FAILED', 'CANCELLED',
            ], 'Invalid status value');
            $prepared['status'] = $filters['status'];
        }

        return $prepared;
    }
}
