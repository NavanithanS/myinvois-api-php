<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Data\DocumentTypeVersion;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;

class DocumentTypeVersionDetailApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSuccessfulAuthentication();
    }

    /** @test */
    public function it_successfully_retrieves_document_type_version(): void
    {
        // Mock successful response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 41235,
                    'invoiceTypeCode' => 4,
                    'name' => '1.0',
                    'description' => 'Invoice version 1.0',
                    'versionNumber' => 1.0,
                    'status' => 'published',
                    'activeFrom' => '2015-02-13T13:15:00Z',
                    'activeTo' => null,
                    'jsonSchema' => base64_encode('{"type": "object"}'),
                    'xmlSchema' => base64_encode('<?xml version="1.0"?>'),
                ],
            ]))
        );

        $version = $this->client->getDocumentTypeVersion(45, 41235);

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/documenttypes/45/versions/41235', $request->getUri()->getPath());

        // Verify response mapping
        $this->assertInstanceOf(DocumentTypeVersion::class, $version);
        $this->assertEquals(41235, $version->id);
        $this->assertEquals('1.0', $version->name);
        $this->assertEquals(1.0, $version->versionNumber);
        $this->assertEquals('published', $version->status);
        $this->assertTrue($version->isActive());
    }

    /** @test */
    public function it_validates_document_type_id(): void
    {
        $invalidIds = [0, -1, 'invalid'];

        foreach ($invalidIds as $id) {
            try {
                $this->client->getDocumentTypeVersion($id, 41235);
                $this->fail('Expected ValidationException for invalid document type ID');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Document type ID must be greater than 0', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_version_id(): void
    {
        $invalidIds = [0, -1, 'invalid'];

        foreach ($invalidIds as $id) {
            try {
                $this->client->getDocumentTypeVersion(45, $id);
                $this->fail('Expected ValidationException for invalid version ID');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Version ID must be greater than 0', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_handles_not_found_error(): void
    {
        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'not_found',
                'message' => 'Document type version not found',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Document type version not found');
        $this->expectExceptionCode(404);

        $this->client->getDocumentTypeVersion(45, 99999);
    }

    /** @test */
    public function it_handles_invalid_response_format(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'invalid' => 'format',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid response format from document type version endpoint');

        $this->client->getDocumentTypeVersion(45, 41235);
    }

    /** @test */
    public function it_can_decode_schema_content(): void
    {
        $jsonSchema = '{"type":"object","properties":{}}';
        $xmlSchema = '<?xml version="1.0" encoding="UTF-8"?><xs:schema></xs:schema>';

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 41235,
                    'name' => '1.0',
                    'description' => 'Test version',
                    'versionNumber' => 1.0,
                    'status' => 'published',
                    'activeFrom' => '2024-01-01T00:00:00Z',
                    'activeTo' => null,
                    'jsonSchema' => base64_encode($jsonSchema),
                    'xmlSchema' => base64_encode($xmlSchema),
                ],
            ]))
        );

        $version = $this->client->getDocumentTypeVersion(45, 41235);

        // Test schema retrieval
        $decodedJson = $this->client->getDocumentTypeVersionSchema($version, 'json');
        $decodedXml = $this->client->getDocumentTypeVersionSchema($version, 'xml');

        $this->assertEquals($jsonSchema, $decodedJson);
        $this->assertEquals($xmlSchema, $decodedXml);
    }

    /** @test */
    public function it_validates_schema_format(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 41235,
                    'name' => '1.0',
                    'description' => 'Test version',
                    'versionNumber' => 1.0,
                    'status' => 'published',
                    'activeFrom' => '2024-01-01T00:00:00Z',
                ],
            ]))
        );

        $version = $this->client->getDocumentTypeVersion(45, 41235);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Format must be either "json" or "xml"');

        $this->client->getDocumentTypeVersionSchema($version, 'invalid');
    }

    /** @test */
    public function it_correctly_determines_version_status(): void
    {
        // Test active version
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 41235,
                    'name' => '1.0',
                    'versionNumber' => 1.0,
                    'status' => 'published',
                    'activeFrom' => date('c', strtotime('-1 day')),
                    'activeTo' => null,
                ],
            ]))
        );

        $activeVersion = $this->client->getDocumentTypeVersion(45, 41235);
        $this->assertTrue($activeVersion->isActive());

        // Test inactive (deactivated) version
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 41236,
                    'name' => '1.1',
                    'versionNumber' => 1.1,
                    'status' => 'deactivated',
                    'activeFrom' => date('c', strtotime('-2 days')),
                    'activeTo' => date('c', strtotime('-1 day')),
                ],
            ]))
        );

        $inactiveVersion = $this->client->getDocumentTypeVersion(45, 41236);
        $this->assertFalse($inactiveVersion->isActive());
    }
}
