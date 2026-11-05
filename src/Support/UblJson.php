<?php

namespace Nava\MyInvois\Support;

/**
 * JSON helpers for UBL documents that preserve numbers exactly.
 *
 * LHDN rejects amounts written in scientific notation (since 30 Apr 2026) and
 * amounts longer than 26 digits (since 24 Oct 2026). PHP's json_encode() writes
 * floats >= 1e15 as "1.0e+15", and a json_decode()/json_encode() round trip
 * loses digits, so documents must be encoded and minified without either.
 */
final class UblJson
{
    /**
     * Matches one JSON string literal or one JSON number literal.
     */
    private const TOKEN_PATTERN = '/"(?:[^"\\\\]++|\\\\.)*+"|-?(?:0|[1-9]\d*+)(?:\.\d++)?(?:[eE][+-]?\d++)?/';

    private const STRING_OR_WHITESPACE_PATTERN = '/"(?:[^"\\\\]++|\\\\.)*+"|\s++/';

    /**
     * Encode a document with json_encode(), rewriting any exponent-form number
     * as a plain decimal (e.g. 1.0e+15 → 1000000000000000).
     *
     * @param  mixed  $value
     *
     * @throws \JsonException
     */
    public static function encode($value, int $flags = 0): string
    {
        $json = json_encode($value, $flags | JSON_THROW_ON_ERROR);

        return self::mapNumbers($json, static function (string $number): string {
            return self::expandExponent($number);
        });
    }

    /**
     * Remove insignificant whitespace without decoding, so numbers, escapes and
     * key order stay exactly as the caller wrote them.
     */
    public static function minify(string $json): string
    {
        return self::replace(self::STRING_OR_WHITESPACE_PATTERN, static function (array $m): string {
            return $m[0][0] === '"' ? $m[0] : '';
        }, $json);
    }

    /**
     * Decode JSON with every number literal turned into a string holding its
     * original text (e.g. 1e15 → "1e15", 100.50 → "100.50"), so validators can
     * see exactly how each number was written.
     *
     * @return array<mixed>|null Null when the input is not a JSON object/array
     */
    public static function decodePreservingNumbers(string $json): ?array
    {
        $quoted = self::mapNumbers($json, static function (string $number): string {
            return '"' . $number . '"';
        });

        $decoded = json_decode($quoted, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Convert a JSON number literal in exponent form to a plain decimal string.
     * Other literals are returned unchanged.
     */
    public static function expandExponent(string $number): string
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $number, $m)) {
            return $number;
        }

        [, $sign, $int, $frac, $exp] = $m + [3 => '', 4 => '0'];
        $digits = $int . $frac;
        $point = strlen($int) + (int) $exp;

        if ($point <= 0) {
            $result = '0.' . str_repeat('0', -$point) . $digits;
        } elseif ($point >= strlen($digits)) {
            $result = $digits . str_repeat('0', $point - strlen($digits));
        } else {
            $result = substr($digits, 0, $point) . '.' . substr($digits, $point);
        }

        if (str_contains($result, '.')) {
            $result = rtrim(rtrim($result, '0'), '.');
        }
        $result = ltrim($result, '0');
        if ($result === '' || $result[0] === '.') {
            $result = '0' . $result;
        }

        return ($result === '0' ? '' : $sign) . $result;
    }

    /**
     * Apply $callback to every number literal outside string literals.
     *
     * @param  callable(string): string  $callback
     */
    private static function mapNumbers(string $json, callable $callback): string
    {
        return self::replace(self::TOKEN_PATTERN, static function (array $m) use ($callback): string {
            return $m[0][0] === '"' ? $m[0] : $callback($m[0]);
        }, $json);
    }

    /**
     * @param  callable(array<int|string, string>): string  $callback
     *
     * @throws \RuntimeException If PCRE fails (never return a truncated document)
     */
    private static function replace(string $pattern, callable $callback, string $subject): string
    {
        $result = preg_replace_callback($pattern, $callback, $subject);
        if ($result === null) {
            throw new \RuntimeException('Failed to process JSON document: ' . preg_last_error_msg());
        }

        return $result;
    }
}
