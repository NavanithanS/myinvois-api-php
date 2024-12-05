<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating Malaysia Standard Industrial Classification (MSIC) codes.
 */
class MsicValidator
{
    /** @var array Mapping of section codes to descriptions */
    private const SECTION_MAPPING = [
        'A' => 'AGRICULTURE, FORESTRY AND FISHING',
        'B' => 'MINING AND QUARRYING',
        'C' => 'MANUFACTURING',
        'D' => 'ELECTRICITY, GAS, STEAM AND AIR CONDITIONING SUPPLY',
        'E' => 'WATER SUPPLY; SEWERAGE, WASTE MANAGEMENT AND REMEDIATION ACTIVITIES',
        'F' => 'CONSTRUCTION',
        'G' => 'WHOLESALE AND RETAIL TRADE, REPAIR OF MOTOR VEHICLES AND MOTORCYCLES',
        'H' => 'TRANSPORTATION AND STORAGE',
        'I' => 'ACCOMODATION AND FOOD SERVICE ACTIVITIES',
        'J' => 'INFORMATION AND COMMUNICATION',
        'K' => 'FINANCIAL AND INSURANCE/TAKAFUL ACTIVITIES',
        'L' => 'REAL ESTATE ACTIVITIES',
        'M' => 'PROFESSIONAL, SCIENTIFIC AND TECHNICAL ACTIVITIES',
        'N' => 'ADMINISTRATIVE AND SUPPORT SERVICE ACTIVITIES',
        'O' => 'PUBLIC ADMINISTRATION AND DEFENCE, COMPULSORY SOCIAL ACTIVITIES',
        'P' => 'EDUCATION',
        'Q' => 'HUMAN HEALTH AND SOCIAL WORK ACTIVITIES',
        'R' => 'ARTS, ENTERTAINMENT AND RECREATION',
        'S' => 'OTHER SERVICE ACTIVITIES',
        'T' => 'ACTIVITIES OF HOUSEHOLDS AS EMPLOYERS',
        'U' => 'ACTIVITIES OF EXTRATERRITORIAL ORGANIZATIONS AND BODIES',
    ];

    /** @var string Special code for "Not Applicable" */
    private const NOT_APPLICABLE = '00000';

    /**
     * Validate a MSIC code.
     *
     * @param string $code The MSIC code to validate
     * @throws ValidationException If the code is invalid
     * @return bool True if valid
     */
    public function validate(string $code): bool
    {
        // Normalize input
        $code = trim($code);

        // Check special case for "Not Applicable"
        if (self::NOT_APPLICABLE === $code) {
            return true;
        }

        // Basic format validation
        if (!preg_match('/^\d{5}$/', $code)) {
            throw new ValidationException(
                'Invalid MSIC code format',
                ['msic' => ['MSIC code must be exactly 5 digits']]
            );
        }

        // Validate numeric range (00000-99999)
        $numericValue = (int) $code;
        if ($numericValue < 0 || $numericValue > 99999) {
            throw new ValidationException(
                'Invalid MSIC code range',
                ['msic' => ['MSIC code must be between 00000 and 99999']]
            );
        }

        return true;
    }

