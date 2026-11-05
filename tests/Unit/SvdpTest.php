<?php

namespace Nava\MyInvois\Tests\Unit;

use DateTimeImmutable;
use Nava\MyInvois\Config;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Validation\SvdpValidator;
use PHPUnit\Framework\TestCase;

/**
 * e-Invoice Voluntary Disclosure Programme (SDK release note 8 Jul 2026).
 * Every test passes an explicit clock; none depend on the real date.
 */
class SvdpTest extends TestCase
{
    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function programmeWindowProvider(): array
    {
        return [
            'mid programme' => ['2026-11-05T00:00:00Z', true],
            'last day, 23:59:59 MYT' => ['2027-12-31T15:59:59Z', true],
            'first second of 2028 MYT' => ['2027-12-31T16:00:00Z', false],
            '2028-01-01 UTC' => ['2028-01-01T00:00:00Z', false],
        ];
    }

    /**
     * @dataProvider programmeWindowProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('programmeWindowProvider')]
    public function test_programme_window(string $now, bool $active): void
    {
        $this->assertSame($active, Config::isSvdpActive(self::at($now)));
    }

    public function test_svdp_versions(): void
    {
        $this->assertTrue(Config::isSvdpVersion('1.2'));
        $this->assertTrue(Config::isSvdpVersion('1.3'));
        $this->assertFalse(Config::isSvdpVersion('1.1'));
        $this->assertSame(['1.2', '1.3'], Config::SVDP_VERSIONS);
    }

    public function test_svdp_versions_supported_for_every_document_type_only_while_active(): void
    {
        $during = self::at('2027-06-01T00:00:00Z');
        $after = self::at('2028-01-01T00:00:00Z');

        foreach (['invoice', 'credit_note', 'debit_note', 'refund_note'] as $type) {
            foreach (['1.2', '1.3'] as $version) {
                $this->assertTrue(Config::isVersionSupported($type, $version, $during), "$type $version during");
                $this->assertFalse(Config::isVersionSupported($type, $version, $after), "$type $version after");
            }
            $this->assertTrue(Config::isVersionSupported($type, '1.1', $after), "$type 1.1 unaffected");
        }

        $this->assertFalse(Config::isVersionSupported('unknown', '1.3', $during));
        // Regular version lists are unchanged
        $this->assertSame(['1.0', '1.1'], Config::INVOICE_SUPPORTED_VERSIONS);
    }

    /**
     * @return array<string, array{string, bool, bool, ?string}>
     */
    public static function signatureProvider(): array
    {
        return [
            '1.2 unsigned ok' => ['1.2', false, true, null],
            '1.2 signed rejected' => ['1.2', true, true, 'without a digital signature'],
            '1.3 signed ok' => ['1.3', true, true, null],
            '1.3 unsigned rejected' => ['1.3', false, true, 'requires a digital signature'],
            '1.3 after programme' => ['1.3', true, false, 'can only be used until 2027-12-31'],
            '1.1 never checked' => ['1.1', false, false, null],
        ];
    }

    /**
     * @dataProvider signatureProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('signatureProvider')]
    public function test_signature_and_window_rules(string $version, bool $signed, bool $active, ?string $expected): void
    {
        $invoice = ['ID' => [['_' => 'INV-1']], 'InvoiceTypeCode' => [['_' => '01', 'listVersionID' => $version]]];
        if ($signed) {
            $invoice['UBLExtensions'] = [['UBLExtension' => []]];
            $invoice['Signature'] = [['ID' => [['_' => 'urn:oasis:names:specification:ubl:signature:Invoice']]]];
        }
        $now = $active ? self::at('2027-01-01T00:00:00Z') : self::at('2028-02-01T00:00:00Z');

        $errors = (new SvdpValidator())->getViolations(['Invoice' => [$invoice]], $now, 'documents[1].');

        if ($expected === null) {
            $this->assertSame([], $errors);
        } else {
            $this->assertArrayHasKey('documents[1].Invoice.InvoiceTypeCode@listVersionID', $errors);
            $this->assertStringContainsString($expected, implode(' ', $errors['documents[1].Invoice.InvoiceTypeCode@listVersionID']));
        }
    }

    public function test_official_svdp_samples_pass(): void
    {
        $now = self::at('2026-11-05T00:00:00Z');
        foreach (['1.2', '1.3'] as $version) {
            $document = json_decode((string) file_get_contents(__DIR__ . "/../fixtures/sdk/{$version}-Invoice-Sample.json"), true);
            $this->assertSame([], (new SvdpValidator())->getViolations($document, $now), $version);
        }
    }

    /**
     * @return array<string, array{string, ?string, string, array{string, string}}>
     */
    public static function validIssueDateProvider(): array
    {
        return [
            'earliest date' => ['2024-08-01', '08:00:00', '2026-11-05T10:00:00Z', ['2024-08-01', '08:00:00Z']],
            'trailing Z accepted' => ['2025-03-15', '23:59:59Z', '2026-11-05T10:00:00Z', ['2025-03-15', '23:59:59Z']],
            'time defaults to now' => ['2025-03-15', null, '2026-11-05T10:11:12Z', ['2025-03-15', '10:11:12Z']],
            'today, earlier time' => ['2026-11-05', '09:59:59', '2026-11-05T10:00:00Z', ['2026-11-05', '09:59:59Z']],
            'last programme day' => ['2027-12-31', '15:00:00', '2027-12-31T15:30:00Z', ['2027-12-31', '15:00:00Z']],
        ];
    }

    /**
     * @dataProvider validIssueDateProvider
     *
     * @param  array{string, string}  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validIssueDateProvider')]
    public function test_valid_backdated_issue_dates(string $date, ?string $time, string $now, array $expected): void
    {
        $this->assertSame($expected, (new SvdpValidator())->resolveIssueDateTime($date, $time, self::at($now)));
    }

    /**
     * @return array<string, array{string, ?string, string, string}>
     */
    public static function invalidIssueDateProvider(): array
    {
        return [
            'before 2024-08-01' => ['2024-07-31', '00:00:00', '2026-11-05T10:00:00Z', 'issueDate'],
            'future date' => ['2026-11-06', '00:00:00', '2026-11-05T10:00:00Z', 'issueDate'],
            'later today' => ['2026-11-05', '10:00:01', '2026-11-05T10:00:00Z', 'issueDate'],
            'programme ended' => ['2025-01-01', '00:00:00', '2028-01-01T00:00:00Z', 'issueDate'],
            'not a date' => ['N/A', null, '2026-11-05T10:00:00Z', 'issueDate'],
            'impossible date' => ['2025-02-29', null, '2026-11-05T10:00:00Z', 'issueDate'],
            'bad time' => ['2025-03-15', '25:00:00', '2026-11-05T10:00:00Z', 'issueTime'],
        ];
    }

    /**
     * @dataProvider invalidIssueDateProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIssueDateProvider')]
    public function test_invalid_backdated_issue_dates(string $date, ?string $time, string $now, string $errorKey): void
    {
        try {
            (new SvdpValidator())->resolveIssueDateTime($date, $time, self::at($now));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($errorKey, $e->getErrors());
        }
    }
}
