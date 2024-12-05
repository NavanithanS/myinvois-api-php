<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Data\DocumentTypeVersion;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class DocumentTypeVersionsApiTest extends TestCase
{
    /** @test */
    public function it_can_get_document_type_version(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock version response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 454,
                    'name' => '1.0',
                    'description' => 'Test version',
                    'activeFrom' => '2024-01-01T00:00:00Z',
                    'activeTo' => null,
                    'versionNumber' => 1.0,
                    'status' => 'published',
                    'jsonSchema' => base64_encode('{"type": "object"}'),
                    'xmlSchema' => base64_encode('<?xml version="1.0"?>'),
                ],
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Retrieved document type version successfully'));

        $this->client->setLogger($logger);
        $version = $this->client->getDocumentTypeVersion(45, 454);

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/documenttypes/45/versions/454', $request->getUri()->getPath());

        // Verify response mapping
        $this->assertInstanceOf(DocumentTypeVersion::class, $version);
        $this->assertEquals(454, $version->id);
        $this->assertEquals(1.0, $version->versionNumber);
        $this->assertEquals('published', $version->status);
        $this->assertTrue($version->isActive());
    }

    /** @test */
    public function it_validates_input_parameters(): void
    {
        $invalidInputs = [
            [0, 454], // Invalid document type ID
            [45, 0], // Invalid version ID
            [-1, 454], // Negative document type ID
            [45, -1], // Negative version ID
        ];

        foreach ($invalidInputs as [$docTypeId, $versionId]) {
            try {
                $this->client->getDocumentTypeVersion($docTypeId, $versionId);
                $this->fail('Expected ValidationException was not thrown');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('must be greater than 0', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_can_find_version_by_number(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock document type response with versions
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 45,
                    'invoiceTypeCode' => 4,
                    'description' => 'Test type',
                    'activeFrom' => '2024-01-01T00:00:00Z',
                    'activeTo' => null,
                    'documentTypeVersions' => [
                        [
                            'id' => 454,
                            'name' => '1.0',
                            'description' => 'Version 1.0',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => null,
                            'versionNumber' => 1.0,
                            'status' => 'published',
                        ],
                        [
                            'id' => 455,
                            'name' => '2.0',
                            'description' => 'Version 2.0',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => null,
                            'versionNumber' => 2.0,
                            'status' => 'published',
                        ],
                    ],
                ],
            ]))
        );

        $version = $this->client->findDocumentTypeVersion(45, 2.0);

        $this->assertInstanceOf(DocumentTypeVersion::class, $version);
        $this->assertEquals(2.0, $version->versionNumber);
        $this->assertEquals('Version 2.0', $version->description);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_version(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock document type response with versions
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 45,
                    'documentTypeVersions' => [
                        [
                            'id' => 454,
                            'name' => '1.0',
                            'description' => 'Version 1.0',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => null,
                            'versionNumber' => 1.0,
                            'status' => 'published',
                        ],
                    ],
                ],
            ]))
        );

        $version = $this->client->findDocumentTypeVersion(45, 2.0);
        $this->assertNull($version);
    }

    /** @test */
    public function it_can_get_active_versions(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock document type response with mix of active and inactive versions
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 45,
                    'documentTypeVersions' => [
                        [
                            'id' => 454,
                            'name' => '1.0',
                            'description' => 'Active version',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => null,
                            'versionNumber' => 1.0,
                            'status' => 'published',
                        ],
                        [
                            'id' => 455,
                            'name' => '2.0',
                            'description' => 'Inactive version',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => '2024-02-01T00:00:00Z',
                            'versionNumber' => 2.0,
                            'status' => 'published',
                        ],
                        [
                            'id' => 456,
                            'name' => '3.0',
                            'description' => 'Draft version',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => null,
                            'versionNumber' => 3.0,
                            'status' => 'draft',
                        ],
                    ],
                ],
            ]))
        );

        $activeVersions = $this->client->getActiveDocumentTypeVersions(45);

        $this->assertCount(1, $activeVersions);
        $this->assertEquals(1.0, $activeVersions[0]->versionNumber);
        $this->assertEquals('published', $activeVersions[0]->status);
    }

    /** @test */
    public function it_can_get_latest_version(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock document type response with multiple active versions
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 45,
                    'documentTypeVersions' => [
                        [
                            'id' => 454,
                            'name' => '1.0',
                            'description' => 'Old version',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => null,
                            'versionNumber' => 1.0,
                            'status' => 'published',
                        ],
                        [
                            'id' => 455,
                            'name' => '2.0',
                            'description' => 'Latest version',
                            'activeFrom' => '2024-01-01T00:00:00Z',
                            'activeTo' => null,
                            'versionNumber' => 2.0,
                            'status' => 'published',
                        ],
                    ],
                ],
            ]))
        );

        $latestVersion = $this->client->getLatestDocumentTypeVersion(45);

        $this->assertInstanceOf(DocumentTypeVersion::class, $latestVersion);
        $this->assertEquals(2.0, $latestVersion->versionNumber);
        $this->assertEquals('Latest version', $latestVersion->description);
    }

    /** @test */
    public function it_can_get_schema_content(): void
    {
        $jsonSchema = '{"type":"object","properties":{}}';
        $xmlSchema = '<?xml version="1.0" encoding="UTF-8"?><xs:schema></xs:schema>';

        $version = new DocumentTypeVersion([
            'id' => 454,
            'name' => '1.0',
            'description' => 'Test version',
            'activeFrom' => new \DateTimeImmutable('2024-01-01'),
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'published',
            'jsonSchema' => base64_encode($jsonSchema),
            'xmlSchema' => base64_encode($xmlSchema),
        ]);

        // Test JSON schema retrieval
        $decodedJson = $this->client->getDocumentTypeVersionSchema($version, 'json');
        $this->assertEquals($jsonSchema, $decodedJson);

        // Test XML schema retrieval
        $decodedXml = $this->client->getDocumentTypeVersionSchema($version, 'xml');
        $this->assertEquals($xmlSchema, $decodedXml);
    }

    /** @test */
    public function it_validates_schema_format(): void
    {
        $version = new DocumentTypeVersion([
            'id' => 454,
            'name' => '1.0',
            'description' => 'Test version',
            'activeFrom' => new \DateTimeImmutable('2024-01-01'),
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'published',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Format must be either "json" or "xml"');

        $this->client->getDocumentTypeVersionSchema($version, 'invalid');
    }

    /** @test */
    public function it_handles_missing_schema(): void
    {
        $version = new DocumentTypeVersion([
            'id' => 454,
            'name' => '1.0',
            'description' => 'Test version',
            'activeFrom' => new \DateTimeImmutable('2024-01-01'),
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'published',
            // No schemas defined
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No json schema available');

        $this->client->getDocumentTypeVersionSchema($version, 'json');
    }

    /** @test */
    public function it_handles_invalid_base64_schema(): void
    {
        $version = new DocumentTypeVersion([
            'id' => 454,
            'name' => '1.0',
            'description' => 'Test version',
            'activeFrom' => new \DateTimeImmutable('2024-01-01'),
            'activeTo' => null,
            'versionNumber' => 1.0,
            'status' => 'published',
            'jsonSchema' => 'invalid-base64',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid base64 encoded json schema');

        $this->client->getDocumentTypeVersionSchema($version, 'json');
    }
}
