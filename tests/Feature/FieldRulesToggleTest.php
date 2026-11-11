<?php

namespace Nava\MyInvois\Tests\Feature;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\Support\CreatesDocuments;
use Nava\MyInvois\Tests\TestCase;

/**
 * The local LHDN field rules can be switched off (setter or
 * myinvois.validation.field_rules); SVDP rules always apply.
 */
class FieldRulesToggleTest extends TestCase
{
    use CreatesDocuments;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-05T10:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Official 1.1 sample with a unit code that is not in our code list (as if
     * LHDN added it after this library was released) and a 51-char invoice ID.
     *
     * @return array{document: string, documentHash: string, codeNumber: string}
     */
    private function documentLocalRulesReject(string $version = '1.1'): array
    {
        $doc = json_decode((string) file_get_contents(__DIR__ . "/../fixtures/sdk/{$version}-Invoice-Sample.json"), true);
        $doc['Invoice'][0]['ID'][0]['_'] = str_repeat('A', 51);
        $doc['Invoice'][0]['InvoiceLine'][0]['InvoicedQuantity'][0]['unitCode'] = 'ZZZ9';
        $json = (string) json_encode($doc);

        return ['document' => $json, 'documentHash' => hash('sha256', $json), 'codeNumber' => 'INV-1'];
    }

    private function mockAccepted(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(202, [], (string) json_encode([
            'submissionUID' => 'SUB1', 'acceptedDocuments' => [], 'rejectedDocuments' => [],
        ])));
    }

    public function test_enabled_by_default(): void
    {
        try {
            $this->client->submitDocuments([$this->documentLocalRulesReject()]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('documents[0].Invoice.ID', $e->getErrors());
            $this->assertArrayHasKey('documents[0].Invoice.InvoiceLine.InvoicedQuantity@unitCode', $e->getErrors());
        }
        $this->assertCount(0, $this->container);
    }

    public function test_setter_disables_field_rules_for_submit_documents(): void
    {
        $this->mockAccepted();

        $response = $this->client->setFieldRulesValidation(false)->submitDocuments([$this->documentLocalRulesReject()]);

        $this->assertSame('SUB1', $response['submissionUID']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function disabledConfigProvider(): array
    {
        return [
            'bool false' => [false],
            'env string "false"' => ['false'],
            'env string "0"' => ['0'],
        ];
    }

    /**
     * @dataProvider disabledConfigProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('disabledConfigProvider')]
    public function test_config_disables_field_rules(mixed $value): void
    {
        config()->set('myinvois.validation.field_rules', $value);
        $this->mockAccepted();

        $this->assertSame('SUB1', $this->client->submitDocuments([$this->documentLocalRulesReject()])['submissionUID']);
    }

    public function test_setter_overrides_config(): void
    {
        config()->set('myinvois.validation.field_rules', false);

        $this->expectException(ValidationException::class);
        $this->client->setFieldRulesValidation(true)->submitDocuments([$this->documentLocalRulesReject()]);
    }

    public function test_svdp_rules_still_apply_when_field_rules_are_off(): void
    {
        $unsigned13 = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/sdk/1.2-Invoice-Sample.json'), true);
        $unsigned13['Invoice'][0]['InvoiceTypeCode'][0]['listVersionID'] = '1.3';
        $json = (string) json_encode($unsigned13);

        try {
            $this->client->setFieldRulesValidation(false)->submitDocuments([
                ['document' => $json, 'documentHash' => hash('sha256', $json), 'codeNumber' => 'SVDP-1'],
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['documents[0].Invoice.InvoiceTypeCode@listVersionID'], array_keys($e->getErrors()));
        }
        $this->assertCount(0, $this->container);
    }

    public function test_create_document_respects_the_switch(): void
    {
        $this->useGeneratedSigningCertificate();
        $request = $this->documentRequest(['invoice_no' => str_repeat('A', 51)]);

        try {
            $this->client->createDocument($request);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('Invoice.ID', $e->getErrors());
        }

        $this->mockDocumentSubmission();
        $result = $this->client->setFieldRulesValidation(false)->createDocument($request);

        $this->assertSame('SUB123', $result['submissionUID']);
        $this->assertSame(str_repeat('A', 51), $this->lastSubmittedDocument()['Invoice'][0]['ID'][0]['_']);
    }
}