    /**
     * Get the section letter for a given MSIC code.
     *
     * @param string $code The MSIC code
     * @return string|null The section letter or null if not found
     * @throws ValidationException If the code format is invalid
     */
    public function getSection(string $code): ?string
    {
        $this->validate($code);

        if (self::NOT_APPLICABLE === $code) {
            return null;
        }

        // Map numeric ranges to sections
        $numericValue = (int) $code;

        // Define section ranges
        $sectionRanges = [
            'A' => ['start' => 1111, 'end' => 3229],
            'B' => ['start' => 5100, 'end' => 9900],
            'C' => ['start' => 10101, 'end' => 33200],
            'D' => ['start' => 35101, 'end' => 35303],
            'E' => ['start' => 36001, 'end' => 39000],
            'F' => ['start' => 41001, 'end' => 43909],
            'G' => ['start' => 45101, 'end' => 47999],
            'H' => ['start' => 49110, 'end' => 53200],
            'I' => ['start' => 55101, 'end' => 56309],
            'J' => ['start' => 58110, 'end' => 63990],
            'K' => ['start' => 64110, 'end' => 66303],
            'L' => ['start' => 68101, 'end' => 68209],
            'M' => ['start' => 69100, 'end' => 75000],
            'N' => ['start' => 77101, 'end' => 82990],
            'O' => ['start' => 84111, 'end' => 84300],
            'P' => ['start' => 85101, 'end' => 85500],
            'Q' => ['start' => 86101, 'end' => 88909],
            'R' => ['start' => 90001, 'end' => 93299],
            'S' => ['start' => 94110, 'end' => 96099],
            'T' => ['start' => 97000, 'end' => 98200],
            'U' => ['start' => 99000, 'end' => 99000],
        ];

        foreach ($sectionRanges as $section => $range) {
            if ($numericValue >= $range['start'] && $numericValue <= $range['end']) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Get the description for a section letter.
     *
     * @param string $section The section letter
     * @return string|null The section description or null if not found
     */
    public function getSectionDescription(string $section): ?string
    {
        return self::SECTION_MAPPING[strtoupper($section)] ?? null;
    }

    /**
     * Format a MSIC code with proper section prefix.
     *
     * @param string $code The MSIC code to format
     * @return string The formatted code
     * @throws ValidationException If the code is invalid
     */
    public function format(string $code): string
    {
        $this->validate($code);

        if (self::NOT_APPLICABLE === $code) {
            return $code;
        }

        $section = $this->getSection($code);
        if (null === $section) {
            return $code;
        }

        return sprintf('%s.%s', $section, $code);
    }

    /**
     * Parse a formatted MSIC code back to its numeric form.
     *
     * @param string $formattedCode The formatted MSIC code
     * @return string The numeric code
     * @throws ValidationException If the formatted code is invalid
     */
    public function parse(string $formattedCode): string
    {
        // Check special case
        if (self::NOT_APPLICABLE === $formattedCode) {
            return $formattedCode;
        }

        // Check if code is already in numeric form
        if (preg_match('/^\d{5}$/', $formattedCode)) {
            $this->validate($formattedCode);
            return $formattedCode;
        }

        // Parse formatted code (e.g., "C.10101")
        if (!preg_match('/^([A-U])\.\d{5}$/', $formattedCode, $matches)) {
            throw new ValidationException(
                'Invalid formatted MSIC code',
                ['msic' => ['Formatted MSIC code must be in the form "X.00000" where X is a section letter']]
            );
        }

        $numericPart = substr($formattedCode, 2);
        $this->validate($numericPart);

        // Verify section matches numeric range
        $expectedSection = $this->getSection($numericPart);
        if ($expectedSection !== $matches[1]) {
            throw new ValidationException(
                'MSIC code section mismatch',
                ['msic' => ['Section letter does not match numeric code range']]
            );
        }

        return $numericPart;
    }

    /**
     * Check if a code belongs to a specific section.
     *
     * @param string $code The MSIC code to check
     * @param string $section The section letter to check against
     * @return bool True if the code belongs to the section
     * @throws ValidationException If either code or section is invalid
     */
    public function isInSection(string $code, string $section): bool
    {
        $section = strtoupper($section);
        if (!isset(self::SECTION_MAPPING[$section])) {
            throw new ValidationException(
                'Invalid section letter',
                ['section' => ['Invalid MSIC section letter']]
            );
        }

        return $this->getSection($code) === $section;
    }

    /**
     * Get all valid MSIC sections.
     *
     * @return array<string, string> Array of section letters and descriptions
     */
    public static function getSections(): array
    {
        return self::SECTION_MAPPING;
    }
}
