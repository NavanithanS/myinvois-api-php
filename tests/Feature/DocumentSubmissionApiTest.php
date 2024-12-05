<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Enums\DocumentFormat;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class DocumentSubmissionApiTest extends TestCase
{
    private array $validDocument;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up a valid test document
        $this->validDocument = [
            'document' => json_encode([
                'invoiceNumber' => 'INV-001',
                'issueDate' => '2024-01-01',
                'totalAmount' => 1000.00,
            ]),
            'documentHash' => hash('sha256', 'test_document_content'),
            'codeNumber' => 'INV-001',
        ];
    }

    /** @test */
    public function it_can_submit_single_document(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock successful submission response
        $this->mockHandler->append(
            new Response(202, [], json_encode([
                'submissionUID' => 'SUB123456789',
                'acceptedDocuments' => [
                    [
                        'uuid' => 'DOC123456789',
                        'invoiceCodeNumber' => 'INV-001',
                    ],
                ],
                'rejectedDocuments' => [],
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                [$this->stringContains('Submitting documents')],
                [$this->stringContains('Documents submitted successfully')]
            );

        $this->client->setLogger($logger);
        $response = $this->client->submitDocuments([$this->validDocument]);

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('application/json', $request->getHeader('Content-Type')[0]);
        $this->assertStringContainsString('/documentsubmissions', $request->getUri()->getPath());

        // Verify response
        $this->assertEquals('SUB123456789', $response['submissionUID']);
        $this->assertCount(1, $response['acceptedDocuments']);
        $this->assertEmpty($response['rejectedDocuments']);
    }

    /** @test */
    public function it_can_submit_multiple_documents(): void
    {
        $this->mockSuccessfulAuthentication();

        $documents = [
            $this->validDocument,
            array_merge($this->validDocument, ['codeNumber' => 'INV-002']),
            array_merge($this->validDocument, ['codeNumber' => 'INV-003']),
        ];

        $this->mockHandler->append(
            new Response(202, [], json_encode([
                'submissionUID' => 'SUB123456789',
                'acceptedDocuments' => [
                    ['uuid' => 'DOC1', 'invoiceCodeNumber' => 'INV-001'],
                    ['uuid' => 'DOC2', 'invoiceCodeNumber' => 'INV-002'],
                    ['uuid' => 'DOC3', 'invoiceCodeNumber' => 'INV-003'],
                ],
                'rejectedDocuments' => [],
            ]))
        );

        $response = $this->client->submitDocuments($documents);

        $this->assertCount(3, $response['acceptedDocuments']);
        $this->assertEmpty($response['rejectedDocuments']);
    }

    /** @test */
    public function it_validates_maximum_document_count(): void
    {
        // Create 101 documents (exceeding limit)
        $documents = array_fill(0, 101, $this->validDocument);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Maximum of 100 documents per submission allowed');

        $this->client->submitDocuments($documents);
    }

    /** @test */
    public function it_validates_document_size(): void
    {
        // Create document exceeding 300KB
        $largeDocument = $this->validDocument;
        $largeDocument['document'] = str_repeat('a', 301 * 1024);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Maximum document size is 300KB');

        $this->client->submitDocuments([$largeDocument]);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $invalidDocuments = [
            // Missing document content
            array_diff_key($this->validDocument, ['document' => '']),
            // Missing hash
            array_diff_key($this->validDocument, ['documentHash' => '']),
            // Missing code number
            array_diff_key($this->validDocument, ['codeNumber' => '']),
        ];

        foreach ($invalidDocuments as $document) {
            try {
                $this->client->submitDocuments([$document]);
                $this->fail('Expected ValidationException was not thrown');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('missing required field', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_code_number_format(): void
    {
        $invalidCodeNumbers = [
            'INV@001', // Invalid character
            'INV#001', // Invalid character
            'INV 001', // Space not allowed
            'INV_001', // Underscore not allowed
        ];

        foreach ($invalidCodeNumbers as $codeNumber) {
            $document = array_merge($this->validDocument, ['codeNumber' => $codeNumber]);

            try {
                $this->client->submitDocuments([$document]);
                $this->fail('Expected ValidationException was not thrown');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid code number format', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_hash_format(): void
    {
        $document = array_merge($this->validDocument, [
            'documentHash' => 'invalid-hash',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid hash format');

        $this->client->submitDocuments([$document]);
    }

    /** @test */
    public function it_handles_duplicate_submission_error(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(422, [], json_encode([
                'error' => 'DuplicateSubmission',
                'message' => 'Duplicate submission detected',
            ]))
        );

        try {
            $this->client->submitDocuments([$this->validDocument]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Duplicate submission detected', $e->getMessage());
            $this->assertArrayHasKey('duplicate', $e->getErrors());
        }
    }

    /** @test */
    public function it_handles_incorrect_submitter_error(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(403, [], json_encode([
                'error' => 'IncorrectSubmitter',
                'message' => 'Not authorized for this taxpayer',
            ]))
        );

        try {
            $this->client->submitDocuments([$this->validDocument]);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Invalid submitter', $e->getMessage());
            $this->assertArrayHasKey('submitter', $e->getErrors());
        }
    }

    /** @test */
    public function it_supports_xml_format(): void
    {
        $this->mockSuccessfulAuthentication();

        $xmlDocument = array_merge($this->validDocument, [
            'document' => '<?xml version="1.0"?><invoice><number>INV-001</number></invoice>',
        ]);

        $this->mockHandler->append(
            new Response(202, [], json_encode([
                'submissionUID' => 'SUB123456789',
                'acceptedDocuments' => [
                    ['uuid' => 'DOC1', 'invoiceCodeNumber' => 'INV-001'],
                ],
                'rejectedDocuments' => [],
            ]))
        );

        $response = $this->client->submitDocuments([$xmlDocument], DocumentFormat::XML);

        $request = $this->getLastRequest();
        $this->assertEquals('application/xml', $request->getHeader('Content-Type')[0]);
        $this->assertCount(1, $response['acceptedDocuments']);
    }

    /** @test */
    public function it_handles_partial_acceptance(): void
    {
        $this->mockSuccessfulAuthentication();

        $documents = [
            $this->validDocument,
            array_merge($this->validDocument, ['codeNumber' => 'INV-002']),
        ];

        $this->mockHandler->append(
            new Response(202, [], json_encode([
                'submissionUID' => 'SUB123456789',
                'acceptedDocuments' => [
                    ['uuid' => 'DOC1', 'invoiceCodeNumber' => 'INV-001'],
                ],
                'rejectedDocuments' => [
                    [
                        'invoiceCodeNumber' => 'INV-002',
                        'error' => ['message' => 'Invalid document structure'],
                    ],
                ],
            ]))
        );

        $response = $this->client->submitDocuments($documents);

        $this->assertCount(1, $response['acceptedDocuments']);
        $this->assertCount(1, $response['rejectedDocuments']);
        $this->assertEquals('INV-002', $response['rejectedDocuments'][0]['invoiceCodeNumber']);
    }

    /** @test */
    public function it_handles_invalid_response_format(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(202, [], json_encode([
                'invalid' => 'format',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid response format from submission endpoint');

        $this->client->submitDocuments([$this->validDocument]);
    }
}
