<?php

namespace Nava\MyInvois\Api;

use DateTimeImmutable;
use Nava\MyInvois\Data\Document;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Traits\DateValidationTrait;
use Nava\MyInvois\Traits\UuidValidationTrait;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for document retrieval operations.
 */
trait DocumentRetrievalApi
{
    protected $logger = null;

    use UuidValidationTrait;
    use DateValidationTrait;

    /**
     * Get a document by its unique ID.
     *
     * This method retrieves the complete document including original content and metadata.
     * For documents with invalid status, use getDocumentDetails() instead.
     *
     * @param  string  $uuid  Document's unique identifier
     * @return array{
     *     uuid: string,
     *     submissionUid: string,
     *     longId: string,
     *     internalId: string,
     *     typeName: string,
     *     typeVersionName: string,
     *     issuerTin: string,
     *     issuerName: string,
     *     receiverId?: string,
     *     receiverName?: string,
     *     dateTimeIssued: DateTimeImmutable,
     *     dateTimeReceived: DateTimeImmutable,
     *     dateTimeValidated: ?DateTimeImmutable,
     *     totalExcludingTax: float,
     *     totalDiscount: float,
     *     totalNetAmount: float,
     *     totalPayableAmount: float,
     *     status: string,
     *     cancelDateTime?: DateTimeImmutable,
     *     rejectRequestDateTime?: DateTimeImmutable,
     *     documentStatusReason?: string,
     *     createdByUserId: string,
     *     document: Document
     * }
     *
     * @throws ValidationException If UUID format is invalid
     * @throws ApiException If the API request fails
     */
    public function getDocument(string $uuid): array
    {
        try {
            $this->validateUuid($uuid);

            $this->logDebug('Retrieving document', ['uuid' => $uuid]);

            $response = $this->apiClient->request(
                'GET',
                "/api/v1.0/documents/{$uuid}/raw"
            );

            if (!isset($response['uuid'])) {
                throw new ApiException('Invalid response format from document endpoint');
            }

            $document = $this->mapDocumentResponse($response);

            $this->logDebug('Retrieved document successfully', [
                'uuid' => $uuid,
                'status' => $document['status'],
                'type' => $document['typeName'],
            ]);

            return $document;

        } catch (ApiException $e) {
            $this->handleDocumentRetrievalError($e, $uuid);
            throw $e;
        }
    }

    /**
     * Get a document's shareable URL.
     *
     * @param  string  $uuid  Document's unique identifier
     * @param  string  $longId  Document's long ID (must be obtained first via getDocument())
     * @return string The shareable URL
     *
     * @throws ValidationException If parameters are invalid
     */
    public function getDocumentShareableUrl(string $uuid, string $longId): string
    {
        $this->validateUuid($uuid);
        Assert::notEmpty($longId, 'Long ID cannot be empty');
        Assert::regex($longId, '/^[A-Z0-9\s]{40,}$/', 'Invalid long ID format');

        $baseUrl = rtrim($this->config['base_url'] ?? '', '/');

        return "{$baseUrl}/{$uuid}/share/{$longId}";
    }

    /**
     * Map API response to structured document data.
     */
    private function mapDocumentResponse(array $response): array
    {
        // Map date fields to DateTimeImmutable objects
        $dateFields = [
            'dateTimeIssued',
            'dateTimeReceived',
            'dateTimeValidated',
            'cancelDateTime',
            'rejectRequestDateTime',
        ];

        foreach ($dateFields as $field) {
            if (isset($response[$field])) {
                $response[$field] = $this->parseDate($response[$field]);
            }
        }

        // Convert numeric amounts to float
        $amountFields = [
            'totalExcludingTax',
            'totalDiscount',
            'totalNetAmount',
            'totalPayableAmount',
        ];

        foreach ($amountFields as $field) {
            if (isset($response[$field])) {
                $response[$field] = (float) $response[$field];
            }
        }

        return $response;
    }

    /**
     * Handle document retrieval specific errors.
     *
     * @throws ApiException
     */
    private function handleDocumentRetrievalError(ApiException $e, string $uuid): void
    {
        $statusCode = $e->getCode();
        $message = $e->getMessage();

        $this->logError('Document retrieval failed', [
            'uuid' => $uuid,
            'status_code' => $statusCode,
            'error' => $message,
        ]);

        switch ($statusCode) {
            case 404:
                // Handle different not found scenarios
                if (str_contains($message, 'invalid status')) {
                    throw new ApiException(
                        'Document exists but has invalid status. Use getDocumentDetails() instead.',
                        404,
                        $e
                    );
                }
                if (str_contains($message, 'submitted status')) {
                    throw new ApiException(
                        'Document exists but is still in submitted status.',
                        404,
                        $e
                    );
                }
                break;

            case 403:
                if (str_contains($message, 'not authorized')) {
                    throw new ApiException(
                        'Not authorized to access this document.',
                        403,
                        $e
                    );
                }
                break;
        }
    }
}
