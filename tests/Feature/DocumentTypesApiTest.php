<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Data\DocumentType;
use Nava\MyInvois\Data\DocumentTypeVersion;
use Nava\MyInvois\Data\WorkflowParameter;
use Nava\MyInvois\Enums\DocumentTypeEnum;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class DocumentTypesApiTest extends TestCase
{
    /** @test */
    public function it_can_retrieve_document_types(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock document types response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    [
                        'id' => 45,
                        'invoiceTypeCode' => DocumentTypeEnum::INVOICE->value,
                        'description' => 'Invoice',
                        'activeFrom' => '2015-02-13T13:15:00Z',
                        'activeTo' => null,
                        'documentTypeVersions' => [
                            [
                                'id' => 454,
                                'name' => '1.0',
                                'description' => 'Invoice version 1.1',
                                'activeFrom' => '2015-02-13T13:15:00Z',
                                'activeTo' => null,
                                'versionNumber' => 1.1,
                                'status' => 'published',
                            ],
                        ],
                        'workflowParameters' => [
                            [
                                'id' => 124,
                                'parameter' => 'rejectionDuration',
                                'value' => 72,
                                'activeFrom' => '2015-02-13T13:15:00Z',
                                'activeTo' => null,
                            ],
                        ],
                    ],
                ],
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Retrieved document types successfully'));

        $this->client->setLogger($logger);
        $types = $this->client->getDocumentTypes();

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/documenttypes', $request->getUri()->getPath());

        // Verify response mapping
        $this->assertCount(1, $types);
        $this->assertInstanceOf(DocumentType::class, $types[0]);
        $this->assertEquals(45, $types[0]->id);
        $this->assertEquals(DocumentTypeEnum::INVOICE->value, $types[0]->invoiceTypeCode);
        $this->assertTrue($types[0]->isActive());

        // Verify document type version
        $version = $types[0]->documentTypeVersions[0];
        $this->assertInstanceOf(DocumentTypeVersion::class, $version);
        $this->assertEquals(454, $version->id);
        $this->assertEquals(1.1, $version->versionNumber);
        $this->assertTrue($version->isActive());

        // Verify workflow parameter
        $param = $types[0]->workflowParameters[0];
        $this->assertInstanceOf(WorkflowParameter::class, $param);
        $this->assertEquals('rejectionDuration', $param->parameter);
        $this->assertEquals(72, $param->value);
    }

    /** @test */
    public function it_handles_invalid_response_format(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'invalid' => 'format',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid response format from document types endpoint');

        $this->client->getDocumentTypes();
    }

    /** @test */
    public function it_can_get_active_workflow_parameters(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    'id' => 45,
                    'invoiceTypeCode' => DocumentTypeEnum::INVOICE->value,
                    'description' => 'Invoice',
                    'activeFrom' => '2015-02-13T13:15:00Z',
                    'activeTo' => null,
                    'documentTypeVersions' => [],
                    'workflowParameters' => [
                        [
                            'id' => 124,
                            'parameter' => 'rejectionDuration',
                            'value' => 72,
                            'activeFrom' => '2015-02-13T13:15:00Z',
                            'activeTo' => null,
                        ],
                        [
                            'id' => 125,
                            'parameter' => 'submissionDuration',
                            'value' => 48,
                            'activeFrom' => '2015-02-13T13:15:00Z',
                            'activeTo' => '2020-01-01T00:00:00Z', // Inactive
                        ],
                    ],
                ],
            ]))
        );

        $parameters = $this->client->getActiveWorkflowParameters(45);

        $this->assertCount(1, $parameters);
        $this->assertEquals('rejectionDuration', $parameters[0]->parameter);
        $this->assertEquals(72, $parameters[0]->value);
    }

    /** @test */
    public function it_validates_workflow_parameter_names(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid workflow parameter name');

        $this->client->getWorkflowParameterValue(45, 'invalid_parameter');
    }

    /** @test */
    public function it_handles_not_found_errors_gracefully(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'not_found',
                'message' => 'Document type not found',
            ]))
        );

        $result = $this->client->getWorkflowParameterValue(999, 'rejectionDuration');
        $this->assertNull($result);
    }
}
