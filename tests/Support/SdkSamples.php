<?php

namespace Nava\MyInvois\Tests\Support;

/**
 * The official LHDN SDK samples in tests/fixtures/sdk, as-is or made
 * acceptable to LHDN.
 *
 * Each sample has a placeholder AdditionalDocumentReference
 * (DocumentType "213312dddddd", ID "sa313321312"). LHDN treats any reference
 * whose DocumentType is not CustomsImportForm / FreeTradeAgreement / K2 as
 * Incoterms, so preprod rejects the raw sample with CF417 (verified
 * 2026-11-12). Giving that reference the FreeTradeAgreement type made the
 * same document Valid on preprod, and keeps every reference index unchanged.
 */
final class SdkSamples
{
    public static function raw(string $version): string
    {
        return (string) file_get_contents(__DIR__ . "/../fixtures/sdk/{$version}-Invoice-Sample.json");
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(string $version): array
    {
        $document = json_decode(self::raw($version), true);
        foreach ($document['Invoice'][0]['AdditionalDocumentReference'] as &$reference) {
            if (($reference['DocumentType'][0]['_'] ?? null) === '213312dddddd') {
                $reference['DocumentType'][0]['_'] = 'FreeTradeAgreement';
            }
        }
        unset($reference);

        return $document;
    }

    public static function json(string $version, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return (string) json_encode(self::document($version), $flags);
    }
}
