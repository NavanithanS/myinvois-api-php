<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating and handling ISO-4217 currency codes according to MyInvois standards.
 */
class CurrencyValidator
{
    /** @var array Mapping of currency codes to descriptions */
    private const CURRENCIES = [
        'AED' => 'UAE Dirham',
        'AFN' => 'Afghani',
        'ALL' => 'Lek',
        'AMD' => 'Armenian Dram',
        'ANG' => 'Netherlands Antillean Guilder',
        'AOA' => 'Kwanza',
        'ARS' => 'Argentine Peso',
        'AUD' => 'Australian Dollar',
        'AWG' => 'Aruban Florin',
        'AZN' => 'Azerbaijan Manat',
        'BAM' => 'Convertible Mark',
        'BBD' => 'Barbados Dollar',
        'BDT' => 'Taka',
        'BGN' => 'Bulgarian Lev',
        'BHD' => 'Bahraini Dinar',
        'BIF' => 'Burundi Franc',
        'BMD' => 'Bermudian Dollar',
        'BND' => 'Brunei Dollar',
        'BOB' => 'Boliviano',
        'BRL' => 'Brazilian Real',
        'BSD' => 'Bahamian Dollar',
        'BTN' => 'Ngultrum',
        'BWP' => 'Pula',
        'BYN' => 'Belarusian Ruble',
        'BZD' => 'Belize Dollar',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Swiss Franc',
        'CNY' => 'Yuan Renminbi',
        'EUR' => 'Euro',
        'GBP' => 'Pound Sterling',
        'HKD' => 'Hong Kong Dollar',
        'IDR' => 'Rupiah',
        'INR' => 'Indian Rupee',
        'JPY' => 'Yen',
        'KRW' => 'Won',
        'MYR' => 'Malaysian Ringgit',
        'SGD' => 'Singapore Dollar',
        'THB' => 'Baht',
        'USD' => 'US Dollar',
        'VND' => 'Dong',
        // Add more currencies as needed
    ];

    /** @var array Special scale/decimal places by currency */
    private const CURRENCY_SCALES = [
        'BHD' => 3, // 3 decimal places
        'IQD' => 3,
        'JOD' => 3,
        'KWD' => 3,
        'LYD' => 3,
        'OMR' => 3,
        'TND' => 3,
        'JPY' => 0, // No decimals
        'KRW' => 0,
        'VND' => 0,
        'default' => 2, // Default 2 decimal places
    ];

    /**
     * Validate a currency code.
     *
     * @param string $code The currency code to validate
     * @return bool True if valid
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (!isset(self::CURRENCIES[$code])) {
            throw new ValidationException(
                'Invalid currency code',
                ['currency' => ['Code must be a valid ISO-4217 currency code']]
            );
        }

        return true;
    }

    /**
     * Get the description for a currency code.
     *
     * @param string $code The currency code
     * @return string|null The description or null if not found
     */
    public function getDescription(string $code): ?string
    {
        $code = $this->normalizeCode($code);
        return self::CURRENCIES[$code] ?? null;
    }

    /**
     * Get the number of decimal places for a currency.
     *
     * @param string $code The currency code
     * @return int Number of decimal places
     * @throws ValidationException If the code is invalid
     */
    public function getDecimalPlaces(string $code): int
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return self::CURRENCY_SCALES[$code] ?? self::CURRENCY_SCALES['default'];
    }

    /**
     * Format an amount according to currency rules.
     *
     * @param string $code The currency code
     * @param float $amount The amount to format
     * @return string Formatted amount
     * @throws ValidationException If the code is invalid
     */
    public function formatAmount(string $code, float $amount): string
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        $decimals = $this->getDecimalPlaces($code);
        return number_format($amount, $decimals, '.', '');
    }

    /**
     * Get all valid currencies.
     *
     * @return array<string, string> Array of currency codes and descriptions
     */
    public static function getCurrencies(): array
    {
        return self::CURRENCIES;
    }

    /**
     * Normalize a currency code.
     *
     * @param string $code The code to normalize
     * @return string The normalized code
     */
    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}

/**
 * Class for validating and handling MyInvois e-invoice type codes.
 */
