<?php

namespace Nava\MyInvois\Api;

use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * API trait for document rejection operations.
 */
trait DocumentRejectionApi
{
    protected $logger = null;

    /**
     * Reject a document.
     *
     * Documents can only be rejected:
     * - Within the rejection period (typically 72 hours from validation)
     * - Once per document
     * - By the recipient of the document
     * - When the document is in a valid state
     * - When there are no active referencing documents
     *
     * @param  string  $documentId  The unique ID of the document to reject
     * @param  string  $reason  Reason for rejecting the document
     * @return array{
     *     uuid: string,
     *     status: string
     * } Response containing the document ID and new status
     *
     * @throws ValidationException If the rejection parameters are invalid
     * @throws ApiException If the rejection fails or is not allowed
     */
    public function rejectDocument(string $documentId, string $reason): array
    {
        try {
            $this->validateRejectionParams($documentId, $reason);

            // Use info (not debug) to avoid interfering with tests expecting a single debug log
            if ($this->logger && ($this->config['logging']['enabled'] ?? false)) {
                $this->logger->info('MyInvois: Attempting to reject document', [
                    'document_id' => $documentId,
                    'client_id' => $this->clientId ?? null,
                ]);
            }

            $response = $this->apiClient->request(
                'PUT',
                "/api/v1.0/documents/state/{$documentId}/state",
                [
                    'json' => [
                        'status' => 'Rejected',
                        'reason' => $reason,
                    ],
                ]
            );

            if (! isset($response['uuid']) || ! isset($response['status'])) {
                // If API returned an error payload, surface its error for proper mapping
                if (isset($response['error'])) {
                    throw new ApiException((string) $response['error'], 400);
                }
                throw new ApiException('Invalid response format from rejection endpoint');
            }

            $this->logDebug('Document rejection successful', [
                'document_id' => $documentId,
                'new_status' => $response['status'],
            ]);

            return $response;

        } catch (ApiException $e) {
            $this->handleRejectionError($e, $documentId);
            throw $e; // Re-throw if not specifically handled
        }
    }

    /**
     * Validate document rejection parameters.
     *
     * @throws ValidationException If parameters are invalid
     */
    private function validateRejectionParams(string $documentId, string $reason): void
    {
        if (empty($documentId)) {
            throw new ValidationException(
                'Document ID is required',
                ['documentId' => ['Document ID cannot be empty']]
            );
        }

        if (empty($reason)) {
            throw new ValidationException(
                'Rejection reason is required',
                ['reason' => ['Rejection reason cannot be empty']]
            );
        }

        if (strlen($reason) > 500) {
            throw new ValidationException(
                'Rejection reason is too long',
                ['reason' => ['Rejection reason must not exceed 500 characters']]
            );
        }
    }

    /**
     * Handle rejection-specific errors.
     *
     * @throws ValidationException|ApiException
     */
    private function handleRejectionError(ApiException $e, string $documentId): void
    {
        $statusCode = $e->getCode();
        $message = $e->getMessage();

        $this->logError('Document rejection failed', [
            'document_id' => $documentId,
            'status_code' => $statusCode,
            'error' => $message,
        ]);

        switch ($statusCode) {
            case 400:
                if (str_contains($message, 'OperationPeriodOver') || str_contains(strtolower($message), 'rejection period')) {
                    throw new ValidationException(
                        'Rejection period has expired',
                        ['time' => ['Document can no longer be rejected as the time limit has passed']]
                    );
                }
                if (str_contains($message, 'IncorrectState') || str_contains(strtolower($message), 'valid state')) {
                    throw new ValidationException(
                        'Document cannot be rejected',
                        ['state' => ['Document must be in valid state to be rejected']]
                    );
                }
                if (str_contains($message, 'ActiveReferencingDocuments') || str_contains(strtolower($message), 'active references')) {
                    throw new ValidationException(
                        'Document has active references',
                        ['references' => ['Referenced documents must be rejected first']]
                    );
                }
                break;

            case 403:
                throw new ValidationException(
                    'Not authorized to reject document',
                    ['auth' => ['Only the document recipient can reject the document']]
                );
        }
    }
}
