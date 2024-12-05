<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating and handling MyInvois tax type codes.
 */
class TaxTypeValidator
{
    /** @var array Mapping of tax type codes to descriptions */
    private const TAX_TYPES = [
        '01' => 'Sales Tax',
        '02' => 'Service Tax',
        '03' => 'Tourism Tax',
        '04' => 'High-Value Goods Tax',
        '05' => 'Sales Tax on Low Value Goods',
        '06' => 'Not Applicable',
        'E' => 'Tax exemption',
    ];

    /** @var array Current standard tax rates (in percentage) */
    private const STANDARD_RATES = [
        '01' => 10.0, // Sales Tax
        '02' => 6.0, // Service Tax
        '03' => 10.0, // Tourism Tax
        '04' => 10.0, // High-Value Goods Tax
        '05' => 10.0, // Sales Tax on Low Value Goods
        '06' => 0.0, // Not Applicable
        'E' => 0.0, // Tax exemption
    ];

    /** @var array Tax types that can have exemptions */
    private const EXEMPTIBLE_TAXES = [
        '01', // Sales Tax
        '02', // Service Tax
        '03', // Tourism Tax
    ];

    /** @var array Tax types requiring registration numbers */
    private const REQUIRES_REGISTRATION = [
        '01', // Sales Tax
        '02', // Service Tax
        '04', // High-Value Goods Tax
    ];

    /**
     * Validate a tax type code.
     *
     * @param string $code The tax type code to validate
     * @return bool True if valid
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (!isset(self::TAX_TYPES[$code])) {
            throw new ValidationException(
                'Invalid tax type code',
                ['tax_type' => [
                    'Code must be one of: ' . implode(', ', array_keys(self::TAX_TYPES)),
                ]]
            );
        }

        return true;
    }

    /**
     * Get the description for a tax type code.
     *
     * @param string $code The tax type code
     * @return string|null The description or null if not found
     */
    public function getDescription(string $code): ?string
    {
        $code = $this->normalizeCode($code);
        return self::TAX_TYPES[$code] ?? null;
    }

    /**
     * Get the standard rate for a tax type.
     *
     * @param string $code The tax type code
     * @return float The standard rate as a percentage
     * @throws ValidationException If the code is invalid
     */
    public function getStandardRate(string $code): float
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return self::STANDARD_RATES[$code];
    }

    /**
     * Check if a tax type can have exemptions.
     *
     * @param string $code The tax type code
     * @return bool True if the tax type can have exemptions
     * @throws ValidationException If the code is invalid
     */
    public function canHaveExemption(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::EXEMPTIBLE_TAXES, true);
    }

    /**
     * Check if a tax type requires registration number.
     *
     * @param string $code The tax type code
     * @return bool True if registration number is required
     * @throws ValidationException If the code is invalid
     */
    public function requiresRegistration(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::REQUIRES_REGISTRATION, true);
    }

    /**
     * Validate a registration number format.
     *
     * @param string $code The tax type code
     * @param string $registrationNumber The registration number to validate
     * @return bool True if valid
     * @throws ValidationException If the code or registration number is invalid
     */
    public function validateRegistrationNumber(string $code, string $registrationNumber): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        if (!$this->requiresRegistration($code)) {
            return true; // Registration not required for this tax type
        }

        // Validate registration number format based on tax type
        $pattern = match ($code) {
            '01' => '/^[A-Z]{1,2}\d{10}$/', // Sales Tax
            '02' => '/^[A-Z]{1,2}\d{10}$/', // Service Tax
            '04' => '/^[A-Z]{1,2}[0-9A-Z]{8,10}$/', // High-Value Goods Tax
            default => '/.+/'
        };

        if (!preg_match($pattern, $registrationNumber)) {
            throw new ValidationException(
                'Invalid registration number format',
                ['registration' => ['Registration number format is invalid for the specified tax type']]
            );
        }

        return true;
    }

    /**
     * Calculate tax amount.
     *
     * @param string $code The tax type code
     * @param float $baseAmount The base amount to calculate tax on
     * @param float|null $rate Optional custom rate (if null, uses standard rate)
     * @return float The calculated tax amount
     * @throws ValidationException If the code is invalid or rate is invalid
     */
    public function calculateTax(string $code, float $baseAmount, ?float $rate = null): float
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        // Use provided rate or fall back to standard rate
        $taxRate = $rate ?? $this->getStandardRate($code);

        // Validate rate
        if ($taxRate < 0 || $taxRate > 100) {
            throw new ValidationException(
                'Invalid tax rate',
                ['rate' => ['Tax rate must be between 0 and 100']]
            );
        }

        // Handle exemption
        if ('E' === $code || '06' === $code) {
            return 0.0;
        }

        // Calculate tax amount: base amount * (rate / 100)
        return round($baseAmount * ($taxRate / 100), 2);
    }

    /**
     * Format a tax type code.
     *
     * @param string $code The code to format
     * @return string The formatted code
     * @throws ValidationException If the code is invalid
     */
    public function format(string $code): string
    {
        $normalized = $this->normalizeCode($code);
        $this->validate($normalized);
        return $normalized;
    }

    /**
     * Parse a tax type identifier into a valid tax type code.
     *
     * @param string|int $identifier The tax type identifier to parse
     * @return string The normalized tax type code
     * @throws ValidationException If the identifier is invalid
     */
    public function parse($identifier): string
    {
        if (is_int($identifier)) {
            $identifier = (string) $identifier;
        }

        $normalized = $this->normalizeCode($identifier);
        $this->validate($normalized);
        return $normalized;
    }

    /**
     * Get all valid tax types.
     *
     * @return array<string, string> Array of tax type codes and descriptions
     */
    public static function getTaxTypes(): array
    {
        return self::TAX_TYPES;
    }

    /**
     * Get all tax types that can have exemptions.
     *
     * @return array<string, string> Array of exemptible tax types
     */
    public static function getExemptibleTaxTypes(): array
    {
        return array_intersect_key(
            self::TAX_TYPES,
            array_flip(self::EXEMPTIBLE_TAXES)
        );
    }

    /**
     * Get all tax types requiring registration.
     *
     * @return array<string, string> Array of tax types requiring registration
     */
    public static function getRegistrationRequiredTypes(): array
    {
        return array_intersect_key(
            self::TAX_TYPES,
            array_flip(self::REQUIRES_REGISTRATION)
        );
    }

    /**
     * Get validation rules for a specific tax type.
     *
     * @param string $code The tax type code
     * @return array Validation rules and requirements
     * @throws ValidationException If the code is invalid
     */
    public function getValidationRules(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return [
            'requires_registration' => $this->requiresRegistration($code),
            'can_have_exemption' => $this->canHaveExemption($code),
            'standard_rate' => $this->getStandardRate($code),
            'registration_format' => match ($code) {
                '01', '02' => '/^[A-Z]{1,2}\d{10}$/',
                '04' => '/^[A-Z]{1,2}[0-9A-Z]{8,10}$/',
                default => null
            },
            'allows_custom_rate' => in_array($code, ['01', '02', '03', '04'], true),
            'requires_supporting_docs' => in_array($code, ['E'], true),
        ];
    }

    /**
     * Normalize a tax type code.
     *
     * @param string $code The code to normalize
     * @return string The normalized code
     */
    private function normalizeCode(string $code): string
    {
        // Remove any whitespace
        $code = trim($code);

        // Handle exemption code specially
        if (strtoupper($code) === 'E') {
            return 'E';
        }

        // Ensure 2-digit format with leading zero for numeric codes
        return str_pad($code, 2, '0', STR_PAD_LEFT);
    }
}
