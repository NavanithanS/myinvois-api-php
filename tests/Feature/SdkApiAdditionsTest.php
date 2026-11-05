<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;

/**
 * Backward-compatible API additions from the LHDN SDK release notes:
 *  - Search Documents `classificationCode` filter (25 Sep 2026)
 *  - `typeVersionNumber` on Get Document / Get Document Details (25 Sep 2026)
 *  - Search Taxpayer's TIN `taxpayerName` / `fileType` (1 Aug 2025)
 */
class SdkApiAdditionsTest extends TestCase
{
    private const UUID = 'F9D425P6DS7D8IU';

    /** @return array<int|string, array<mixed>|string> */
    private function lastQuery(): array
    {
        $query = [];
        parse_str($this->getLastRequest()->getUri()->getQuery(), $query);

        return $query;
    }

    private function mockSearchResponse(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode([
            'documents' => [],
            'metadata' => ['totalPages' => 0, 'totalCount' => 0],
        ])));
    }

    /** @return array<string, string> */
    private function searchDates(): array
    {
        return [
            'submissionDateFrom' => '2026-10-01T00:00:00Z',
            'submissionDateTo' => '2026-10-20T00:00:00Z',
        ];
    }

    // --- classificationCode -------------------------------------------------

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function classificationCodeProvider(): array
    {
        return [
            'array' => [['001', '002'], '001,002'],
            'string' => ['001,002', '001,002'],
            'string with spaces' => [' 004 , 022 ', '004,022'],
            'single' => ['022', '022'],
            'duplicates removed' => [['001', '001'], '001'],
        ];
    }

    /**
     * @dataProvider classificationCodeProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('classificationCodeProvider')]
    public function test_search_sends_classification_code_as_comma_separated_list(mixed $input, string $expected): void
    {
        $this->mockSearchResponse();

        $this->client->searchDocuments($this->searchDates() + ['classificationCode' => $input]);

        $this->assertSame($expected, $this->lastQuery()['classificationCode']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidClassificationCodeProvider(): array
    {
        return [
            'two digits' => ['01'],
            'letters' => [['ABC']],
            'unknown code' => ['999'],
            'one bad in list' => ['001,99'],
            'empty string' => [''],
            'empty array' => [[]],
            'integer' => [1],
        ];
    }

    /**
     * @dataProvider invalidClassificationCodeProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidClassificationCodeProvider')]
    public function test_search_rejects_invalid_classification_codes(mixed $input): void
    {
        try {
            $this->client->searchDocuments($this->searchDates() + ['classificationCode' => $input]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('classificationCode', $e->getErrors());
        }

        $this->assertCount(0, $this->container, 'No HTTP call should be made');
    }

    public function test_search_omits_classification_code_when_not_given(): void
    {
        $this->mockSearchResponse();

        $this->client->searchDocuments($this->searchDates());

        $this->assertArrayNotHasKey('classificationCode', $this->lastQuery());
    }

    // --- typeVersionNumber --------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function documentResponse(array $extra = []): array
    {
        return array_merge([
            'uuid' => self::UUID,
            'submissionUid' => 'HJSD135P2S7D8IU',
            'internalId' => 'PZ-234-A',
            'typeName' => 'invoice',
            'typeVersionName' => 'Version 1.1',
            'issuerTin' => 'C2584563200',
            'issuerName' => 'AMS Setia Jaya Sdn. Bhd.',
            'dateTimeIssued' => '2026-10-01T10:00:00Z',
            'dateTimeReceived' => '2026-10-01T10:05:00Z',
            'totalExcludingTax' => '10.10',
            'totalDiscount' => '0.00',
            'totalNetAmount' => '10.10',
            'totalPayableAmount' => '10.71',
            'status' => 'Valid',
            'createdByUserId' => 'general.ams@supplier.com',
        ], $extra);
    }

    public function test_document_details_maps_type_version_number(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode($this->documentResponse(['typeVersionNumber' => '1.1']))));

        $details = $this->client->getDocumentDetails(self::UUID);

        $this->assertSame('1.1', $details['typeVersionNumber']);
    }

    public function test_document_details_type_version_number_is_null_when_absent(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode($this->documentResponse())));

        $details = $this->client->getDocumentDetails(self::UUID);

        $this->assertArrayHasKey('typeVersionNumber', $details);
        $this->assertNull($details['typeVersionNumber']);
    }

    public function test_get_document_maps_type_version_number(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode($this->documentResponse(['typeVersionNumber' => '1.3']))));

        $this->assertSame('1.3', $this->client->getDocument(self::UUID)['typeVersionNumber']);
    }

    public function test_get_document_type_version_number_is_null_when_absent(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode($this->documentResponse())));

        $document = $this->client->getDocument(self::UUID);

        $this->assertArrayHasKey('typeVersionNumber', $document);
        $this->assertNull($document['typeVersionNumber']);
    }

    // --- searchTaxpayerTin --------------------------------------------------

    /**
     * @return array<string, array{array{?string, ?string, ?string, ?int}, array<string, string>}>
     */
    public static function tinSearchProvider(): array
    {
        return [
            'name only' => [['ABC XYZ', null, null, null], ['taxpayerName' => 'ABC XYZ']],
            'name + id' => [['ABC XYZ', 'NRIC', '770625015324', null], ['taxpayerName' => 'ABC XYZ', 'idType' => 'NRIC', 'idValue' => '770625015324']],
            'id only' => [[null, 'BRN', '201901234567', null], ['idType' => 'BRN', 'idValue' => '201901234567']],
            'id + fileType individual' => [[null, 'nric', '770625015324', 1], ['idType' => 'NRIC', 'idValue' => '770625015324', 'fileType' => '1']],
            'all, non-individual' => [['ABC Sdn Bhd', 'BRN', '201901234567', 2], ['taxpayerName' => 'ABC Sdn Bhd', 'idType' => 'BRN', 'idValue' => '201901234567', 'fileType' => '2']],
        ];
    }

    /**
     * @dataProvider tinSearchProvider
     *
     * @param  array{?string, ?string, ?string, ?int}  $args
     * @param  array<string, string>  $expectedQuery
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tinSearchProvider')]
    public function test_search_taxpayer_tin_builds_query(array $args, array $expectedQuery): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode(['tin' => 'C1234567890'])));

        $result = $this->client->searchTaxpayerTin(...$args);

        $this->assertSame(['tin' => 'C1234567890'], $result);
        $request = $this->getLastRequest();
        $this->assertSame('/api/v1.0/taxpayer/search/tin', $request->getUri()->getPath());
        $query = $this->lastQuery();
        ksort($query);
        ksort($expectedQuery);
        $this->assertSame($expectedQuery, $query);
    }

    /**
     * @return array<string, array{array{?string, ?string, ?string, ?int}, string}>
     */
    public static function invalidTinSearchProvider(): array
    {
        return [
            'nothing' => [[null, null, null, null], 'taxpayerName'],
            'blank name' => [['  ', null, null, null], 'taxpayerName'],
            'idType without idValue' => [['ABC', 'NRIC', null, null], 'idValue'],
            'idValue without idType' => [['ABC', null, '770625015324', null], 'idType'],
            'fileType out of range' => [[null, 'NRIC', '770625015324', 3], 'fileType'],
            'fileType with name only' => [['ABC', null, null, 1], 'fileType'],
            'unknown idType' => [[null, 'DRIVING', '123', null], 'idType'],
            'bad NRIC' => [[null, 'NRIC', '1234', null], 'idValue'],
            'passport too long' => [[null, 'PASSPORT', 'A123456789012', null], 'idValue'],
        ];
    }

    /**
     * @dataProvider invalidTinSearchProvider
     *
     * @param  array{?string, ?string, ?string, ?int}  $args
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTinSearchProvider')]
    public function test_search_taxpayer_tin_guards(array $args, string $errorKey): void
    {
        try {
            $this->client->searchTaxpayerTin(...$args);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($errorKey, $e->getErrors());
        }

        $this->assertCount(0, $this->container, 'No HTTP call should be made');
    }

    public function test_deprecated_get_taxpayer_tin_still_sends_id_query(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode(['tin' => 'IG12345678901'])));

        $result = $this->client->getTaxpayerTin('NRIC', '770625015324');

        $this->assertSame(['tin' => 'IG12345678901'], $result);
        $this->assertSame(['idType' => 'NRIC', 'idValue' => '770625015324'], $this->lastQuery());
    }

    public function test_deprecated_get_taxpayer_tin_still_throws_invalid_argument_on_empty_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client->getTaxpayerTin('NRIC', '');
    }
}
