<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating and handling MyInvois e-invoice type codes.
 */
class EInvoiceTypeValidator
{
    /** @var array Mapping of e-invoice type codes to descriptions */
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

    /** @var array Regular (non-self-billed) invoice types */
    private const REGULAR_TYPES = ['01', '02', '03', '04'];

    /** @var array Self-billed types */
    private const SELF_BILLED_TYPES = ['11', '12', '13', '14'];

    /** @var array Types that can modify original invoices */
    private const MODIFYING_TYPES = ['02', '03', '04', '12', '13', '14'];

    /** @var array Types requiring original invoice reference */
    private const REQUIRES_ORIGINAL = ['02', '03', '04', '12', '13', '14'];

    /** @var array Types allowed to have positive amounts */
    private const POSITIVE_AMOUNT_TYPES = ['01', '03', '11', '13'];

    /** @var array Types requiring tax details */
    private const REQUIRES_TAX_DETAILS = ['01', '03', '11', '13'];

    /**
     * Validate an e-invoice type code.
     *
     * @param string $code The e-invoice type code to validate
     * @return bool True if valid
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (!isset(self::INVOICE_TYPES[$code])) {
            throw new ValidationException(
                'Invalid e-invoice type code',
                ['type' => [
                    'Code must be one of: ' . implode(', ', array_keys(self::INVOICE_TYPES)),
                ]]
            );
        }

        return true;
    }

    /**
     * Get the description for an e-invoice type code.
     *
     * @param string $code The e-invoice type code
     * @return string|null The description or null if not found
     */
    public function getDescription(string $code): ?string
    {
        $code = $this->normalizeCode($code);
        return self::INVOICE_TYPES[$code] ?? null;
    }

    /**
     * Check if an e-invoice type is self-billed.
     *
     * @param string $code The e-invoice type code
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
     * Check if an e-invoice type is a regular (non-self-billed) type.
     *
     * @param string $code The e-invoice type code
     * @return bool True if regular type
     * @throws ValidationException If the code is invalid
     */
    public function isRegularType(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::REGULAR_TYPES, true);
    }

    /**
     * Check if an e-invoice type modifies an original invoice.
     *
     * @param string $code The e-invoice type code
     * @return bool True if modifying type
     * @throws ValidationException If the code is invalid
     */
    public function isModifyingType(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::MODIFYING_TYPES, true);
    }

    /**
     * Check if an e-invoice type requires original invoice reference.
     *
     * @param string $code The e-invoice type code
     * @return bool True if original invoice reference required
     * @throws ValidationException If the code is invalid
     */
    public function requiresOriginalInvoice(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::REQUIRES_ORIGINAL, true);
    }

    /**
     * Check if an e-invoice type allows positive amounts.
     *
     * @param string $code The e-invoice type code
     * @return bool True if positive amounts allowed
     * @throws ValidationException If the code is invalid
     */
    public function allowsPositiveAmounts(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::POSITIVE_AMOUNT_TYPES, true);
    }

    /**
     * Check if an e-invoice type requires tax details.
     *
     * @param string $code The e-invoice type code
     * @return bool True if tax details required
     * @throws ValidationException If the code is invalid
     */
    public function requiresTaxDetails(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);
        return in_array($code, self::REQUIRES_TAX_DETAILS, true);
    }

    /**
     * Get validation rules for an e-invoice type.
     *
     * @param string $code The e-invoice type code
     * @return array Validation rules and requirements
     * @throws ValidationException If the code is invalid
     */
    public function getValidationRules(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return [
            'is_self_billed' => $this->isSelfBilled($code),
            'is_regular_type' => $this->isRegularType($code),
            'is_modifying_type' => $this->isModifyingType($code),
            'requires_original_invoice' => $this->requiresOriginalInvoice($code),
            'allows_positive_amounts' => $this->allowsPositiveAmounts($code),
            'requires_tax_details' => $this->requiresTaxDetails($code),
            'required_fields' => $this->getRequiredFields($code),
            'amount_validation' => $this->getAmountValidationRules($code),
        ];
    }

    /**
     * Get required fields for an e-invoice type.
     *
     * @param string $code The e-invoice type code
     * @return array List of required fields
     * @throws ValidationException If the code is invalid
     */
    public function getRequiredFields(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        $fields = [
            'issueDate',
            'invoiceNumber',
            'sellerInfo',
            'buyerInfo',
            'totalAmount',
            'currencyCode',
        ];

        if ($this->requiresOriginalInvoice($code)) {
            $fields[] = 'originalInvoiceNumber';
            $fields[] = 'originalInvoiceDate';
            $fields[] = 'adjustmentReason';
        }

        if ($this->requiresTaxDetails($code)) {
            $fields[] = 'taxableAmount';
            $fields[] = 'taxAmount';
            $fields[] = 'taxCode';
        }

        if ($this->isSelfBilled($code)) {
            $fields[] = 'sellerReference';
            $fields[] = 'buyerReference';
        }

        return $fields;
    }

    /**
     * Get amount validation rules for an e-invoice type.
     *
     * @param string $code The e-invoice type code
     * @return array Amount validation rules
     * @throws ValidationException If the code is invalid
     */
    public function getAmountValidationRules(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return [
            'allows_positive' => $this->allowsPositiveAmounts($code),
            'allows_zero' => in_array($code, ['02', '03', '12', '13'], true),
            'requires_opposite_sign' => $this->requiresOriginalInvoice($code),
            'requires_tax_calculation' => $this->requiresTaxDetails($code),
        ];
    }

    /**
     * Format an e-invoice type code.
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
     * Get all valid e-invoice types.
     *
     * @return array<string, string> Array of e-invoice type codes and descriptions
     */
    public static function getInvoiceTypes(): array
    {
        return self::INVOICE_TYPES;
    }

    /**
     * Get all self-billed e-invoice types.
     *
     * @return array<string, string> Array of self-billed e-invoice types
     */
    public static function getSelfBilledTypes(): array
    {
        return array_intersect_key(
            self::INVOICE_TYPES,
            array_flip(self::SELF_BILLED_TYPES)
        );
    }

    /**
     * Get all regular (non-self-billed) e-invoice types.
     *
     * @return array<string, string> Array of regular e-invoice types
     */
    public static function getRegularTypes(): array
    {
        return array_intersect_key(
            self::INVOICE_TYPES,
            array_flip(self::REGULAR_TYPES)
        );
    }

    /**
     * Get all modifying e-invoice types.
     *
     * @return array<string, string> Array of modifying e-invoice types
     */
    public static function getModifyingTypes(): array
    {
        return array_intersect_key(
            self::INVOICE_TYPES,
            array_flip(self::MODIFYING_TYPES)
        );
    }

    /**
     * Normalize an e-invoice type code.
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
}
