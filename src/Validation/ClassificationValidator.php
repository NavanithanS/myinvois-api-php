<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating and handling MyInvois document classification codes.
 */
class ClassificationValidator
{
    /** @var array Mapping of classification codes to descriptions */
    private const CLASSIFICATIONS = [
        '001' => 'Breastfeeding equipment',
        '002' => 'Child care centres and kindergartens fees',
        '003' => 'Computer, smartphone or tablet',
        '004' => 'Consolidated e-Invoice',
        '005' => 'Construction materials',
        '006' => 'Disbursement',
        '007' => 'Donation',
        '008' => 'e-Commerce - e-Invoice to buyer / purchaser',
        '009' => 'e-Commerce - Self-billed e-Invoice to seller, logistics, etc.',
        '010' => 'Education fees',
        '011' => 'Goods on consignment (Consignor)',
        '012' => 'Goods on consignment (Consignee)',
        '013' => 'Gym membership',
        '014' => 'Insurance - Education and medical benefits',
        '015' => 'Insurance - Takaful or life insurance',
        '016' => 'Interest and financing expenses',
        '017' => 'Internet subscription',
        '018' => 'Land and building',
        '019' => 'Medical examination for learning disabilities and early intervention or rehabilitation treatments',
        '020' => 'Medical examination or vaccination expenses',
        '021' => 'Medical expenses for serious diseases',
        '022' => 'Others',
        '023' => 'Petroleum operations',
        '024' => 'Private retirement scheme or deferred annuity scheme',
        '025' => 'Motor vehicle',
        '026' => 'Subscription of books / journals / magazines / newspapers',
        '027' => 'Reimbursement',
        '028' => 'Rental of motor vehicle',
        '029' => 'EV charging facilities',
        '030' => 'Repair and maintenance',
        '031' => 'Research and development',
        '032' => 'Foreign income',
        '033' => 'Self-billed - Betting and gaming',
        '034' => 'Self-billed - Importation of goods',
        '035' => 'Self-billed - Importation of services',
        '036' => 'Self-billed - Others',
        '037' => 'Self-billed - Monetary payment to agents, dealers or distributors',
        '038' => 'Sports equipment and facilities',
        '039' => 'Supporting equipment for disabled person',
        '040' => 'Voluntary contribution to approved provident fund',
        '041' => 'Dental examination or treatment',
        '042' => 'Fertility treatment',
        '043' => 'Treatment and home care nursing, daycare centres and residential care centers',
        '044' => 'Vouchers, gift cards, loyalty points, etc',
        '045' => 'Self-billed - Non-monetary payment to agents, dealers or distributors',
    ];

    /** @var array Classifications requiring special documentation */
    private const REQUIRES_DOCUMENTATION = [
        '005', // Construction materials
        '019', // Medical learning disabilities
        '021', // Medical serious diseases
        '023', // Petroleum operations
        '024', // Private retirement scheme
        '040', // Voluntary provident fund
    ];

    /** @var array Self-billed classifications */
    private const SELF_BILLED = [
        '033', '034', '035', '036', '037', '045',
    ];

    /** @var array Medical related classifications */
    private const MEDICAL_RELATED = [
        '019', '020', '021', '041', '042', '043',
    ];

    /** @var array Tax deductible classifications */
    private const TAX_DEDUCTIBLE = [
        '001', '002', '003', '010', '013', '014', '015', '019',
        '020', '021', '024', '026', '038', '039', '040', '041', '042', '043',
    ];

    /**
     * Validate a classification code.
     *
     * @param  string  $code  The classification code to validate
     * @return bool True if valid
     *
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (! isset(self::CLASSIFICATIONS[$code])) {
            throw new ValidationException(
                'Invalid classification code',
                ['classification' => [
                    'Code must be one of: '.implode(', ', array_keys(self::CLASSIFICATIONS)),
                ]]
            );
        }

        return true;
    }

    /**
     * Get the description for a classification code.
     *
     * @param  string  $code  The classification code
     * @return string|null The description or null if not found
     */
    public function getDescription(string $code): ?string
    {
        $code = $this->normalizeCode($code);

        return self::CLASSIFICATIONS[$code] ?? null;
    }

    /**
     * Check if a classification requires special documentation.
     *
     * @param  string  $code  The classification code
     * @return bool True if documentation is required
     *
     * @throws ValidationException If the code is invalid
     */
    public function requiresDocumentation(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return in_array($code, self::REQUIRES_DOCUMENTATION, true);
    }

