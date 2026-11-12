<?php

namespace Nava\MyInvois\Tests\Feature;

use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\Support\CreatesDocuments;
use Nava\MyInvois\Tests\TestCase;

/**
 * State code resolution in createDocument().
 *
 * Since 30 Apr 2026 LHDN only accepts state code 17 ("Not Applicable") for
 * consolidated e-Invoices or addresses outside Malaysia.
 */
class CreateDocumentStateCodeTest extends TestCase
{
    use CreatesDocuments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useGeneratedSigningCertificate();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validStateProvider(): array
    {
        return [
            'known name' => ['Selangor', '10'],
            'alias' => ['Penang', '07'],
            'numeric code' => ['14', '14'],
            'single digit code' => ['1', '01'],
            'trailing space' => ['Johor ', '01'],
            'case-insensitive' => ['kuala lumpur', '14'],
            'long-form name' => ['Wilayah Persekutuan Labuan', '15'],
        ];
    }

    /**
     * @dataProvider validStateProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validStateProvider')]
    public function test_it_resolves_valid_buyer_states(string $input, string $expected): void
    {
        $this->mockDocumentSubmission();

        $result = $this->client->createDocument($this->documentRequest(['buyerState' => $input]));
        $this->assertSame('SUB123', $result['submissionUID'] ?? null);

        $document = $this->lastSubmittedDocument();
        $buyerAddress = $document['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PostalAddress'][0];
        $this->assertSame($expected, $buyerAddress['CountrySubentityCode'][0]['_']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidStateProvider(): array
    {
        return [
            'unknown name' => ['Atlantis'],
            'out of range code' => ['18'],
            'zero code' => ['00'],
            'empty' => [''],
            'missing' => [null],
        ];
    }

    /**
     * @dataProvider invalidStateProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidStateProvider')]
    public function test_it_throws_for_unknown_buyer_state(mixed $input): void
    {
        try {
            $this->client->createDocument($this->documentRequest(['buyerState' => $input]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('buyerState', $e->getErrors());
        }

        $this->assertCount(0, $this->container, 'No HTTP call should be made');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notApplicableProvider(): array
    {
        return [
            'code' => ['17'],
            'name' => ['Not Applicable'],
            'abbreviation' => ['NA'],
            'lowercase abbreviation' => ['na'],
        ];
    }

    /**
     * @dataProvider notApplicableProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('notApplicableProvider')]
    public function test_state_17_is_rejected_for_a_normal_buyer(string $input): void
    {
        try {
            $this->client->createDocument($this->documentRequest(['buyerState' => $input]));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('17', $e->getErrors()['buyerState'][0]);
            $this->assertStringContainsString($input, $e->getErrors()['buyerState'][0]);
        }
    }

    public function test_state_17_is_rejected_for_the_supplier_of_a_normal_invoice(): void
    {
        try {
            $this->client->createDocument($this->documentRequest(['supplierState' => '17']));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplierState', $e->getErrors());
        }
    }

    /**
     * @dataProvider notApplicableProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('notApplicableProvider')]
    public function test_state_17_is_accepted_for_consolidated_e_invoice(string $input): void
    {
        $this->mockDocumentSubmission();

        $this->client->createDocument($this->documentRequest(self::consolidated(['buyerState' => $input])));

        $document = $this->lastSubmittedDocument();
        $buyerAddress = $document['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PostalAddress'][0];
        $this->assertSame('17', $buyerAddress['CountrySubentityCode'][0]['_']);
    }

    /**
     * Consolidated e-Invoice: general-public buyer TIN and classification code 004
     * (LHDN preprod rejects state 17 with CV317 when the 004 line is missing).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function consolidated(array $overrides = []): array
    {
        return array_replace([
            'buyerTIN' => 'EI00000000010',
            'buyerName' => 'General Public',
            'lineItems' => [
                ['description' => 'Daily sales', 'quantity' => 1, 'unitPrice' => 100.00, 'taxRate' => 6, 'taxAmount' => 6.00, 'subtotal' => 100.00, 'total' => 106.00, 'classificationCode' => '004'],
            ],
        ], $overrides);
    }

    public function test_state_17_is_rejected_for_general_buyer_without_classification_004(): void
    {
        $request = self::consolidated(['buyerState' => '17']);
        $request['lineItems'][0]['classificationCode'] = '022';

        try {
            $this->client->createDocument($this->documentRequest($request));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('classificationCode 004', $e->getErrors()['buyerState'][0]);
        }
        $this->assertCount(0, $this->container);
    }
}
