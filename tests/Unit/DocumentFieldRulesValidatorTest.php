<?php

namespace Nava\MyInvois\Tests\Unit;

use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Validation\DocumentFieldRulesValidator;
use Nava\MyInvois\Tests\Support\SdkSamples;
use PHPUnit\Framework\TestCase;

/**
 * LHDN field length/format rules (SDK release notes 30 Apr, 3 Jul, 6 Aug 2026).
 *
 * Each rule is exercised by mutating the official SDK 1.1 sample, which must
 * itself pass without violations.
 */
class DocumentFieldRulesValidatorTest extends TestCase
{
    private const SUPPLIER = 'Invoice.AccountingSupplierParty.Party';

    private const CUSTOMER = 'Invoice.AccountingCustomerParty.Party';

    /** @return array<string, mixed> */
    private static function sample(string $version = '1.1'): array
    {
        return SdkSamples::document($version);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
     * @return array<string, array<int, string>>
     */
    private function violationsAfter(callable $mutate): array
    {
        $document = $mutate(self::sample());

        return (new DocumentFieldRulesValidator())->getViolations((string) json_encode($document));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sampleProvider(): array
    {
        return ['1.1' => ['1.1'], '1.2' => ['1.2'], '1.3' => ['1.3']];
    }

    /**
     * @dataProvider sampleProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sampleProvider')]
    public function test_official_sdk_samples_have_no_violations_once_the_placeholder_reference_is_typed(string $version): void
    {
        $this->assertSame([], (new DocumentFieldRulesValidator())->getViolations(SdkSamples::json($version)));
    }

    /**
     * The raw official samples are rejected by LHDN preprod with CF417 (Incoterms
     * max 3) because of a placeholder reference with an unknown DocumentType.
     *
     * @dataProvider sampleProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sampleProvider')]
    public function test_raw_official_samples_fail_incoterms_like_lhdn(string $version): void
    {
        $violations = (new DocumentFieldRulesValidator())->getViolations(SdkSamples::raw($version));

        $this->assertSame(['Invoice.AdditionalDocumentReference[1].ID'], array_keys($violations));
        $this->assertStringContainsString('Incoterms', $violations['Invoice.AdditionalDocumentReference[1].ID'][0]);
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function referenceTypeProvider(): array
    {
        return [
            'CustomsImportForm' => ['CustomsImportForm', false],
            'FreeTradeAgreement' => ['FreeTradeAgreement', false],
            'K2' => ['K2', false],
            'no DocumentType' => [null, true],
            'empty DocumentType' => ['', true],
            'unknown DocumentType' => ['Other', true],
        ];
    }

    /**
     * @dataProvider referenceTypeProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('referenceTypeProvider')]
    public function test_incoterms_limit_applies_to_every_reference_type_except_customs_and_fta(?string $type, bool $limited): void
    {
        $violations = $this->violationsAfter(static function (array $d) use ($type): array {
            $reference = ['ID' => [['_' => 'LONGER-THAN-3']]];
            if ($type !== null) {
                $reference['DocumentType'] = [['_' => $type]];
            }
            $d['Invoice'][0]['AdditionalDocumentReference'][] = $reference;

            return $d;
        });

        $this->assertSame($limited ? ['Invoice.AdditionalDocumentReference[4].ID'] : [], array_keys($violations));
    }

    /**
     * Each case: [mutation that stays valid (boundary), mutation that breaks the rule, expected error path].
     *
     * @return array<string, array{callable, callable, string}>
     */
    public static function ruleProvider(): array
    {
        $set = static function (string $field, $value): callable {
            return static function (array $d) use ($field, $value): array {
                $i = &$d['Invoice'][0];
                switch ($field) {
                    case 'id': $i['ID'][0]['_'] = $value; break;
                    case 'bank': $i['PaymentMeans'][0]['PayeeFinancialAccount'][0]['ID'][0]['_'] = $value; break;
                    case 'certex': $i['AccountingSupplierParty'][0]['AdditionalAccountID'][0]['_'] = $value; break;
                    case 'incoterms': $i['AdditionalDocumentReference'][3]['ID'][0]['_'] = $value; break;
                    case 'frequency': $i['InvoicePeriod'][0]['Description'][0]['_'] = $value; break;
                    case 'msicName': $i['AccountingSupplierParty'][0]['Party'][0]['IndustryClassificationCode'][0]['name'] = $value; break;
                    case 'terms': $i['PaymentTerms'][0]['Note'][0]['_'] = $value; break;
                    case 'prepaid': $i['PrepaidPayment'][0]['ID'][0]['_'] = $value; break;
                    case 'issueDate': $i['IssueDate'][0]['_'] = $value; break;
                    case 'startDate': $i['InvoicePeriod'][0]['StartDate'][0]['_'] = $value; break;
                    case 'paidDate': $i['PrepaidPayment'][0]['PaidDate'][0]['_'] = $value; break;
                    case 'buyerState': $i['AccountingCustomerParty'][0]['Party'][0]['PostalAddress'][0]['CountrySubentityCode'][0]['_'] = $value; break;
                    case 'buyerPostcode': $i['AccountingCustomerParty'][0]['Party'][0]['PostalAddress'][0]['PostalZone'][0]['_'] = $value; break;
                    case 'shippingPostcode': $i['Delivery'][0]['DeliveryParty'][0]['PostalAddress'][0]['PostalZone'][0]['_'] = $value; break;
                    case 'unit': $i['InvoiceLine'][0]['InvoicedQuantity'][0]['unitCode'] = $value; break;
                    case 'buyerCountry': $i['AccountingCustomerParty'][0]['Party'][0]['PostalAddress'][0]['Country'][0]['IdentificationCode'][0]['_'] = $value; break;
                    case 'currency': $i['DocumentCurrencyCode'][0]['_'] = $value; break;
                    case 'currencyID': $i['LegalMonetaryTotal'][0]['PayableAmount'][0]['currencyID'] = $value; break;
                    case 'paymentMode': $i['PaymentMeans'][0]['PaymentMeansCode'][0]['_'] = $value; break;
                    case 'taxType': $i['TaxTotal'][0]['TaxSubtotal'][0]['TaxCategory'][0]['ID'][0]['_'] = $value; break;
                    case 'passport': $i['AccountingCustomerParty'][0]['Party'][0]['PartyIdentification'][1]['ID'][0] = ['_' => $value, 'schemeID' => 'PASSPORT']; break;
                    case 'payable': $i['LegalMonetaryTotal'][0]['PayableAmount'][0]['_'] = $value; break;
                }

                return $d;
            };
        };

        return [
            'e-Invoice number max 50' => [$set('id', str_repeat('A', 50)), $set('id', str_repeat('A', 51)), 'Invoice.ID'],
            'bank account max 150' => [$set('bank', str_repeat('1', 150)), $set('bank', str_repeat('1', 151)), 'Invoice.PaymentMeans.PayeeFinancialAccount.ID'],
            'CertEX max 300' => [$set('certex', str_repeat('C', 300)), $set('certex', str_repeat('C', 301)), 'Invoice.AccountingSupplierParty.AdditionalAccountID'],
            'Incoterms max 3' => [$set('incoterms', 'FOB'), $set('incoterms', 'FOBX'), 'Invoice.AdditionalDocumentReference[3].ID'],
            'billing frequency max 50' => [$set('frequency', str_repeat('M', 50)), $set('frequency', str_repeat('M', 51)), 'Invoice.InvoicePeriod.Description'],
            'business activity max 300' => [$set('msicName', str_repeat('B', 300)), $set('msicName', str_repeat('B', 301)), self::SUPPLIER . '.IndustryClassificationCode@name'],
            'payment terms max 300' => [$set('terms', str_repeat('T', 300)), $set('terms', str_repeat('T', 301)), 'Invoice.PaymentTerms.Note'],
            'prepayment reference max 150' => [$set('prepaid', str_repeat('P', 150)), $set('prepaid', str_repeat('P', 151)), 'Invoice.PrepaidPayment.ID'],
            'issue date not N/A' => [$set('issueDate', '2026-02-28'), $set('issueDate', 'N/A'), 'Invoice.IssueDate'],
            'billing start date real date' => [$set('startDate', '2024-02-29'), $set('startDate', '2025-02-29'), 'Invoice.InvoicePeriod.StartDate'],
            'prepayment date format' => [$set('paidDate', '2024-07-23'), $set('paidDate', '23/07/2024'), 'Invoice.PrepaidPayment.PaidDate'],
            'MYS state code list' => [$set('buyerState', '16'), $set('buyerState', 'Selangor'), self::CUSTOMER . '.PostalAddress.CountrySubentityCode'],
            'MYS state 17 needs consolidated buyer' => [$set('buyerState', '01'), $set('buyerState', '17'), self::CUSTOMER . '.PostalAddress.CountrySubentityCode'],
            'MYS postcode max 5' => [$set('buyerPostcode', '50480'), $set('buyerPostcode', '504800'), self::CUSTOMER . '.PostalAddress.PostalZone'],
            'MYS shipping postcode max 5' => [$set('shippingPostcode', '12345'), $set('shippingPostcode', '123456'), 'Invoice.Delivery.DeliveryParty.PostalAddress.PostalZone'],
            'unit code list (Hectare ok)' => [$set('unit', 'HAR'), $set('unit', 'QQQ'), 'Invoice.InvoiceLine.InvoicedQuantity@unitCode'],
            'unit code is case-sensitive' => [$set('unit', 'KGM'), $set('unit', 'kgm'), 'Invoice.InvoiceLine.InvoicedQuantity@unitCode'],
            'country code list' => [$set('buyerCountry', 'MYS'), $set('buyerCountry', 'MAS'), self::CUSTOMER . '.PostalAddress.Country.IdentificationCode'],
            'currency code list' => [$set('currency', 'CLP'), $set('currency', 'RM'), 'Invoice.DocumentCurrencyCode'],
            'currencyID attribute' => [$set('currencyID', 'MYR'), $set('currencyID', 'XXY'), 'Invoice.LegalMonetaryTotal.PayableAmount@currencyID'],
            'payment mode code list (exact)' => [$set('paymentMode', '08'), $set('paymentMode', '3'), 'Invoice.PaymentMeans.PaymentMeansCode'],
            'tax type code list' => [$set('taxType', 'E'), $set('taxType', '07'), 'Invoice.TaxTotal.TaxSubtotal.TaxCategory.ID'],
            'PASSPORT max 12' => [$set('passport', 'AB1234567890'), $set('passport', 'AB12345678901'), self::CUSTOMER . '.PartyIdentification[1].ID'],
            'amount plain decimal' => [$set('payable', 1436.5), $set('payable', '1,436.50'), 'Invoice.LegalMonetaryTotal.PayableAmount'],
        ];
    }

    /**
     * @dataProvider ruleProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ruleProvider')]
    public function test_rule(callable $valid, callable $invalid, string $path): void
    {
        $this->assertSame([], $this->violationsAfter($valid), 'Boundary value must pass');

        $violations = $this->violationsAfter($invalid);
        $this->assertArrayHasKey($path, $violations, 'Got: ' . json_encode($violations));
        $this->assertCount(1, $violations, 'Only the mutated field should fail. Got: ' . json_encode($violations));
    }

    /**
     * Matches LHDN preprod (2026-11-12): general buyer TIN + classification 003 got
     * CV317; with classification 004 state 17 was accepted.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function consolidatedProvider(): array
    {
        return [
            'general TIN + 004' => ['EI00000000010', '004', true],
            'general TIN without 004' => ['EI00000000010', '003', false],
            '004 without general TIN' => ['IG12345678901', '004', false],
        ];
    }

    /**
     * @dataProvider consolidatedProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('consolidatedProvider')]
    public function test_state_17_needs_general_buyer_tin_and_classification_004(string $tin, string $class, bool $allowed): void
    {
        $violations = $this->violationsAfter(static function (array $d) use ($tin, $class): array {
            $party = &$d['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0];
            $party['PartyIdentification'][0]['ID'][0]['_'] = $tin;
            $party['PostalAddress'][0]['CountrySubentityCode'][0]['_'] = '17';
            unset($party);
            foreach ($d['Invoice'][0]['InvoiceLine'][0]['Item'][0]['CommodityClassification'] as &$classification) {
                if ($classification['ItemClassificationCode'][0]['listID'] === 'CLASS') {
                    $classification['ItemClassificationCode'][0]['_'] = $class;
                }
            }

            return $d;
        });

        $this->assertSame($allowed ? [] : [self::CUSTOMER . '.PostalAddress.CountrySubentityCode'], array_keys($violations));
    }

    public function test_non_malaysian_address_allows_state_name_and_long_postcode_up_to_50(): void
    {
        $mutate = static function (string $state, string $postcode): callable {
            return static function (array $d) use ($state, $postcode): array {
                $address = &$d['Invoice'][0]['AccountingCustomerParty'][0]['Party'][0]['PostalAddress'][0];
                $address['Country'][0]['IdentificationCode'][0]['_'] = 'SGP';
                $address['CountrySubentityCode'][0]['_'] = $state;
                $address['PostalZone'][0]['_'] = $postcode;

                return $d;
            };
        };

        $this->assertSame([], $this->violationsAfter($mutate(str_repeat('S', 50), str_repeat('9', 50))));
        $this->assertSame([], $this->violationsAfter($mutate('17', '018989')));

        $violations = $this->violationsAfter($mutate(str_repeat('S', 51), str_repeat('9', 51)));
        $this->assertArrayHasKey(self::CUSTOMER . '.PostalAddress.CountrySubentityCode', $violations);
        $this->assertArrayHasKey(self::CUSTOMER . '.PostalAddress.PostalZone', $violations);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function rawAmountProvider(): array
    {
        return [
            '26 digits ok' => ['12345678901234567890123.456', true],
            '27 digits' => ['123456789012345678901234.567', false],
            'negative 26 digits ok' => ['-1234567890123456789012345.6', true],
            'exponent lowercase' => ['1e15', false],
            'exponent with fraction' => ['1.4365E+3', false],
        ];
    }

    /**
     * Amount rules are checked on the raw JSON text, before any float conversion.
     *
     * @dataProvider rawAmountProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rawAmountProvider')]
    public function test_amount_literal_rules(string $literal, bool $valid): void
    {
        $json = (string) json_encode(self::sample());
        $json = str_replace('"PayableAmount":[{"_":1436.5,', '"PayableAmount":[{"_":' . $literal . ',', $json, $count);
        $this->assertSame(1, $count);

        $violations = (new DocumentFieldRulesValidator())->getViolations($json);

        if ($valid) {
            $this->assertSame([], $violations);
        } else {
            $this->assertArrayHasKey('Invoice.LegalMonetaryTotal.PayableAmount', $violations);
        }
    }

    public function test_line_level_amounts_and_tax_rate_are_checked(): void
    {
        $json = (string) json_encode(self::sample());
        $json = (string) preg_replace('/"PriceAmount":\[\{"_":[0-9.]+/', '"PriceAmount":[{"_":1E2', $json, 1);
        $json = (string) preg_replace('/"Percent":\[\{"_":[0-9.]+/', '"Percent":[{"_":6e0', $json, 1);

        $violations = (new DocumentFieldRulesValidator())->getViolations($json);

        $this->assertArrayHasKey('Invoice.InvoiceLine.Price.PriceAmount', $violations);
        $this->assertArrayHasKey('Invoice.InvoiceLine.TaxTotal.TaxSubtotal.Percent', $violations);
    }

    public function test_all_violations_are_reported_in_one_exception(): void
    {
        $document = self::sample();
        $document['Invoice'][0]['ID'][0]['_'] = str_repeat('A', 51);
        $document['Invoice'][0]['IssueDate'][0]['_'] = 'N/A';
        $document['Invoice'][0]['InvoiceLine'][0]['InvoicedQuantity'][0]['unitCode'] = 'QQQ';

        try {
            (new DocumentFieldRulesValidator())->validate((string) json_encode($document), 'documents[2].');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['documents[2].Invoice.ID', 'documents[2].Invoice.InvoiceLine.InvoicedQuantity@unitCode', 'documents[2].Invoice.IssueDate'],
                $this->sortedKeys($e->getErrors())
            );
            $this->assertStringContainsString('3 violations', $e->getMessage());
            $this->assertSame(['must not exceed 50 characters (got 51)'], $e->getErrors()['documents[2].Invoice.ID']);
        }
    }

    public function test_signature_blocks_are_not_validated(): void
    {
        $document = self::sample();
        $document['Invoice'][0]['UBLExtensions'][0]['SigningDate'] = [['_' => 'not a date']];
        $document['Invoice'][0]['Signature'][0]['FakeAmount'] = [['_' => '1e9']];

        $this->assertSame([], (new DocumentFieldRulesValidator())->getViolations((string) json_encode($document)));
    }

    public function test_documents_without_invoice_root_or_invalid_json_are_ignored(): void
    {
        $validator = new DocumentFieldRulesValidator();

        $this->assertSame([], $validator->getViolations('{"invoiceNumber":"INV-001","amount":1e99}'));
        $this->assertSame([], $validator->getViolations('not json'));
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<int, string>
     */
    private function sortedKeys(array $errors): array
    {
        $keys = array_keys($errors);
        sort($keys);

        return $keys;
    }
}
