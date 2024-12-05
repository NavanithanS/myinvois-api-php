<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating and handling Malaysian state codes according to MyInvois standards.
 */
class StateCodeValidator
{
    /** @var array Mapping of state codes to names */
    private const STATE_CODES = [
        '01' => 'Johor',
        '02' => 'Kedah',
        '03' => 'Kelantan',
        '04' => 'Melaka',
        '05' => 'Negeri Sembilan',
        '06' => 'Pahang',
        '07' => 'Pulau Pinang',
        '08' => 'Perak',
        '09' => 'Perlis',
        '10' => 'Selangor',
        '11' => 'Terengganu',
        '12' => 'Sabah',
        '13' => 'Sarawak',
        '14' => 'Wilayah Persekutuan Kuala Lumpur',
        '15' => 'Wilayah Persekutuan Labuan',
        '16' => 'Wilayah Persekutuan Putrajaya',
        '17' => 'Not Applicable',
    ];

    /** @var array Mapping of common alternate names and abbreviations */
    private const ALTERNATE_NAMES = [
        'penang' => '07',
        'wp kuala lumpur' => '14',
        'wp labuan' => '15',
        'wp putrajaya' => '16',
        'kl' => '14',
        'fdt kuala lumpur' => '14',
        'fdt labuan' => '15',
        'fdt putrajaya' => '16',
        'n. sembilan' => '05',
        'n.sembilan' => '05',
        'negri sembilan' => '05',
        'p. pinang' => '07',
        'p.pinang' => '07',
        'pulau pinang' => '07',
        'na' => '17',
        'n/a' => '17',
    ];

    /** @var array Geographic regions */
    private const REGIONS = [
        'PENINSULAR' => ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '14', '16'],
        'EAST_MALAYSIA' => ['12', '13', '15'],
        'FEDERAL_TERRITORIES' => ['14', '15', '16'],
    ];

    /**
     * Validate a state code.
     *
     * @param string $code The state code to validate
     * @return bool True if valid
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (!isset(self::STATE_CODES[$code])) {
            throw new ValidationException(
                'Invalid state code',
                ['state' => [
                    'Code must be one of: ' . implode(', ', array_keys(self::STATE_CODES)),
                ]]
            );
        }

        return true;
    }

    /**
     * Get the state name for a given code.
     *
     * @param string $code The state code
     * @return string|null The state name or null if not found
     */
    public function getStateName(string $code): ?string
    {
        $code = $this->normalizeCode($code);
        return self::STATE_CODES[$code] ?? null;
    }

    /**
     * Get the state code for a given state name.
     *
     * @param string $name The state name or alternate name
     * @return string|null The state code or null if not found
     */
    public function getStateCode(string $name): ?string
    {
        // Normalize the input name
        $normalized = strtolower(trim($name));

        // Check direct mapping
        foreach (self::STATE_CODES as $code => $stateName) {
            if (strtolower($stateName) === $normalized) {
                return $code;
            }
        }

        // Check alternate names
        return self::ALTERNATE_NAMES[$normalized] ?? null;
    }

    /**
     * Format a state code with leading zero if needed.
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
     * Parse a state identifier (code or name) into a valid state code.
     *
     * @param string|int $identifier The state identifier to parse
     * @return string The normalized state code
     * @throws ValidationException If the identifier is invalid
     */
    public function parse($identifier): string
    {
        if (is_int($identifier)) {
            $identifier = (string) $identifier;
        }

        // Try as a code first
        $normalized = $this->normalizeCode($identifier);
        if (isset(self::STATE_CODES[$normalized])) {
            return $normalized;
        }

        // Try as a state name
        $code = $this->getStateCode($identifier);
        if (null !== $code) {
            return $code;
        }

        throw new ValidationException(
            'Invalid state identifier',
            ['state' => ['Not a valid state code or name']]
        );
    }

    /**
     * Check if a state is in a specific region.
     *
     * @param string $code The state code
     * @param string $region The region to check (PENINSULAR, EAST_MALAYSIA, FEDERAL_TERRITORIES)
     * @return bool True if the state is in the region
     * @throws ValidationException If the code or region is invalid
     */
    public function isInRegion(string $code, string $region): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        if (!isset(self::REGIONS[$region])) {
            throw new ValidationException(
                'Invalid region',
                ['region' => ['Region must be one of: ' . implode(', ', array_keys(self::REGIONS))]]
            );
        }

        return in_array($code, self::REGIONS[$region], true);
    }

    /**
     * Get all states in a region.
     *
     * @param string $region The region name
     * @return array<string, string> Array of state codes and names in the region
     * @throws ValidationException If the region is invalid
     */
    public function getStatesInRegion(string $region): array
    {
        if (!isset(self::REGIONS[$region])) {
            throw new ValidationException(
                'Invalid region',
                ['region' => ['Region must be one of: ' . implode(', ', array_keys(self::REGIONS))]]
            );
        }

        return array_intersect_key(
            self::STATE_CODES,
            array_flip(self::REGIONS[$region])
        );
    }

    /**
     * Check if a state is a Federal Territory.
     *
     * @param string $code The state code
     * @return bool True if the state is a Federal Territory
     * @throws ValidationException If the code is invalid
     */
    public function isFederalTerritory(string $code): bool
    {
        return $this->isInRegion($code, 'FEDERAL_TERRITORIES');
    }

    /**
     * Check if a state is in East Malaysia.
     *
     * @param string $code The state code
     * @return bool True if the state is in East Malaysia
     * @throws ValidationException If the code is invalid
     */
    public function isEastMalaysia(string $code): bool
    {
        return $this->isInRegion($code, 'EAST_MALAYSIA');
    }

    /**
     * Check if a state is in Peninsular Malaysia.
     *
     * @param string $code The state code
     * @return bool True if the state is in Peninsular Malaysia
     * @throws ValidationException If the code is invalid
     */
    public function isPeninsular(string $code): bool
    {
        return $this->isInRegion($code, 'PENINSULAR');
    }

    /**
     * Get all valid state codes and names.
     *
     * @return array<string, string> Array of state codes and names
     */
    public static function getStates(): array
    {
        return self::STATE_CODES;
    }

    /**
     * Get all available regions.
     *
     * @return array<string, array<string>> Array of regions and their state codes
     */
    public static function getRegions(): array
    {
        return self::REGIONS;
    }

    /**
     * Normalize a state code.
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
