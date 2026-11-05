<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Support\UblJson;

/**
 * Field length / format rules that LHDN enforces on UBL JSON documents.
 *
 * Shared by MyInvoisClient::createDocument() and submitDocuments() (JSON only).
 * Every violation is collected and reported in one ValidationException,
 * keyed by field path (e.g. "Invoice.ID" => ["must not exceed 50 characters (got 51)"]).
 *
 * Sources (LHDN SDK release notes, https://sdk.myinvois.hasil.gov.my/sdk-1-0-release/):
 *  - 3 Jul 2026 (Production 15 Aug 2026): date format, field lengths, code lists.
 *  - 30 Apr 2026: no scientific notation in amounts; state code 17 limited.
 *  - 6 Aug 2026 (Production 24 Oct 2026): amounts max 26 digits; PASSPORT max 12.
 * UBL paths from https://sdk.myinvois.hasil.gov.my/documents/invoice-v1-1/.
 *
 * Only fields that are present are checked; missing mandatory fields are left
 * to LHDN. Signature blocks (UBLExtensions, Signature) are skipped.
 */
class DocumentFieldRulesValidator
{
    public const MAX_AMOUNT_DIGITS = 26;

    private const MALAYSIA = 'MYS';

    private const CONSOLIDATED_BUYER_TIN = 'EI00000000010';

    private const NOT_APPLICABLE_STATE = '17';

    /** Keys whose subtree is not validated. */
    private const SKIPPED_KEYS = ['UBLExtensions', 'Signature'];

    /** Number-valued fields that are not named *Amount but follow the amount rules. */
    private const EXTRA_AMOUNT_KEYS = ['Percent'];

    /** @var array<string, array<int, string>> */
    private $errors = [];

    /**
     * LHDN code lists, keyed by code. Codes are matched exactly (no padding or
     * case folding) because LHDN matches them exactly.
     *
     * @var array<string, array<string, string>>
     */
    private $codeLists;

    public function __construct()
    {
        $this->codeLists = [
            'country' => CountryValidator::getCountries(),
            'currency' => CurrencyValidator::getCurrencies(),
            'payment mode' => PaymentMethodValidator::getMethods(),
            'tax type' => TaxTypeValidator::getTaxTypes(),
            'unit of measurement' => UnitTypeValidator::getUnitTypes(),
        ];
    }

    /**
     * Validate a UBL JSON document and throw if any rule is violated.
     *
     * @param  string  $pathPrefix  Prepended to every error key (e.g. "documents[0].")
     *
     * @throws ValidationException
     */
    public function validate(string $json, string $pathPrefix = ''): void
    {
        $errors = $this->getViolations($json, $pathPrefix);

        if ($errors !== []) {
            throw self::exception($errors);
        }
    }

