<?php

namespace Nava\MyInvois\Api;

use DateTimeImmutable;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Traits\UuidValidationTrait;
use Webmozart\Assert\Assert;

/**
 * API trait for document details operations.
 */
trait DocumentDetailsApi
{
    use UuidValidationTrait;

    /**
     * Get detailed information about a document by its UUID.
     *
     * @param  string  $uuid  Document UUID
     * @return array{
     *     uuid: string,
     *     submissionUid: string,
     *     longId: ?string,
     *     internalId: string,
     *     typeName: string,
     *     typeVersionName: string,
     *     issuerTin: string,
     *     issuerName: string,
     *     receiverId: ?string,
     *     receiverName: ?string,
     *     dateTimeIssued: DateTimeImmutable,
     *     dateTimeReceived: DateTimeImmutable,
     *     dateTimeValidated: ?DateTimeImmutable,
     *     totalExcludingTax: float,
     *     totalDiscount: float,
     *     totalNetAmount: float,
     *     totalPayableAmount: float,
     *     status: string,
     *     cancelDateTime: ?DateTimeImmutable,
     *     rejectRequestDateTime: ?DateTimeImmutable,
     *     documentStatusReason: ?string,
     *     createdByUserId: string,
     *     validationResults: array
     * }
     *
     * @throws ValidationException If the UUID format is invalid
     * @throws ApiException If the API request fails
     */
    public function getDocumentDetails(string $uuid): array
    {
        try {
            $this->validateUuid($uuid);

            $response = $this->apiClient->request('GET', "/api/v1.0/documents/{$uuid}/details");

            if (! isset($response['uuid'])) {
                throw new ApiException('Invalid response format from document details endpoint');
            }

            $this->logDebug('Retrieved document details successfully', [
                'uuid' => $uuid,
                'status' => $response['status'] ?? 'unknown',
            ]);

            return $this->mapDocumentDetails($response);

        } catch (ApiException $e) {
            $this->logError('Failed to retrieve document details', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            if ($e->getCode() === 404) {
                // Handle document not found or unauthorized access
                throw new ApiException(
                    'Document not found or access not authorized. Note: Receivers can only access Valid or Cancelled documents.',
                    404,
                    $e
                );
            }

            throw $e;
        }
    }

    /**
     * Get document status validation results.
     *
     * @param  string  $uuid  Document UUID
     * @return array{
     *     status: string,
     *     validationSteps: array<array{
     *         name: string,
     *         status: string,
     *         error?: array
     *     }>
     * }
     *
     * @throws ValidationException If the UUID format is invalid
     * @throws ApiException If the API request fails
     */
    public function getDocumentValidationResults(string $uuid): array
    {
        $details = $this->getDocumentDetails($uuid);

        return $details['validationResults'] ?? [
            'status' => $details['status'],
            'validationSteps' => [],
        ];
    }

    /**
     * Generate public URL for document viewing.
     *
     * @param  string  $uuid  Document UUID
     * @param  string  $longId  Document long ID
     * @return string Public URL
     *
     * @throws ValidationException If the UUID or long ID format is invalid
     */
    public function generateDocumentPublicUrl(string $uuid, string $longId): string
    {
        $this->validateUuid($uuid);
        Assert::notEmpty($longId, 'Long ID cannot be empty');
        Assert::regex($longId, '/^[A-Z0-9\s]{32,}$/', 'Invalid long ID format');

        $baseUrl = rtrim($this->config['base_url'] ?? '', '/');

        return "{$baseUrl}/{$uuid}/share/{$longId}";
    }

    /**
     * Map API response to structured document details.
     *
     * @throws ApiException If required fields are missing
     */
    private function mapDocumentDetails(array $response): array
    {
        try {
            // Map required fields
            $details = [
                'uuid' => $response['uuid'],
                'submissionUid' => $response['submissionUid'],
                'internalId' => $response['internalId'],
                'typeName' => $response['typeName'],
                'typeVersionName' => $response['typeVersionName'],
                'issuerTin' => $response['issuerTin'],
                'issuerName' => $response['issuerName'],
                'dateTimeIssued' => new DateTimeImmutable($response['dateTimeIssued']),
                'dateTimeReceived' => new DateTimeImmutable($response['dateTimeReceived']),
                'totalExcludingTax' => (float) $response['totalExcludingTax'],
                'totalDiscount' => (float) $response['totalDiscount'],
                'totalNetAmount' => (float) $response['totalNetAmount'],
                'totalPayableAmount' => (float) $response['totalPayableAmount'],
                'status' => $response['status'],
                'createdByUserId' => $response['createdByUserId'],
            ];

            // Map optional fields
            $optionalDateFields = [
                'dateTimeValidated',
                'cancelDateTime',
                'rejectRequestDateTime',
            ];

            foreach ($optionalDateFields as $field) {
                $details[$field] = isset($response[$field])
                ? new DateTimeImmutable($response[$field])
                : null;
            }

            $optionalFields = [
                'longId',
                'receiverId',
                'receiverName',
                'documentStatusReason',
            ];

            foreach ($optionalFields as $field) {
                $details[$field] = $response[$field] ?? null;
            }

            // Map validation results if present
            if (isset($response['validationResults'])) {
                $details['validationResults'] = $this->mapValidationResults($response['validationResults']);
            }

            return $details;

        } catch (\Throwable $e) {
            throw new ApiException(
                'Failed to map document details: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Map validation results structure.
     */
    private function mapValidationResults(array $results): array
    {
        Assert::keyExists($results, 'status', 'Validation status is required');
        Assert::keyExists($results, 'validationSteps', 'Validation steps are required');
        Assert::isArray($results['validationSteps'], 'Validation steps must be an array');

        $validStatuses = ['Submitted', 'Valid', 'Invalid'];
        Assert::inArray($results['status'], $validStatuses, 'Invalid validation status');

        foreach ($results['validationSteps'] as $step) {
            Assert::keyExists($step, 'name', 'Validation step name is required');
            Assert::keyExists($step, 'status', 'Validation step status is required');
            Assert::inArray($step['status'], $validStatuses, 'Invalid validation step status');
        }

        return $results;
    }

    /**
     * Convert decimal values to floats.
     */
    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        throw new \InvalidArgumentException('Value cannot be converted to float');
    }
}
