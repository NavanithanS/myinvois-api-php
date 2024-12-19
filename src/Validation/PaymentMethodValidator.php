<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating and handling MyInvois payment method codes.
 */
class PaymentMethodValidator
{
    /** @var array Mapping of payment method codes to descriptions */
    private const PAYMENT_METHODS = [
        '01' => 'Cash',
        '02' => 'Cheque',
        '03' => 'Bank Transfer',
        '04' => 'Credit Card',
        '05' => 'Debit Card',
        '06' => 'e-Wallet / Digital Wallet',
        '07' => 'Digital Bank',
        '08' => 'Others',
    ];

    /** @var array Digital payment methods */
    private const DIGITAL_METHODS = [
        '03', // Bank Transfer
        '04', // Credit Card
        '05', // Debit Card
        '06', // e-Wallet
        '07', // Digital Bank
    ];

    /** @var array Cash-equivalent methods */
    private const CASH_EQUIVALENT_METHODS = [
        '01', // Cash
        '02', // Cheque
    ];

    /**
     * Validate a payment method code.
     *
     * @param  string  $code  The payment method code to validate
     * @return bool True if valid
     *
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (! isset(self::PAYMENT_METHODS[$code])) {
            throw new ValidationException(
                'Invalid payment method code',
                ['payment_method' => [
                    'Code must be one of: '.implode(', ', array_keys(self::PAYMENT_METHODS)),
                ]]
            );
        }

        return true;
    }

    /**
     * Get the description for a payment method code.
     *
     * @param  string  $code  The payment method code
     * @return string|null The description or null if not found
     */
    public function getDescription(string $code): ?string
    {
        $code = $this->normalizeCode($code);

        return self::PAYMENT_METHODS[$code] ?? null;
    }

    /**
     * Get all valid payment methods.
     *
     * @return array<string, string> Array of codes and descriptions
     */
    public static function getMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    /**
     * Check if a payment method is digital.
     *
     * @param  string  $code  The payment method code
     * @return bool True if digital
     *
     * @throws ValidationException If the code is invalid
     */
    public function isDigital(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return in_array($code, self::DIGITAL_METHODS, true);
    }

    /**
     * Check if a payment method is a cash equivalent.
     *
     * @param  string  $code  The payment method code
     * @return bool True if cash equivalent
     *
     * @throws ValidationException If the code is invalid
     */
    public function isCashEquivalent(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return in_array($code, self::CASH_EQUIVALENT_METHODS, true);
    }

    /**
     * Get all digital payment methods.
     *
     * @return array<string, string> Array of digital payment methods
     */
    public static function getDigitalMethods(): array
    {
        return array_intersect_key(
            self::PAYMENT_METHODS,
            array_flip(self::DIGITAL_METHODS)
        );
    }

    /**
     * Get all cash equivalent payment methods.
     *
     * @return array<string, string> Array of cash equivalent payment methods
     */
    public static function getCashEquivalentMethods(): array
    {
        return array_intersect_key(
            self::PAYMENT_METHODS,
            array_flip(self::CASH_EQUIVALENT_METHODS)
        );
    }

    /**
     * Format a payment method code with leading zero if needed.
     *
     * @param  string  $code  The code to format
     * @return string The formatted code
     *
     * @throws ValidationException If the code is invalid
     */
    public function format(string $code): string
    {
        $normalized = $this->normalizeCode($code);
        $this->validate($normalized);

        return $normalized;
    }

    /**
     * Parse a payment method code, accepting various formats.
     *
     * @param  string|int  $code  The code to parse
     * @return string The normalized code
     *
     * @throws ValidationException If the code is invalid
     */
    public function parse($code): string
    {
        if (is_int($code)) {
            $code = (string) $code;
        }

        $normalized = $this->normalizeCode($code);
        $this->validate($normalized);

        return $normalized;
    }

    /**
     * Check if a payment method requires additional information.
     *
     * For example, credit card payments might require last 4 digits,
     * bank transfers might require reference numbers, etc.
     *
     * @param  string  $code  The payment method code
     * @return bool True if additional info is required
     *
     * @throws ValidationException If the code is invalid
     */
    public function requiresAdditionalInfo(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        // Methods requiring additional reference information
        $requiresInfo = [
            '02', // Cheque (number)
            '03', // Bank Transfer (reference)
            '04', // Credit Card (last 4 digits)
            '05', // Debit Card (last 4 digits)
            '06', // e-Wallet (reference)
            '07', // Digital Bank (reference)
        ];

        return in_array($code, $requiresInfo, true);
    }

    /**
     * Get validation rules for additional information based on payment method.
     *
     * @param  string  $code  The payment method code
     * @return array Validation rules and descriptions
     *
     * @throws ValidationException If the code is invalid
     */
    public function getAdditionalInfoRules(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        $rules = [
            '02' => [ // Cheque
                'pattern' => '/^[A-Za-z0-9-]{1,20}$/',
                'description' => 'Cheque number (up to 20 alphanumeric characters)',
                'required' => true,
            ],
            '03' => [ // Bank Transfer
                'pattern' => '/^[A-Za-z0-9-]{1,50}$/',
                'description' => 'Bank reference number (up to 50 alphanumeric characters)',
                'required' => true,
            ],
            '04' => [ // Credit Card
                'pattern' => '/^\d{4}$/',
                'description' => 'Last 4 digits of credit card',
                'required' => true,
            ],
            '05' => [ // Debit Card
                'pattern' => '/^\d{4}$/',
                'description' => 'Last 4 digits of debit card',
                'required' => true,
            ],
            '06' => [ // e-Wallet
                'pattern' => '/^[A-Za-z0-9-]{1,50}$/',
                'description' => 'e-Wallet transaction reference',
                'required' => true,
            ],
            '07' => [ // Digital Bank
                'pattern' => '/^[A-Za-z0-9-]{1,50}$/',
                'description' => 'Digital bank transaction reference',
                'required' => true,
            ],
            '08' => [ // Others
                'pattern' => '/^[A-Za-z0-9\s-]{1,100}$/',
                'description' => 'Payment reference or description',
                'required' => false,
            ],
        ];

        return $rules[$code] ?? [
            'pattern' => null,
            'description' => null,
            'required' => false,
        ];
    }

    /**
     * Normalize a payment method code.
     *
     * @param  string  $code  The code to normalize
     * @return string The normalized code
     */
    private function normalizeCode(string $code): string
    {
        // Remove any whitespace
        $code = trim($code);

        // Ensure 2-digit format with leading zero
        return str_pad($code, 2, '0', STR_PAD_LEFT);
    }
}

try {
    $result = $this->client->validateTaxpayerTin('C1234567890', 'NRIC', '770625015324');
} catch (ValidationException $e) {
    // Handle validation errors
}