class InvoiceTypeValidator
{
    /** @var array Mapping of invoice type codes to descriptions */
    private const INVOICE_TYPES = [
        '01' => 'Invoice',
        '02' => 'Credit Note',
        '03' => 'Debit Note',
        '04' => 'Refund Note',
        '11' => 'Self-billed Invoice',
        '12' => 'Self-billed Credit Note',
        '13' => 'Self-billed Debit Note',
        '14' => 'Self-billed Refund Note',
    ];

    /** @var array Regular (non-self-billed) types */
    private const REGULAR_TYPES = ['01', '02', '03', '04'];

    /** @var array Self-billed types */
    private const SELF_BILLED_TYPES = ['11', '12', '13', '14'];

    /** @var array Adjustment document types */
    private const ADJUSTMENT_TYPES = ['02', '03', '12', '13'];

    /** @var array Refund document types */
    private const REFUND_TYPES = ['04', '14'];

    /**
     * Validate an invoice type code.
     *
     * @param string $code The invoice type code to validate
     * @return bool True if valid
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (!isset(self::INVOICE_TYPES[$code])) {
            throw new ValidationException(
                'Invalid invoice type code',
                ['invoice_type' => [
                    'Code must be one of: ' . implode(', ', array_keys(self::INVOICE_TYPES)),
                ]]
            );
        }

        return true;
    }

    /**
     * Get the description for an invoice type code.
     *
     * @param string $code The invoice type code
     * @return string|null The description or null if not found
     */
    public function getDescription(string $code): ?string
    {
        $code = $this->normalizeCode($code);
        return self::INVOICE_TYPES[$code] ?? null;
    }

    /**
     * Check if an invoice type is self-billed.
     *
     * @param string $code The invoice type code
     * @return bool True if self-billed
     * @throws ValidationException If the code is invalid
     */
    public function isSelfBilled(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::SELF_BILLED_TYPES, true);
    }

    /**
     * Check if an invoice type is an adjustment document.
     *
     * @param string $code The invoice type code
     * @return bool True if adjustment
     * @throws ValidationException If the code is invalid
     */
    public function isAdjustment(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::ADJUSTMENT_TYPES, true);
    }

    /**
     * Check if an invoice type is a refund document.
     *
     * @param string $code The invoice type code
     * @return bool True if refund
     * @throws ValidationException If the code is invalid
     */
    public function isRefund(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::REFUND_TYPES, true);
    }

    /**
     * Get validation rules for an invoice type.
     *
     * @param string $code The invoice type code
     * @return array Validation rules and requirements
     * @throws ValidationException If the code is invalid
     */
    public function getValidationRules(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return [
            'is_self_billed' => $this->isSelfBilled($code),
            'is_adjustment' => $this->isAdjustment($code),
            'is_refund' => $this->isRefund($code),
            'requires_original_invoice' => $this->isAdjustment($code) || $this->isRefund($code),
            'requires_reason' => $this->isAdjustment($code) || $this->isRefund($code),
            'allows_positive_amounts' => !in_array($code, ['02', '12', '04', '14'], true),
            'requires_tax_details' => in_array($code, ['01', '11'], true),
        ];
    }

    /**
     * Format an invoice type code.
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
     * Get all valid invoice types.
     *
     * @return array<string, string> Array of invoice type codes and descriptions
     */
    public static function getInvoiceTypes(): array
    {
        return self::INVOICE_TYPES;
    }

    /**
     * Get all self-billed invoice types.
     *
     * @return array<string, string> Array of self-billed invoice types
     */
    public static function getSelfBilledTypes(): array
    {
        return array_intersect_key(
            self::INVOICE_TYPES,
            array_flip(self::SELF_BILLED_TYPES)
        );
    }

    /**
     * Get all regular (non-self-billed) invoice types.
     *
     * @return array<string, string> Array of regular invoice types
     */
    public static function getRegularTypes(): array
    {
        return array_intersect_key(
            self::INVOICE_TYPES,
            array_flip(self::REGULAR_TYPES)
        );
    }

    /**
     * Normalize an invoice type code.
     *
     * @param string $code The code to normalize
     * @return string The normalized code
     */
    private function normalizeCode(string $code): string
    {
        // Remove any whitespace
        $code = trim($code);

        // Ensure 2-digit format with leading zero
        return str_pad($code, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Get all available currencies.
     *
     * @return array An array of currency codes and descriptions
     */
    public static function getAllCurrencies(): array
    {
        return self::CURRENCIES;
    }
}
