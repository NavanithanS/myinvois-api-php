<?php

namespace Nava\MyInvois\Tests\Unit;

use Nava\MyInvois\Support\UblJson;
use PHPUnit\Framework\TestCase;

class UblJsonTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function exponentProvider(): array
    {
        return [
            'php float 1e15' => ['1.0e+15', '1000000000000000'],
            'fractional large' => ['1.0000000000000005e+15', '1000000000000000.5'],
            'small' => ['5.0e-5', '0.00005'],
            'negative' => ['-1.5e+20', '-150000000000000000000'],
            'uppercase E no sign' => ['2E3', '2000'],
            'zero' => ['0.0e+0', '0'],
            'plain decimal untouched' => ['100.50', '100.50'],
            'integer untouched' => ['12', '12'],
        ];
    }

    /**
     * @dataProvider exponentProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('exponentProvider')]
    public function test_expand_exponent(string $input, string $expected): void
    {
        $this->assertSame($expected, UblJson::expandExponent($input));
    }

    public function test_encode_never_emits_exponent_notation(): void
    {
        $json = UblJson::encode([
            'PayableAmount' => [['_' => 1e15, 'currencyID' => 'MYR']],
            'Tiny' => [['_' => 0.00001]],
            'Normal' => [['_' => 106.5]],
            'Text' => 'keeps 1e5 inside strings',
        ]);

        $this->assertSame(
            '{"PayableAmount":[{"_":1000000000000000,"currencyID":"MYR"}],"Tiny":[{"_":0.00001}],"Normal":[{"_":106.5}],"Text":"keeps 1e5 inside strings"}',
            $json
        );
        $this->assertEquals(1e15, json_decode($json, true)['PayableAmount'][0]['_']);
    }

    public function test_encode_matches_json_encode_when_no_exponents(): void
    {
        $data = ['a' => [['_' => 0]], 'b' => 'x/y', 'c' => 'é', 'd' => [1.25, true, null]];

        $this->assertSame(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            UblJson::encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_minify_preserves_numbers_and_escapes_exactly(): void
    {
        $pretty = "{\n  \"Amount\" : [ { \"_\" : 12345678901234567890123.45 } ],\n  \"Text\": \"a  b \\\" c \\/ \\u00e9\",\n  \"Zero\": 1.50\n}";

        $this->assertSame(
            '{"Amount":[{"_":12345678901234567890123.45}],"Text":"a  b \\" c \\/ \\u00e9","Zero":1.50}',
            UblJson::minify($pretty)
        );
    }

    public function test_minify_handles_large_documents(): void
    {
        $json = json_encode(['k' => str_repeat('A', 400000), 'n' => 1]);

        $this->assertSame($json, UblJson::minify((string) $json));
    }

    public function test_decode_preserving_numbers_keeps_literal_text(): void
    {
        $decoded = UblJson::decodePreservingNumbers('{"a":[{"_":1e15}],"b":100.50,"c":-3,"s":"1e5","t":true,"n":null}');

        $this->assertSame(['a' => [['_' => '1e15']], 'b' => '100.50', 'c' => '-3', 's' => '1e5', 't' => true, 'n' => null], $decoded);
    }

    public function test_decode_preserving_numbers_returns_null_for_invalid_json(): void
    {
        $this->assertNull(UblJson::decodePreservingNumbers('{not json'));
        $this->assertNull(UblJson::decodePreservingNumbers('42'));
    }
}
