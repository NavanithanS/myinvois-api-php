<?php

namespace Nava\MyInvois\Tests\Feature;

use Carbon\Carbon;
use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\Support\CreatesDocuments;
use Nava\MyInvois\Tests\TestCase;

/**
 * SVDP (versions 1.2 / 1.3) through createDocument() and submitDocuments().
 * The clock is always frozen with Carbon::setTestNow() or setClock().
 */
class SvdpSubmissionTest extends TestCase
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

    // --- createDocument() ---------------------------------------------------

    public function test_create_document_defaults_to_version_1_1_and_current_utc_time(): void
    {
        $this->useGeneratedSigningCertificate();
        $this->mockDocumentSubmission();

        $this->client->createDocument($this->documentRequest());

        $invoice = $this->lastSubmittedDocument()['Invoice'][0];
        $this->assertSame('1.1', $invoice['InvoiceTypeCode'][0]['listVersionID']);
        $this->assertSame('2026-11-05', $invoice['IssueDate'][0]['_']);
        $this->assertSame('10:00:00Z', $invoice['IssueTime'][0]['_']);
    }

    public function test_create_document_with_svdp_1_3_and_backdated_issue_date(): void
    {
        $this->useGeneratedSigningCertificate();
        $this->mockDocumentSubmission();

        $result = $this->client->createDocument($this->documentRequest([
            'documentVersion' => '1.3',
            'issueDate' => '2024-08-01',
            'issueTime' => '09:30:00Z',
        ]));

        $this->assertSame('SUB123', $result['submissionUID']);
        $invoice = $this->lastSubmittedDocument()['Invoice'][0];
        $this->assertSame('1.3', $invoice['InvoiceTypeCode'][0]['listVersionID']);
        $this->assertSame('2024-08-01', $invoice['IssueDate'][0]['_']);
        $this->assertSame('09:30:00Z', $invoice['IssueTime'][0]['_']);
        $this->assertArrayHasKey('UBLExtensions', $invoice);
        $this->assertArrayHasKey('Signature', $invoice);

        // Signing time stays the real (frozen) time, not the backdated issue time
        $signingTime = $invoice['UBLExtensions'][0]['UBLExtension'][0]['ExtensionContent'][0]['UBLDocumentSignatures'][0]
            ['SignatureInformation'][0]['Signature'][0]['Object'][0]['QualifyingProperties'][0]['SignedProperties'][0]
            ['SignedSignatureProperties'][0]['SigningTime'][0]['_'];
        $this->assertSame('2026-11-05T10:00:00Z', $signingTime);
    }

    public function test_create_document_with_svdp_1_3_without_issue_date_uses_now(): void
    {
        $this->useGeneratedSigningCertificate();
        $this->mockDocumentSubmission();

        $this->client->createDocument($this->documentRequest(['documentVersion' => '1.3']));

        $invoice = $this->lastSubmittedDocument()['Invoice'][0];
        $this->assertSame('1.3', $invoice['InvoiceTypeCode'][0]['listVersionID']);
        $this->assertSame('2026-11-05', $invoice['IssueDate'][0]['_']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string, ?string}>
     */
    public static function rejectedCreateProvider(): array
    {
        return [
            'unsigned 1.0' => [['documentVersion' => '1.0'], 'documentVersion', 'submitDocuments()', null],
            'unsigned SVDP 1.2' => [['documentVersion' => '1.2'], 'documentVersion', 'submitDocuments()', null],
            'unknown version' => [['documentVersion' => '2.0'], 'documentVersion', '1.1 or SVDP 1.3', null],
            'SVDP after programme' => [['documentVersion' => '1.3'], 'documentVersion', '2027-12-31', '2028-01-01T00:00:00Z'],
            'backdating without SVDP' => [['issueDate' => '2025-01-01'], 'issueDate', 'documentVersion 1.3', null],
            'issueTime without SVDP' => [['issueTime' => '10:00:00'], 'issueDate', 'documentVersion 1.3', null],
            'before 2024-08-01' => [['documentVersion' => '1.3', 'issueDate' => '2024-07-31'], 'issueDate', '2024-08-01', null],
            'future issue date' => [['documentVersion' => '1.3', 'issueDate' => '2026-11-06'], 'issueDate', 'future', null],
            'N/A issue date' => [['documentVersion' => '1.3', 'issueDate' => 'N/A'], 'issueDate', 'YYYY-MM-DD', null],
        ];
    }

    /**
     * @dataProvider rejectedCreateProvider
     *
     * @param  array<string, mixed>  $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedCreateProvider')]
    public function test_create_document_rejects(array $overrides, string $key, string $messagePart, ?string $now): void
    {
        if ($now !== null) {
            Carbon::setTestNow(Carbon::parse($now));
        }

        try {
            $this->client->createDocument($this->documentRequest($overrides));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->getErrors());
            $this->assertStringContainsString($messagePart, implode(' ', $e->getErrors()[$key]));
        }

        $this->assertCount(0, $this->container, 'Rejected before any HTTP call');
    }

    // --- submitDocuments() ----------------------------------------------------

    /**
     * @return array{document: string, documentHash: string, codeNumber: string}
     */
    private function sample(string $version, ?callable $mutate = null): array
    {
        $json = (string) file_get_contents(__DIR__ . "/../fixtures/sdk/{$version}-Invoice-Sample.json");
        if ($mutate !== null) {
            $json = (string) json_encode($mutate(json_decode($json, true)));
        }

        return ['document' => $json, 'documentHash' => hash('sha256', $json), 'codeNumber' => 'SVDP-' . str_replace('.', '', $version)];
    }

    private function mockAccepted(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(202, [], (string) json_encode([
            'submissionUID' => 'SUB1', 'acceptedDocuments' => [], 'rejectedDocuments' => [],
        ])));
    }

    public function test_submit_documents_accepts_official_svdp_samples_during_programme(): void
    {
        $this->mockAccepted();

        $response = $this->client->submitDocuments([$this->sample('1.2'), $this->sample('1.3')]);

        $this->assertSame('SUB1', $response['submissionUID']);
    }

    public function test_submit_documents_rejects_signature_mismatch(): void
    {
        $signed12 = $this->sample('1.3', static function (array $d): array {
            $d['Invoice'][0]['InvoiceTypeCode'][0]['listVersionID'] = '1.2';

            return $d;
        });
        $unsigned13 = $this->sample('1.2', static function (array $d): array {
            $d['Invoice'][0]['InvoiceTypeCode'][0]['listVersionID'] = '1.3';

            return $d;
        });
        $signed12['codeNumber'] = 'A-1';
        $unsigned13['codeNumber'] = 'A-2';

        try {
            $this->client->submitDocuments([$signed12, $unsigned13]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertStringContainsString('without a digital signature', $errors['documents[0].Invoice.InvoiceTypeCode@listVersionID'][0]);
            $this->assertStringContainsString('requires a digital signature', $errors['documents[1].Invoice.InvoiceTypeCode@listVersionID'][0]);
        }

        $this->assertCount(0, $this->container);
    }

    public function test_submit_documents_rejects_svdp_after_programme_using_injected_clock(): void
    {
        $this->client->setClock(static fn () => new DateTimeImmutable('2028-01-01T00:00:00+08:00'));

        try {
            $this->client->submitDocuments([$this->sample('1.2')]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('until 2027-12-31', $e->getErrors()['documents[0].Invoice.InvoiceTypeCode@listVersionID'][0]);
        }

        // Regular versions are unaffected by the SVDP window
        $this->mockAccepted();
        $this->assertSame('SUB1', $this->client->submitDocuments([$this->sample('1.1')])['submissionUID']);
    }

    public function test_submit_document_rejects_unsigned_1_3(): void
    {
        $unsigned13 = $this->sample('1.2', static function (array $d): array {
            $d['Invoice'][0]['InvoiceTypeCode'][0]['listVersionID'] = '1.3';

            return $d;
        });

        try {
            $this->client->submitDocument($unsigned13['document'], ['access_token' => 'x'], '1.3');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('Invoice.InvoiceTypeCode@listVersionID', $e->getErrors());
        }
    }

    public function test_svdp_version_accepted_by_note_helpers_only_during_programme(): void
    {
        $this->client->setClock(static fn () => new DateTimeImmutable('2028-01-01T00:00:00Z'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unsupported refund note version');

        $this->client->submitRefundNote(['documents' => []], '1.3');
    }
}