    /**
     * Check if a classification is self-billed.
     *
     * @param  string  $code  The classification code
     * @return bool True if self-billed
     *
     * @throws ValidationException If the code is invalid
     */
    public function isSelfBilled(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return in_array($code, self::SELF_BILLED, true);
    }

    /**
     * Check if a classification is medical related.
     *
     * @param  string  $code  The classification code
     * @return bool True if medical related
     *
     * @throws ValidationException If the code is invalid
     */
    public function isMedicalRelated(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return in_array($code, self::MEDICAL_RELATED, true);
    }

    /**
     * Check if a classification is tax deductible.
     *
     * @param  string  $code  The classification code
     * @return bool True if tax deductible
     *
     * @throws ValidationException If the code is invalid
     */
    public function isTaxDeductible(string $code): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return in_array($code, self::TAX_DEDUCTIBLE, true);
    }

    /**
     * Get validation rules for a classification.
     *
     * @param  string  $code  The classification code
     * @return array Validation rules and requirements
     *
     * @throws ValidationException If the code is invalid
     */
    public function getValidationRules(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        return [
            'requires_documentation' => $this->requiresDocumentation($code),
            'is_self_billed' => $this->isSelfBilled($code),
            'is_medical_related' => $this->isMedicalRelated($code),
            'is_tax_deductible' => $this->isTaxDeductible($code),
            'requires_amount' => ! in_array($code, ['004', '007', '022'], true),
            'requires_recipient_details' => in_array($code, ['007', '037', '045'], true),
            'requires_registration_number' => $this->requiresDocumentation($code),
        ];
    }

    /**
     * Get required documentation types for a classification.
     *
     * @param  string  $code  The classification code
     * @return array List of required documentation types
     *
     * @throws ValidationException If the code is invalid
     */
    public function getRequiredDocumentation(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        if (! $this->requiresDocumentation($code)) {
            return [];
        }

        return match ($code) {
            '005' => ['CIDB Registration', 'Project Documentation'],
            '019' => ['Medical Certificate', 'Treatment Plan'],
            '021' => ['Medical Certificate', 'Diagnosis Report'],
            '023' => ['Petroleum License', 'Operation Permit'],
            '024' => ['Scheme Registration', 'Policy Documentation'],
            '040' => ['Fund Registration', 'Contribution Statement'],
            default => []
        };
    }

    /**
     * Format a classification code.
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
     * Parse a classification code.
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
     * Get all valid classifications.
     *
     * @return array<string, string> Array of classification codes and descriptions
     */
    public static function getClassifications(): array
    {
        return self::CLASSIFICATIONS;
    }

    /**
     * Get all self-billed classifications.
     *
     * @return array<string, string> Array of self-billed classifications
     */
    public static function getSelfBilledClassifications(): array
    {
        return array_intersect_key(
            self::CLASSIFICATIONS,
            array_flip(self::SELF_BILLED)
        );
    }

    /**
     * Get all medical related classifications.
     *
     * @return array<string, string> Array of medical related classifications
     */
    public static function getMedicalClassifications(): array
    {
        return array_intersect_key(
            self::CLASSIFICATIONS,
            array_flip(self::MEDICAL_RELATED)
        );
    }

    /**
     * Get all tax deductible classifications.
     *
     * @return array<string, string> Array of tax deductible classifications
     */
    public static function getTaxDeductibleClassifications(): array
    {
        return array_intersect_key(
            self::CLASSIFICATIONS,
            array_flip(self::TAX_DEDUCTIBLE)
        );
    }

    /**
     * Get classifications requiring documentation.
     *
     * @return array<string, string> Array of classifications requiring documentation
     */
    public static function getDocumentationRequiredClassifications(): array
    {
        return array_intersect_key(
            self::CLASSIFICATIONS,
            array_flip(self::REQUIRES_DOCUMENTATION)
        );
    }

    /**
     * Normalize a classification code.
     *
     * @param  string  $code  The code to normalize
     * @return string The normalized code
     */
    private function normalizeCode(string $code): string
    {
        // Remove any whitespace
        $code = trim($code);

        // Ensure 3-digit format with leading zeros
        return str_pad($code, 3, '0', STR_PAD_LEFT);
    }
}