    /**
     * Build the exception for a set of violations.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function exception(array $errors): ValidationException
    {
        $count = array_sum(array_map('count', $errors));

        return new ValidationException(
            sprintf('Document failed LHDN field rules (%d violation%s)', $count, $count === 1 ? '' : 's'),
            $errors,
            422
        );
    }

    /**
     * Return all violations as field-path => messages.
     *
     * The raw JSON text is inspected (not a decoded copy) so that exponent
     * notation and digit counts are checked exactly as the document was written.
     * Non-JSON input and documents without an "Invoice" root yield no violations.
     *
     * @return array<string, array<int, string>>
     */
    public function getViolations(string $json, string $pathPrefix = ''): array
    {
        $document = UblJson::decodePreservingNumbers($json);
        if ($document === null || ! isset($document['Invoice']) || ! is_array($document['Invoice'])) {
            return [];
        }

        $this->errors = [];

        foreach ($document['Invoice'] as $index => $invoice) {
            if (is_array($invoice)) {
                $this->validateInvoice($invoice, $pathPrefix . 'Invoice' . self::index($index));
            }
        }

        $errors = $this->errors;
        $this->errors = [];

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function validateInvoice(array $invoice, string $path): void
    {
        // e-Invoice Code/Number: max 50
        $this->maxLength($invoice, 'ID', 50, $path);

        // Currency codes
        foreach (['DocumentCurrencyCode', 'TaxCurrencyCode'] as $key) {
            foreach (self::values($invoice, $key) as $i => $code) {
                $this->code($code, 'currency', "{$path}.{$key}" . self::index($i));
            }
        }

        // Frequency of Billing: max 50
        foreach (self::nodes($invoice, 'InvoicePeriod') as $i => $period) {
            $this->maxLength($period, 'Description', 50, "{$path}.InvoicePeriod" . self::index($i));
        }

        // Supplier bank account (max 150) and payment mode code list
        foreach (self::nodes($invoice, 'PaymentMeans') as $i => $means) {
            $meansPath = "{$path}.PaymentMeans" . self::index($i);
            foreach (self::values($means, 'PaymentMeansCode') as $j => $code) {
                $this->code($code, 'payment mode', "{$meansPath}.PaymentMeansCode" . self::index($j));
            }
            foreach (self::nodes($means, 'PayeeFinancialAccount') as $j => $account) {
                $this->maxLength($account, 'ID', 150, "{$meansPath}.PayeeFinancialAccount" . self::index($j));
            }
        }

        // Payment terms: max 300
        foreach (self::nodes($invoice, 'PaymentTerms') as $i => $terms) {
            $this->maxLength($terms, 'Note', 300, "{$path}.PaymentTerms" . self::index($i));
        }

        // PrePayment reference number: max 150
        foreach (self::nodes($invoice, 'PrepaidPayment') as $i => $prepaid) {
            $this->maxLength($prepaid, 'ID', 150, "{$path}.PrepaidPayment" . self::index($i));
        }

        // Incoterms: max 3. SDK path cac:AdditionalDocumentReference[4]/cbc:ID; in the
        // official samples it is the reference that carries an ID but no DocumentType.
        foreach (self::nodes($invoice, 'AdditionalDocumentReference') as $i => $reference) {
            if (! isset($reference['DocumentType'])) {
                $this->maxLength($reference, 'ID', 3, "{$path}.AdditionalDocumentReference" . self::index($i), 'Incoterms');
            }
        }

        // Supplier: Certified Exporter number (max 300), business activity description (max 300)
        foreach (self::nodes($invoice, 'AccountingSupplierParty') as $i => $supplier) {
            $supplierPath = "{$path}.AccountingSupplierParty" . self::index($i);
            foreach (self::nodes($supplier, 'AdditionalAccountID') as $j => $account) {
                if (strcasecmp((string) ($account['schemeAgencyName'] ?? ''), 'CertEX') === 0) {
                    $this->stringMax($account['_'] ?? null, 300, "{$supplierPath}.AdditionalAccountID" . self::index($j));
                }
            }
            foreach (self::nodes($supplier, 'Party') as $j => $party) {
                foreach (self::nodes($party, 'IndustryClassificationCode') as $k => $msic) {
                    if (array_key_exists('name', $msic)) {
                        $this->stringMax($msic['name'], 300, "{$supplierPath}.Party" . self::index($j) . '.IndustryClassificationCode' . self::index($k) . '@name');
                    }
                }
            }
        }

        // Unit of measurement
        foreach (self::nodes($invoice, 'InvoiceLine') as $i => $line) {
            foreach (self::nodes($line, 'InvoicedQuantity') as $j => $quantity) {
                if (array_key_exists('unitCode', $quantity)) {
                    $this->code($quantity['unitCode'], 'unit of measurement', "{$path}.InvoiceLine" . self::index($i) . '.InvoicedQuantity' . self::index($j) . '@unitCode');
                }
            }
        }

        $this->walk($invoice, $path, $this->isConsolidated($invoice));
    }

    /**
     * Recursive checks that apply wherever the field appears: dates, amounts,
     * currencyID attributes, tax types, postal addresses and PASSPORT IDs.
     *
     * @param  array<mixed>  $node
     */
    private function walk(array $node, string $path, bool $consolidated): void
    {
        foreach ($node as $key => $value) {
            if (in_array($key, self::SKIPPED_KEYS, true) || ! is_array($value)) {
                continue;
            }

            $isList = is_int($key);
            $childPath = $isList ? $path . self::index($key) : "{$path}.{$key}";

            if (! $isList) {
                if (str_ends_with((string) $key, 'Date')) {
                    $this->checkDates($value, $childPath);
                } elseif (str_ends_with((string) $key, 'Amount') || in_array($key, self::EXTRA_AMOUNT_KEYS, true)) {
                    $this->checkAmounts($value, $childPath);
                } elseif ($key === 'PostalAddress') {
                    $this->checkPostalAddresses($value, $childPath, $consolidated);
                } elseif ($key === 'TaxCategory') {
                    foreach (self::nodes($node, 'TaxCategory') as $i => $category) {
                        foreach (self::values($category, 'ID') as $j => $code) {
                            $this->code($code, 'tax type', "{$childPath}" . self::index($i) . '.ID' . self::index($j));
                        }
                    }
                } elseif ($key === 'PartyIdentification') {
                    $this->checkPartyIdentification($value, $childPath);
                }
            }

            if (! $isList && isset($value[0]) && is_array($value[0]) && array_key_exists('currencyID', $value[0])) {
                foreach ($value as $i => $item) {
                    if (is_array($item) && array_key_exists('currencyID', $item)) {
                        $this->code($item['currencyID'], 'currency', $childPath . self::index($i) . '@currencyID');
                    }
                }
            }

            $this->walk($value, $childPath, $consolidated);
        }
    }

    /**
     * @param  array<mixed>  $items
     */
    private function checkDates(array $items, string $path): void
    {
        foreach ($items as $i => $item) {
            if (! is_array($item) || ! array_key_exists('_', $item)) {
                continue;
            }
            $value = $item['_'];
            $valid = is_string($value)
                && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
                && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
            if (! $valid) {
                $this->add($path . self::index($i), sprintf('must be a real date in YYYY-MM-DD format (got %s)', self::describe($value)));
            }
        }
    }

    /**
     * @param  array<mixed>  $items
     */
    private function checkAmounts(array $items, string $path): void
    {
        foreach ($items as $i => $item) {
            if (! is_array($item) || ! array_key_exists('_', $item)) {
                continue;
            }
            $value = $item['_'];
            $itemPath = $path . self::index($i);

            if (! is_string($value) || ! is_numeric($value)) {
                $this->add($itemPath, sprintf('must be a plain decimal number (got %s)', self::describe($value)));
                continue;
            }
            if (preg_match('/[eE]/', $value)) {
                $this->add($itemPath, sprintf('must not use scientific notation (got %s)', $value));
                continue;
            }
            if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $value)) {
                $this->add($itemPath, sprintf('must be a plain decimal number (got %s)', $value));
                continue;
            }
            $digits = strlen((string) preg_replace('/\D/', '', $value));
            if ($digits > self::MAX_AMOUNT_DIGITS) {
                $this->add($itemPath, sprintf('must not exceed %d digits (got %d)', self::MAX_AMOUNT_DIGITS, $digits));
            }
        }
    }

    /**
     * State code and postcode rules depend on the address's own country.
     *
     * @param  array<mixed>  $addresses
     */
    private function checkPostalAddresses(array $addresses, string $path, bool $consolidated): void
    {
        foreach ($addresses as $i => $address) {
            if (! is_array($address)) {
                continue;
            }
            $addressPath = $path . self::index($i);

            $country = null;
            foreach (self::nodes($address, 'Country') as $j => $countryNode) {
                foreach (self::values($countryNode, 'IdentificationCode') as $k => $code) {
                    $country = strtoupper(trim((string) $code));
                    $this->code($code, 'country', "{$addressPath}.Country" . self::index($j) . '.IdentificationCode' . self::index($k));
                }
            }
            $isMalaysia = $country === self::MALAYSIA;

            foreach (self::values($address, 'CountrySubentityCode') as $j => $state) {
                $statePath = "{$addressPath}.CountrySubentityCode" . self::index($j);
                if ($isMalaysia) {
                    $state = (string) $state;
                    if (! isset(StateCodeValidator::getStates()[$state])) {
                        $this->add($statePath, sprintf('must be a state code 01-17 for Malaysian addresses (got %s)', self::describe($state)));
                    } elseif ($state === self::NOT_APPLICABLE_STATE && ! $consolidated) {
                        $this->add($statePath, sprintf('state code 17 is only allowed for consolidated e-Invoices (buyer TIN %s) or non-Malaysian addresses', self::CONSOLIDATED_BUYER_TIN));
                    }
                } else {
                    $this->stringMax($state, 50, $statePath);
                }
            }

            foreach (self::values($address, 'PostalZone') as $j => $postcode) {
                $this->stringMax($postcode, $isMalaysia ? 5 : 50, "{$addressPath}.PostalZone" . self::index($j));
            }
        }
    }

    /**
     * @param  array<mixed>  $identifications
     */
    private function checkPartyIdentification(array $identifications, string $path): void
    {
        foreach ($identifications as $i => $identification) {
            foreach (self::nodes(is_array($identification) ? $identification : [], 'ID') as $j => $id) {
                if (strtoupper((string) ($id['schemeID'] ?? '')) === 'PASSPORT') {
                    $this->stringMax($id['_'] ?? null, 12, $path . self::index($i) . '.ID' . self::index($j), 'PASSPORT');
                }
            }
        }
    }

    /**
     * A consolidated e-Invoice has buyer TIN EI00000000010.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function isConsolidated(array $invoice): bool
    {
        foreach (self::nodes($invoice, 'AccountingCustomerParty') as $customer) {
            foreach (self::nodes($customer, 'Party') as $party) {
                foreach (self::nodes($party, 'PartyIdentification') as $identification) {
                    foreach (self::nodes($identification, 'ID') as $id) {
                        if (strtoupper((string) ($id['schemeID'] ?? '')) === 'TIN'
                            && strtoupper(trim((string) ($id['_'] ?? ''))) === self::CONSOLIDATED_BUYER_TIN) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function maxLength(array $node, string $key, int $max, string $parentPath, string $label = ''): void
    {
        foreach (self::values($node, $key) as $i => $value) {
            $this->stringMax($value, $max, "{$parentPath}.{$key}" . self::index($i), $label);
        }
    }

    /**
     * @param  mixed  $value
     */
    private function stringMax($value, int $max, string $path, string $label = ''): void
    {
        if ($value === null) {
            return;
        }
        $length = mb_strlen(is_scalar($value) ? (string) $value : (string) json_encode($value));
        if ($length > $max) {
            $this->add($path, sprintf('%smust not exceed %d characters (got %d)', $label !== '' ? $label . ' ' : '', $max, $length));
        }
    }

    /**
     * @param  mixed  $value
     */
    private function code($value, string $list, string $path): void
    {
        if (! is_string($value) || ! isset($this->codeLists[$list][$value])) {
            $this->add($path, sprintf('%s is not in the LHDN %s code list', self::describe($value), $list));
        }
    }

    private function add(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }

    /**
     * UBL JSON wraps each field in a list of objects; return those objects.
     *
     * @param  array<mixed>  $node
     * @return array<int, array<mixed>>
     */
    private static function nodes(array $node, string $key): array
    {
        if (! isset($node[$key]) || ! is_array($node[$key])) {
            return [];
        }

        return array_filter($node[$key], 'is_array');
    }

    /**
     * Return the "_" values of a UBL field.
     *
     * @param  array<mixed>  $node
     * @return array<int, mixed>
     */
    private static function values(array $node, string $key): array
    {
        $values = [];
        foreach (self::nodes($node, $key) as $i => $item) {
            if (array_key_exists('_', $item)) {
                $values[$i] = $item['_'];
            }
        }

        return $values;
    }

    /**
     * Path suffix for a list index: "" for the first element, "[n]" otherwise.
     *
     * @param  int|string  $index
     */
    private static function index($index): string
    {
        return ((int) $index) === 0 ? '' : '[' . $index . ']';
    }

    /**
     * @param  mixed  $value
     */
    private static function describe($value): string
    {
        if (is_string($value)) {
            return '"' . (mb_strlen($value) > 40 ? mb_substr($value, 0, 40) . '…' : $value) . '"';
        }

        return (string) json_encode($value);
    }
}
