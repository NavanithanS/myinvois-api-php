<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Enums\DocumentFormat;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\Support\CreatesDocuments;
use Nava\MyInvois\Tests\TestCase;

/**
 * DocumentFieldRulesValidator wired into submitDocuments() and createDocument().
 */
class DocumentFieldRulesIntegrationTest extends TestCase
{
    use CreatesDocuments;

    private function sampleJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/sdk/1.1-Invoice-Sample.json');
    }

    /**
     * @return array{document: string, documentHash: string, codeNumber: string}
     */
    private function submission(string $json, string $codeNumber = 'INV-1'): array
    {
        return ['document' => $json, 'documentHash' => hash('sha256', $json), 'codeNumber' => $codeNumber];
    }

    private function mockSubmissionAccepted(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(202, [], (string) json_encode([
            'submissionUID' => 'SUB1',
            'acceptedDocuments' => [['uuid' => 'U1', 'invoiceCodeNumber' => 'INV-1']],
            'rejectedDocuments' => [],
        ])));
    }

    /** @return array<string, mixed> */
    private function lastSubmissionPayload(): array
    {
        return json_decode((string) $this->getLastRequest()->getBody(), true);
    }

    public function test_submit_documents_accepts_official_sample_and_sends_it_byte_for_byte_minified(): void
    {
        // A 25-digit amount: json_decode/json_encode would turn this into 1.2345678901234568e+22
        $json = str_replace('"PayableAmount": [', '"PayableAmount" : [', $this->sampleJson());
        $json = (string) preg_replace('/("PayableAmount"\s*:\s*\[\s*\{\s*"_"\s*:\s*)[0-9.]+/', '${1}12345678901234567890123.45', $json, 1, $count);
        $this->assertSame(1, $count);

        $this->mockSubmissionAccepted();
        $this->client->submitDocuments([$this->submission($json)]);

        $sent = base64_decode($this->lastSubmissionPayload()['documents'][0]['document']);
        $this->assertStringContainsString('"PayableAmount":[{"_":12345678901234567890123.45,', $sent);
        $this->assertDoesNotMatchRegularExpression('/[:\[,]\s*-?\d+(?:\.\d+)?[eE]/', $sent);
        $this->assertSame(json_decode($json, true), json_decode($sent, true), 'Minification must not change content');
        $this->assertStringNotContainsString("\n", $sent);
    }

    public function test_submit_documents_rejects_rule_violations_across_documents_before_any_http_call(): void
    {
        $bad1 = json_decode($this->sampleJson(), true);
        $bad1['Invoice'][0]['ID'][0]['_'] = str_repeat('A', 51);
        $bad2 = json_decode($this->sampleJson(), true);
        $bad2['Invoice'][0]['IssueDate'][0]['_'] = 'N/A';
        $exponent = str_replace('"PayableAmount":[{"_":1436.5,', '"PayableAmount":[{"_":1.4365e3,', (string) json_encode(json_decode($this->sampleJson(), true)));

        try {
            $this->client->submitDocuments([
                $this->submission((string) json_encode($bad1), 'INV-1'),
                $this->submission($this->sampleJson(), 'INV-2'),
                $this->submission((string) json_encode($bad2), 'INV-3'),
                $this->submission($exponent, 'INV-4'),
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $keys = array_keys($e->getErrors());
            sort($keys);
            $this->assertSame([
                'documents[0].Invoice.ID',
                'documents[2].Invoice.IssueDate',
                'documents[3].Invoice.LegalMonetaryTotal.PayableAmount',
            ], $keys);
        }

        $this->assertCount(0, $this->container, 'No HTTP call should be made');
    }

    public function test_submit_documents_does_not_inspect_xml(): void
    {
        $this->mockSubmissionAccepted();

        $xml = '<?xml version="1.0"?><Invoice><ID>' . str_repeat('A', 60) . '</ID></Invoice>';
        $response = $this->client->submitDocuments([$this->submission($xml)], DocumentFormat::XML);

        $this->assertSame('SUB1', $response['submissionUID']);
    }

    public function test_create_document_never_sends_amounts_in_scientific_notation(): void
    {
        $this->useGeneratedSigningCertificate();
        $this->mockDocumentSubmission();

        $this->client->createDocument($this->documentRequest([
            'total_amount' => 1e15,
            'lineItems' => [
                ['description' => 'Big ticket', 'quantity' => 1, 'unitPrice' => 1e15, 'taxRate' => 0, 'taxAmount' => 0, 'subtotal' => 1e15, 'total' => 1e15],
            ],
        ]));

        $sent = $this->lastSubmittedDocumentJson();
        // Only inspect JSON number tokens: base64 signature strings may legitimately contain "e+".
        $this->assertDoesNotMatchRegularExpression('/[:\[,]\s*-?\d+(?:\.\d+)?[eE]/', $sent);
        $this->assertStringContainsString('"PayableAmount":[{"_":1000000000000000,"currencyID":"MYR"}]', $sent);
        $this->assertStringContainsString('"PriceAmount":[{"_":1000000000000000,"currencyID":"MYR"}]', $sent);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidCreateRequestProvider(): array
    {
        return [
            'invoice number > 50' => [['invoice_no' => str_repeat('A', 51)], 'Invoice.ID'],
            'business activity > 300' => [['supplierMsicDescription' => str_repeat('B', 301)], 'Invoice.AccountingSupplierParty.Party.IndustryClassificationCode@name'],
            'MYS postcode > 5' => [['buyerPostcode' => '500001'], 'Invoice.AccountingCustomerParty.Party.PostalAddress.PostalZone'],
            'passport > 12' => [['buyerIdType' => 'PASSPORT', 'buyerIC' => 'A123456789012'], 'Invoice.AccountingCustomerParty.Party.PartyIdentification[1].ID'],
        ];
    }

    /**
     * @dataProvider invalidCreateRequestProvider
     *
     * @param  array<string, mixed>  $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCreateRequestProvider')]
    public function test_create_document_enforces_field_rules_before_authenticating(array $overrides, string $path): void
    {
        // No certificate configured: validation must fail before the key is read.
        try {
            $this->client->createDocument($this->documentRequest($overrides));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($path, $e->getErrors(), (string) json_encode($e->getErrors()));
        }

        $this->assertCount(0, $this->container, 'No HTTP call (including authentication) should be made');
    }

    public function test_create_document_output_passes_field_rules(): void
    {
        $this->useGeneratedSigningCertificate();
        $this->mockDocumentSubmission();

        $result = $this->client->createDocument($this->documentRequest());

        $this->assertSame('SUB123', $result['submissionUID']);
        $this->assertSame([], (new \Nava\MyInvois\Validation\DocumentFieldRulesValidator())->getViolations($this->lastSubmittedDocumentJson()));
    }
}
