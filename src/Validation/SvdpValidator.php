<?php

namespace Nava\MyInvois\Validation;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Nava\MyInvois\Config;
use Nava\MyInvois\Exception\ValidationException;

/**
 * Guards for the e-Invoice Voluntary Disclosure Programme (SVDP).
 *
 * SDK release note 8 Jul 2026: versions 1.2 (no digital signature) and 1.3
 * (with digital signature) may only be used until 31 Dec 2027, and allow the
 * issue date to be backdated to the phased mandatory implementation date
 * (e.g. 1 Aug 2024 onwards).
 */
class SvdpValidator
{
    /**
     * Check the SVDP rules for one UBL JSON document (decoded array).
     *
     * - 1.2/1.3 are rejected once the programme has ended.
     * - 1.2 must not carry UBLExtensions/Signature; 1.3 must carry both.
     *
     * Non-SVDP versions and documents without an Invoice root are ignored.
     *
     * @param  array<mixed>  $document
     * @return array<string, array<int, string>> field path => messages
     */
    public function getViolations(array $document, DateTimeInterface $now, string $pathPrefix = ''): array
    {
        $errors = [];
        if (! isset($document['Invoice']) || ! is_array($document['Invoice'])) {
            return $errors;
        }

        foreach ($document['Invoice'] as $index => $invoice) {
            if (! is_array($invoice)) {
                continue;
            }
            $version = $invoice['InvoiceTypeCode'][0]['listVersionID'] ?? null;
            if (! is_string($version) || ! Config::isSvdpVersion($version)) {
                continue;
            }

            $path = $pathPrefix . 'Invoice' . ((int) $index === 0 ? '' : "[{$index}]") . '.InvoiceTypeCode@listVersionID';

            if (! Config::isSvdpActive($now)) {
                $errors[$path][] = sprintf('SVDP version %s can only be used until %s', $version, Config::SVDP_END_DATE);
            }

            $signed = isset($invoice['UBLExtensions']) || isset($invoice['Signature']);
            $fullySigned = isset($invoice['UBLExtensions']) && isset($invoice['Signature']);
            if ($version === Config::SVDP_UNSIGNED_VERSION && $signed) {
                $errors[$path][] = 'SVDP version 1.2 is for documents without a digital signature; use 1.3 for signed documents';
            } elseif ($version === Config::SVDP_SIGNED_VERSION && ! $fullySigned) {
                $errors[$path][] = 'SVDP version 1.3 requires a digital signature (UBLExtensions and Signature); use 1.2 for unsigned documents';
            }
        }

        return $errors;
    }

    /**
     * Validate a backdated SVDP issue date/time and return it normalised to
     * ['YYYY-MM-DD', 'HH:MM:SSZ'] (UTC).
     *
     * @param  string|null  $time  HH:MM:SS with optional trailing Z; defaults to $now's UTC time
     *
     * @throws ValidationException
     */
    public function resolveIssueDateTime(string $date, ?string $time, DateTimeInterface $now): array
    {
        $utcNow = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));
        $errors = [];

        if (! Config::isSvdpActive($now)) {
            $errors['issueDate'][] = sprintf('The SVDP programme ended on %s; backdated issue dates are no longer accepted', Config::SVDP_END_DATE);
        }

        $date = trim($date);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $errors['issueDate'][] = 'issueDate must be a real date in YYYY-MM-DD format';
        }

        $time = $time === null || trim($time) === '' ? $utcNow->format('H:i:s') : rtrim(trim($time), 'Zz');
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time)) {
            $errors['issueTime'][] = 'issueTime must be HH:MM:SS (UTC), optionally followed by Z';
        }

        if ($errors === []) {
            if ($date < Config::SVDP_EARLIEST_ISSUE_DATE) {
                $errors['issueDate'][] = sprintf('SVDP issue dates cannot be earlier than %s', Config::SVDP_EARLIEST_ISSUE_DATE);
            } elseif ($date . 'T' . $time > $utcNow->format('Y-m-d\TH:i:s')) {
                $errors['issueDate'][] = 'issueDate/issueTime cannot be in the future';
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Invalid SVDP issue date', $errors, 422);
        }

        return [$date, $time . 'Z'];
    }
}
