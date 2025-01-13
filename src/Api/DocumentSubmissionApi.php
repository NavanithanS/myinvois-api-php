<?php

namespace Nava\MyInvois\Api;

use Nava\MyInvois\Config;
use Nava\MyInvois\Enums\DocumentFormat;
use Nava\MyInvois\Enums\DocumentTypeEnum;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Traits\RateLimitingTrait;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

/**
 * API trait for document submission operations.
 */
trait DocumentSubmissionApi
{
    use RateLimitingTrait;

    protected $logger = null;

    private const MAX_SUBMISSION_SIZE = 5 * 1024 * 1024; // 5 MB

    private const MAX_DOCUMENT_SIZE = 300 * 1024; // 300 KB

    private const MAX_DOCUMENTS_PER_SUBMISSION = 100;

    private const DUPLICATE_DETECTION_WINDOW = 600; // 10 minutes

    private const SUBMISSION_ENDPOINT = '/api/v1.0/documentsubmissions';

    /**
     * Submit one or more documents to MyInvois.
     *
     * @param  array[]  $documents  Array of document data following MyInvois schema
     * @param  DocumentFormat  $format  Format of the documents (JSON or XML)
     * @return array{
     *     submissionUID: string,
     *     acceptedDocuments: array<array{uuid: string, invoiceCodeNumber: string}>,
     *     rejectedDocuments: array<array{invoiceCodeNumber: string, error: array}>
     * }
     *
     * @throws ValidationException If the submission is invalid
     * @throws ApiException If the API request fails
     */
    public function submitDocuments(array $documents, DocumentFormat $format = DocumentFormat::JSON): array
    {
        $maxRetries = 3;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                $this->checkRateLimit('submission');
                $this->validateSubmission($documents);

                $this->logDebug('Starting document submission', [
                    'attempt' => $attempt + 1,
                    'count' => count($documents),
                    'format' => $format->value,
                ]);

                $preparedDocuments = array_map(
                    function (array $doc) use ($format) {
                        return $this->prepareDocument($doc, $format);
                    },
                    $documents
                );                

                $response = $this->apiClient->request('POST', self::SUBMISSION_ENDPOINT, [
                    'headers' => ['Content-Type' => $this->getContentType($format)],
                    'json' => ['documents' => $preparedDocuments],
                    'timeout' => 30,
                ]);

                $this->logSubmissionResults($response);

                return $response;

            } catch (ApiException $e) {
                $lastException = $e;
                if (!$this->isRetryableError($e)) {
                    throw $e;
                }
                $attempt++;
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt)); // Exponential backoff
                }
            }
        }

        throw $lastException ?? new ApiException('Maximum retry attempts reached');
    }

    /**
     * Submit a single document.
     *
     * @param  array  $document  Document data following MyInvois schema
     * @param  DocumentFormat  $format  Format of the document
     * @return array Submission response
     *
     * @throws ValidationException|ApiException
     */
    public function submitDocument(array $document, ?string $version = null): array
    {
        $this->checkRateLimit('document_submission',
            $this->createRateLimitConfig('submitDocument', 50, 3600)
        );

        // Use provided version or current version
        $version = $version ?? Config::INVOICE_CURRENT_VERSION;

        // Validate version is supported
        if (!Config::isVersionSupported('invoice', $version)) {
            throw new ValidationException('Unsupported document version');
        }

        // Set version in document
        $document['invoiceTypeCode'] = [
            'value' => '01', // Invoice type code
            'listVersionID' => $version,
        ];

        // The correct endpoint is /api/v1.0/documentsubmissions (not /documents)
        return $this->apiClient->request('POST', '/api/v1.0/documentsubmissions', [
            'json' => $document,
        ]);
    }

    /**
     * Submit a batch of documents with the same document type.
     *
     * @param  array[]  $documents  Array of document data
     * @param  DocumentTypeEnum  $documentType  Type of documents being submitted
     * @param  DocumentFormat  $format  Format of the documents
     * @return array Submission response
     *
     * @throws ValidationException|ApiException
     */
    public function submitBatch(
        array $documents,
        DocumentTypeEnum $documentType,
        DocumentFormat $format = DocumentFormat::JSON
    ): array {
        // Validate document type consistency
        foreach ($documents as $document) {
            if (!isset($document['documentType']) || $document['documentType'] !== $documentType->value) {
                throw new ValidationException(
                    'All documents in batch must be of the same type',
                    ['documentType' => ['Inconsistent document types in batch']]
                );
            }
        }

        return $this->submitDocuments($documents, $format);
    }

    /**
     * Validate the document submission.
     *
     * @throws ValidationException If the submission is invalid
     */
    private function validateSubmission(array $documents): void
    {
        // Validate document count
        Assert::notEmpty($documents, 'At least one document is required');
        Assert::maxCount(
            $documents,
            self::MAX_DOCUMENTS_PER_SUBMISSION,
            sprintf('Maximum of %d documents per submission allowed', self::MAX_DOCUMENTS_PER_SUBMISSION)
        );

        // Validate total size
        $totalSize = $this->calculateSubmissionSize($documents);
        if ($totalSize > self::MAX_SUBMISSION_SIZE) {
            throw new ValidationException(
                'Maximum submission size exceeded',
                ['size' => ['Total submission size must not exceed 5MB']]
            );
        }

        // Validate individual documents
        foreach ($documents as $index => $document) {
            $this->validateDocument($document, $index);
        }

        // Validate unique code numbers
        $this->validateUniqueCodeNumbers($documents);
    }

    /**
     * Validate an individual document.
     *
     * @throws ValidationException If the document is invalid
     */
    private function validateDocument(array $document, int $index): void
    {
        $requiredFields = ['document', 'documentHash', 'codeNumber'];
        foreach ($requiredFields as $field) {
            Assert::keyExists(
                $document,
                $field,
                sprintf('Document at index %d is missing required field: %s', $index, $field)
            );
        }

        // Validate document size
        $size = strlen($document['document']);
        if ($size > self::MAX_DOCUMENT_SIZE) {
            throw new ValidationException(
                sprintf('Document %s exceeds maximum size', $document['codeNumber']),
                ['size' => ['Individual document size must not exceed 300KB']]
            );
        }

        // Validate code number format
        if (!preg_match('/^[A-Za-z0-9-]+$/', $document['codeNumber'])) {
            throw new ValidationException(
                sprintf('Invalid code number format for document %s', $document['codeNumber']),
                ['codeNumber' => ['Code number can only contain letters, numbers, and hyphens']]
            );
        }

        // Validate hash format (SHA-256)
        if (!preg_match('/^[A-Fa-f0-9]{64}$/', $document['documentHash'])) {
            throw new ValidationException(
                sprintf('Invalid hash format for document %s', $document['codeNumber']),
                ['documentHash' => ['Document hash must be a valid SHA-256 hash']]
            );
        }

        // Validate document content is not empty
        if (empty($document['document'])) {
            throw new ValidationException(
                sprintf('Empty document content for document %s', $document['codeNumber']),
                ['document' => ['Document content cannot be empty']]
            );
        }
    }

    /**
     * Validate that all code numbers are unique within the submission.
     *
     * @throws ValidationException If duplicate code numbers are found
     */
    private function validateUniqueCodeNumbers(array $documents): void
    {
        $codeNumbers = array_column($documents, 'codeNumber');
        $uniqueCodeNumbers = array_unique($codeNumbers);

        if (count($codeNumbers) !== count($uniqueCodeNumbers)) {
            $duplicates = array_diff_assoc($codeNumbers, $uniqueCodeNumbers);
            throw new ValidationException(
                'Duplicate code numbers found',
                ['codeNumbers' => ['Each document must have a unique code number within the submission']]
            );
        }
    }

    /**
     * Prepare a document for submission.
     */
    private function prepareDocument(array $document, DocumentFormat $format): array
    {
        // Minify document content if JSON/XML
        $content = $document['document'];
        if (DocumentFormat::JSON === $format) {
            $content = $this->minifyJson($content);
        } elseif (DocumentFormat::XML === $format) {
            $content = $this->minifyXml($content);
        }

        return [
            'format' => $format->value,
            'document' => base64_encode($content),
            'documentHash' => $document['documentHash'],
            'codeNumber' => $document['codeNumber'],
        ];
    }

    /**
     * Minify JSON content.
     */
    private function minifyJson(string $json): string
    {
        $data = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(
                'Invalid JSON content',
                ['document' => ['Document content must be valid JSON']]
            );
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Minify XML content.
     */
    private function minifyXml(string $xml): string
    {
        $doc = new \DOMDocument;
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        if (!@$doc->loadXML($xml)) {
            throw new ValidationException(
                'Invalid XML content',
                ['document' => ['Document content must be valid XML']]
            );
        }

        return $doc->saveXML();
    }

    /**
     * Calculate total submission size.
     */
    private function calculateSubmissionSize(array $documents): int
    {
        return array_reduce(
            $documents,
            function (int $total, array $doc) {
                return $total + strlen($doc['document']);
            },
            0
        );        
    }

    /**
     * Get content type header value based on format.
     */
    private function getContentType(DocumentFormat $format): string
    {
        switch ($format) {
            case DocumentFormat::JSON:
                return 'application/json';
            case DocumentFormat::XML:
                return 'application/xml';
        }
    }

    /**
     * Handle submission-specific errors.
     *
     * @throws ValidationException
     */
    private function handleSubmissionError(ApiException $e, array $documents): void
    {
        $statusCode = $e->getCode();
        $message = $e->getMessage();

        $this->logError('Document submission failed', [
            'status_code' => $statusCode,
            'error' => $message,
            'document_count' => count($documents),
        ]);

        switch ($statusCode) {
            case 400:
                if (str_contains($message, 'BadStructure')) {
                    throw new ValidationException(
                        'Invalid submission structure',
                        ['structure' => ['Submission must contain valid document data']]
                    );
                }
                if (str_contains($message, 'MaximumSizeExceeded')) {
                    throw new ValidationException(
                        'Maximum submission size exceeded',
                        ['size' => ['Total submission size must not exceed 5MB']]
                    );
                }
                break;

            case 403:
                if (str_contains($message, 'IncorrectSubmitter')) {
                    throw new ValidationException(
                        'Invalid submitter',
                        ['submitter' => ['Not authorized to submit documents for this taxpayer']]
                    );
                }
                break;

            case 422:
                if (str_contains($message, 'DuplicateSubmission')) {
                    throw new ValidationException(
                        'Duplicate submission detected',
                        ['duplicate' => ['Please wait 10 minutes before resubmitting the same documents']]
                    );
                }
                break;
        }
    }

    /**
     * Log submission results.
     */
    private function logSubmissionResults(array $response): void
    {
        $acceptedCount = count($response['acceptedDocuments'] ?? []);
        $rejectedCount = count($response['rejectedDocuments'] ?? []);

        $this->logDebug('Document submission completed', [
            'submission_id' => $response['submissionUID'],
            'accepted_count' => $acceptedCount,
            'rejected_count' => $rejectedCount,
            'accepted_documents' => array_column($response['acceptedDocuments'] ?? [], 'invoiceCodeNumber'),
            'rejected_documents' => array_column($response['rejectedDocuments'] ?? [], 'invoiceCodeNumber'),
        ]);

        if ($rejectedCount > 0) {
            $this->logError('Some documents were rejected', [
                'submission_id' => $response['submissionUID'],
                'rejected_count' => $rejectedCount,
                'rejected_details' => $response['rejectedDocuments'],
            ]);
        }
    }

    public function submitCreditNote(array $document, ?string $version = null): array
    {
        // Use provided version or current version
        $version = $version ?? Config::CREDIT_NOTE_CURRENT_VERSION;

        // Validate version is supported
        if (!Config::isVersionSupported('credit_note', $version)) {
            throw new ValidationException('Unsupported credit note version');
        }

        // Add version to document
        $document['invoiceTypeCode'] = [
            'value' => Config::CREDIT_NOTE_TYPE_CODE,
            'listVersionID' => $version,
        ];

        // Submit using base submission logic
        return $this->submitDocument($document);
    }

    public function submitDebitNote(array $document, ?string $version = null): array
    {
        // Use provided version or current version
        $version = $version ?? Config::DEBIT_NOTE_CURRENT_VERSION;

        // Validate version is supported
        if (!Config::isVersionSupported('debit_note', $version)) {
            throw new ValidationException('Unsupported debit note version');
        }

        // Add version to document
        $document['invoiceTypeCode'] = [
            'value' => '03', // Debit note type code
            'listVersionID' => $version,
        ];

        // Submit using base submission logic
        return $this->submitDocument($document);
    }
}
