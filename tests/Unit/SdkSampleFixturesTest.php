<?php

namespace Nava\MyInvois\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Sanity checks for the official LHDN SDK sample documents stored under
 * tests/fixtures/sdk. They were downloaded on 2026-11-05 from
 * https://sdk.myinvois.hasil.gov.my/files/sdksamples/ and are used as
 * known-good inputs by the document field-rules tests.
 */
class SdkSampleFixturesTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function sampleProvider(): array
    {
        return [
            'v1.1 signed' => ['1.1-Invoice-Sample.json', '1.1', true],
            'v1.2 SVDP unsigned' => ['1.2-Invoice-Sample.json', '1.2', false],
            'v1.3 SVDP signed' => ['1.3-Invoice-Sample.json', '1.3', true],
        ];
    }

    /**
     * @dataProvider sampleProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sampleProvider')]
    public function test_sdk_sample_is_valid_ubl_json(string $file, string $version, bool $signed): void
    {
        $json = file_get_contents(__DIR__ . '/../fixtures/sdk/' . $file);
        $this->assertIsString($json);

        $document = json_decode($json, true);
        $this->assertIsArray($document, 'Fixture must be valid JSON');

        $invoice = $document['Invoice'][0];
        $this->assertSame('01', $invoice['InvoiceTypeCode'][0]['_']);
        $this->assertSame($version, $invoice['InvoiceTypeCode'][0]['listVersionID']);
        $this->assertSame($signed, isset($invoice['UBLExtensions']), 'UBLExtensions presence');
        $this->assertSame($signed, isset($invoice['Signature']), 'Signature presence');
    }
}
