<?php

namespace Nava\MyInvois\Validation;

use Nava\MyInvois\Exception\ValidationException;

/**
 * Class for validating and handling ISO-3166 alpha-3 country codes according to MyInvois standards.
 */
class CountryValidator
{
    /** @var array Mapping of country codes to names */
    private const COUNTRIES = [
        'ABW' => 'ARUBA',
        'AFG' => 'AFGHANISTAN',
        'AGO' => 'ANGOLA',
        'AIA' => 'ANGUILLA',
        'ALA' => 'ALAND ISLANDS',
        'ALB' => 'ALBANIA',
        'AND' => 'ANDORA',
        'ANT' => 'NETHERLANDS ANTILLES',
        'ARE' => 'UNITED ARAB EMIRATES',
        'ARG' => 'ARGENTINA',
        'ARM' => 'ARMENIA',
        'ASM' => 'AMERICAN SAMOA',
        'ATA' => 'ANTARCTICA',
        'ATF' => 'FRENCH SOUTHERN TERRITORIES',
        'ATG' => 'ANTIGUA AND BARBUDA',
        'AUS' => 'AUSTRALIA',
        'AUT' => 'AUSTRIA',
        'AZE' => 'AZERBAIDJAN',
        'BDI' => 'BURUNDI',
        'BEL' => 'BELGIUM',
        'BEN' => 'BENIN',
        'BES' => 'BONAIRE, SINT EUSTATIUS AND SABA',
        'BFA' => 'BURKINA FASO',
        'BGD' => 'BANGLADESH',
        'BGR' => 'BULGARIA',
        'BHR' => 'BAHRAIN',
        'BHS' => 'BAHAMAS',
        'BIH' => 'BOSNIA AND HERZEGOVINA',
        'BLM' => 'SAINT BARTHELEMY',
        'BLR' => 'BELARUS',
        'BLZ' => 'BELIZE',
        'BMU' => 'BERMUDA',
        'BOL' => 'BOLIVIA',
        'BRA' => 'BRAZIL',
        'BRB' => 'BARBADOS',
        'BRN' => 'BRUNEI DARUSSALAM',
        'BTN' => 'BHUTAN',
        'BVT' => 'BOUVET ISLAND',
        'BWA' => 'BOTSWANA',
        'CAF' => 'CENTRAL AFRICAN REPUBLIC',
        'CAN' => 'CANADA',
        'CCK' => 'COCOS ISLAND',
        'CHE' => 'SWITZERLAND',
        'CHL' => 'CHILE',
        'CHN' => 'CHINA',
        'CIV' => 'COTE D’IVOIRE',
        'CMR' => 'CAMEROON',
        'COD' => 'CONGO, THE DEMOCRATIC REPUBLIC',
        'COG' => 'CONGO',
        'COK' => 'COOK ISLANDS',
        'COL' => 'COLOMBIA',
        'COM' => 'COMOROS',
        'CPV' => 'CAPE VERDE',
        'CRI' => 'COSTA RICA',
        'CUB' => 'CUBA',
        'CUW' => 'CURACAO',
        'CXR' => 'CHRISTMAS ISLANDS',
        'CYM' => 'CAYMAN ISLANDS',
        'CYP' => 'CYPRUS',
        'CZE' => 'CZECH REPUBLIC',
        'DEU' => 'GERMANY',
        'DJI' => 'DJIBOUTI',
        'DMA' => 'DOMINICA',
        'DNK' => 'DENMARK',
        'DOM' => 'DOMINICAN REPUBLIC',
        'DZA' => 'ALGERIA',
        'ECU' => 'ECUADOR',
        'EGY' => 'EGYPT',
        'ERI' => 'ERITREA',
        'ESH' => 'WESTERN SAHARA',
        'ESP' => 'SPAIN',
        'EST' => 'ESTONIA',
        'ETH' => 'ETHIOPIA',
        'FIN' => 'FINLAND',
        'FJI' => 'FIJI',
        'FLK' => 'FALKLAND ISLANDS (MALVINAS)',
        'FRA' => 'FRANCE',
        'FRO' => 'FAEROE ISLANDS',
        'FSM' => 'MICRONESIA, FEDERATED STATES OF',
        'GAB' => 'GABON',
        'GBR' => 'UNITED KINGDOM',
        'GEO' => 'GEORGIA',
        'GGY' => 'GUERNSEY',
        'GHA' => 'GHANA',
        'GIB' => 'GIBRALTAR',
        'GIN' => 'GUINEA',
        'GLP' => 'GUADELOUPE',
        'GMB' => 'GAMBIA',
        'GNB' => 'GUINEA-BISSAU',
        'GNQ' => 'EQUATORIAL GUINEA',
        'GRC' => 'GREECE',
        'GRD' => 'GRENADA',
        'GRL' => 'GREENLAND',
        'GTM' => 'GUATEMALA',
        'GUF' => 'FRENCH GUIANA',
        'GUM' => 'GUAM',
        'GUY' => 'GUYANA',
        'HKG' => 'HONG KONG',
        'HMD' => 'HEARD AND MCDONALD ISLANDS',
        'HND' => 'HONDURAS',
        'HRV' => 'CROATIA',
        'HTI' => 'HAITI',
        'HUN' => 'HUNGARY',
        'IDN' => 'INDONESIA',
        'IMN' => 'ISLE OF MAN',
        'IND' => 'INDIA',
        'IOT' => 'BRITISH INDIAN OCEAN TERRITORY',
        'IRL' => 'IRELAND',
        'IRN' => 'IRAN',
        'IRQ' => 'IRAQ',
        'ISL' => 'ICELAND',
        'ISR' => 'ISRAEL',
        'ITA' => 'ITALY',
        'JAM' => 'JAMAICA',
        'JEY' => 'JERSEY (CHANNEL ISLANDS)',
        'JOR' => 'JORDAN',
        'JPN' => 'JAPAN',
        'KAZ' => 'KAZAKHSTAN',
        'KEN' => 'KENYA',
        'KGZ' => 'KYRGYZSTAN',
        'KHM' => 'CAMBODIA',
        'KIR' => 'KIRIBATI',
        'KNA' => 'ST.KITTS AND NEVIS',
        'KOR' => 'THE REPUBLIC OF KOREA',
        'KWT' => 'KUWAIT',
        'LAO' => 'LAOS',
        'LBN' => 'LEBANON',
        'LBR' => 'LIBERIA',
        'LBY' => 'LIBYAN ARAB JAMAHIRIYA',
        'LCA' => 'SAINT LUCIA',
        'LIE' => 'LIECHTENSTEIN',
        'LKA' => 'SRI LANKA',
        'LSO' => 'LESOTHO',
        'LTU' => 'LITHUANIA',
        'LUX' => 'LUXEMBOURG',
        'LVA' => 'LATVIA',
        'MAC' => 'MACAO',
        'MAF' => 'SAINT MARTIN (FRENCH PART)',
        'MAR' => 'MOROCCO',
        'MCO' => 'MONACO',
        'MDA' => 'MOLDOVA, REPUBLIC OF',
        'MDG' => 'MADAGASCAR',
        'MDV' => 'MALDIVES',
        'MEX' => 'MEXICO',
        'MHL' => 'MARSHALL ISLANDS',
        'MKD' => 'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF',
        'MLI' => 'MALI',
        'MLT' => 'MALTA',
        'MMR' => 'MYANMAR',
        'MNE' => 'MONTENEGRO',
        'MNG' => 'MONGOLIA',
        'MNP' => 'NORTHERN MARIANA ISLANDS',
        'MOZ' => 'MOZAMBIQUE',
        'MRT' => 'MAURITANIA',
        'MSR' => 'MONTSERRAT',
        'MTQ' => 'MARTINIQUE',
        'MUS' => 'MAURITIUS',
        'MWI' => 'MALAWI',
        'MYS' => 'MALAYSIA',
        'MYT' => 'MAYOTTE',
        'NAM' => 'NAMIBIA',
        'NCL' => 'NEW CALEDONIA',
        'NER' => 'NIGER',
        'NFK' => 'NORFOLK ISLAND',
        'NGA' => 'NIGERIA',
        'NIC' => 'NICARAGUA',
        'NIU' => 'NIUE',
        'NLD' => 'NETHERLANDS',
        'NOR' => 'NORWAY',
        'NPL' => 'NEPAL',
        'NRU' => 'NAURU',
        'NZL' => 'NEW ZEALAND',
        'OMN' => 'OMAN',
        'PAK' => 'PAKISTAN',
        'PAN' => 'PANAMA',
        'PCN' => 'PITCAIRN',
        'PER' => 'PERU',
        'PHL' => 'PHILIPPINES',
        'PLW' => 'PALAU',
        'PNG' => 'PAPUA NEW GUINEA',
        'POL' => 'POLAND',
        'PRI' => 'PUERTO RICO',
        'PRK' => 'DEMOC.PEOPLES REP.OF KOREA',
        'PRT' => 'PORTUGAL',
        'PRY' => 'PARAGUAY',
        'PSE' => 'PALESTINIAN TERRITORY, OCCUPIED',
        'PYF' => 'FRENCH POLYNESIA',
        'QAT' => 'QATAR',
        'REU' => 'REUNION',
        'ROU' => 'ROMANIA',
        'RUS' => 'RUSSIAN FEDERATION (USSR)',
        'RWA' => 'RWANDA',
        'SAU' => 'SAUDI ARABIA',
        'SDN' => 'SUDAN',
        'SEN' => 'SENEGAL',
        'SGP' => 'SINGAPORE',
        'SGS' => 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLAND',
        'SHN' => 'ST. HELENA',
        'SJM' => 'SVALBARD AND JAN MAYEN ISLANDS',
        'SLB' => 'SOLOMON ISLANDS',
        'SLE' => 'SIERRA LEONE',
        'SLV' => 'EL SALVADOR',
        'SMR' => 'SAN MARINO',
        'SOM' => 'SOMALIA',
        'SPM' => 'ST. PIERRE AND MIQUELON',
        'SRB' => 'SERBIA & MONTENEGRO',
        'SSD' => 'SOUTH SUDAN',
        'STP' => 'SAO TOME AND PRINCIPE',
        'SUR' => 'SURINAME',
        'SVK' => 'SLOVAK REPUBLIC',
        'SVN' => 'SLOVENIA',
        'SWE' => 'SWEDEN',
        'SWZ' => 'ESWATINI, KINGDOM OF (SWAZILAND)',
        'SXM' => 'SINT MAARTEN (DUTCH PART)',
        'SYC' => 'SEYCHELLES',
        'SYR' => 'SYRIAN ARAB REPUBLIC',
        'TCA' => 'TURKS AND CAICOS ISLANDS',
        'TCD' => 'CHAD',
        'TGO' => 'TOGO',
        'THA' => 'THAILAND',
        'TJK' => 'TAJIKISTAN',
        'TKL' => 'TOKELAU',
        'TKM' => 'TURKMENISTAN',
        'TLS' => 'TIMOR-LESTE',
        'TON' => 'TONGA',
        'TTO' => 'TRINIDAD AND TOBAGO',
        'TUN' => 'TUNISIA',
        'TUR' => 'TURKIYE',
        'TUV' => 'TUVALU',
        'TWN' => 'TAIWAN',
        'TZA' => 'TANZANIA UNITED REPUBLIC',
        'UGA' => 'UGANDA',
        'UKR' => 'UKRAINE',
        'UMI' => 'UNITED STATES MINOR OUTLYING ISLANDS',
        'URY' => 'URUGUAY',
        'USA' => 'UNITED STATES OF AMERICA',
        'UZB' => 'UZBEKISTAN',
        'VAT' => 'VATICAN CITY STATE (HOLY SEE)',
        'VCT' => 'SAINT VINCENT AND GRENADINES',
        'VEN' => 'VENEZUELA',
        'VGB' => 'VIRGIN ISLANDS(BRITISH)',
        'VIR' => 'VIRGIN ISLANDS(US)',
        'VNM' => 'VIETNAM',
        'VUT' => 'VANUATU',
        'WLF' => 'WALLIS AND FUTUNA ISLANDS',
        'WSM' => 'SAMOA',
        'XKX' => 'KOSOVO',
        'YEM' => 'YEMEN',
        'ZAF' => 'SOUTH AFRICA',
        'ZMB' => 'ZAMBIA',
        'ZWE' => 'ZIMBABWE',
    ];

    /** @var array Common regions grouping */
    private const REGIONS = [
        'ASEAN' => ['BRN', 'KHM', 'IDN', 'LAO', 'MYS', 'MMR', 'PHL', 'SGP', 'THA', 'VNM'],
        'EU' => ['AUT', 'BEL', 'BGR', 'HRV', 'CYP', 'CZE', 'DNK', 'EST', 'FIN', 'FRA', 'DEU', 'GRC'],
        'APAC' => ['AUS', 'CHN', 'HKG', 'IDN', 'JPN', 'KOR', 'MYS', 'NZL', 'PHL', 'SGP', 'TWN', 'THA', 'VNM'],
    ];

    /** @var array Regional trade agreements */
    private const TRADE_AGREEMENTS = [
        'RCEP' => ['AUS', 'BRN', 'KHM', 'CHN', 'IDN', 'JPN', 'KOR', 'LAO', 'MYS', 'MMR', 'NZL', 'PHL', 'SGP', 'THA', 'VNM'],
        'CPTPP' => ['AUS', 'BRN', 'CAN', 'CHL', 'JPN', 'MYS', 'MEX', 'NZL', 'PER', 'SGP', 'VNM'],
    ];

    /**
     * Validate a country code.
     *
     * @param string $code The country code to validate
     * @return bool True if valid
     * @throws ValidationException If the code is invalid
     */
    public function validate(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (!isset(self::COUNTRIES[$code])) {
            throw new ValidationException(
                'Invalid country code',
                ['country' => ['Code must be a valid ISO-3166 alpha-3 country code']]
            );
        }

        return true;
    }

    /**
     * Get the country name for a given code.
     *
     * @param string $code The country code
     * @return string|null The country name or null if not found
     */
    public function getCountryName(string $code): ?string
    {
        $code = $this->normalizeCode($code);
        return self::COUNTRIES[$code] ?? null;
    }

    /**
     * Check if a country is in a specific region.
     *
     * @param string $code The country code
     * @param string $region The region to check (ASEAN, EU, APAC)
     * @return bool True if the country is in the region
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
     * Check if a country is a member of a trade agreement.
     *
     * @param string $code The country code
     * @param string $agreement The trade agreement to check (RCEP, CPTPP)
     * @return bool True if the country is a member
     * @throws ValidationException If the code or agreement is invalid
     */
    public function isInTradeAgreement(string $code, string $agreement): bool
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        if (!isset(self::TRADE_AGREEMENTS[$agreement])) {
            throw new ValidationException(
                'Invalid trade agreement',
                ['agreement' => ['Agreement must be one of: ' . implode(', ', array_keys(self::TRADE_AGREEMENTS))]]
            );
        }

        return in_array($code, self::TRADE_AGREEMENTS[$agreement], true);
    }

    /**
     * Get validation rules for a country code.
     *
     * @param string $code The country code
     * @return array Validation rules and membership info
     * @throws ValidationException If the code is invalid
     */
    public function getValidationRules(string $code): array
    {
        $this->validate($code);
        $code = $this->normalizeCode($code);

        $regions = array_keys(array_filter(
            self::REGIONS,
            fn($members) => in_array($code, $members, true)
        ));

        $agreements = array_keys(array_filter(
            self::TRADE_AGREEMENTS,
            fn($members) => in_array($code, $members, true)
        ));

        return [
            'regions' => $regions,
            'trade_agreements' => $agreements,
            'requires_special_documentation' => in_array($code, ['USA', 'CHN', 'RUS'], true),
            'requires_certificate_origin' => !in_array($code, self::REGIONS['ASEAN'], true),
            'is_sanctions_list' => in_array($code, ['PRK', 'IRN', 'SYR'], true),
        ];
    }

    /**
     * Format a country code.
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
     * Get all valid countries.
     *
     * @return array<string, string> Array of country codes and names
     */
    public static function getCountries(): array
    {
        return self::COUNTRIES;
    }

    /**
     * Get countries in a specific region.
     *
     * @param string $region The region name
     * @return array<string, string> Array of countries in the region
     * @throws ValidationException If the region is invalid
     */
    public function getCountriesInRegion(string $region): array
    {
        if (!isset(self::REGIONS[$region])) {
            throw new ValidationException(
                'Invalid region',
                ['region' => ['Region must be one of: ' . implode(', ', array_keys(self::REGIONS))]]
            );
        }

        return array_intersect_key(
            self::COUNTRIES,
            array_flip(self::REGIONS[$region])
        );
    }

    /**
     * Get countries in a specific trade agreement.
     *
     * @param string $agreement The trade agreement name
     * @return array<string, string> Array of countries in the agreement
     * @throws ValidationException If the agreement is invalid
     */
    public function getCountriesInTradeAgreement(string $agreement): array
    {
        if (!isset(self::TRADE_AGREEMENTS[$agreement])) {
            throw new ValidationException(
                'Invalid trade agreement',
                ['agreement' => ['Agreement must be one of: ' . implode(', ', array_keys(self::TRADE_AGREEMENTS))]]
            );
        }

        return array_intersect_key(
            self::COUNTRIES,
            array_flip(self::TRADE_AGREEMENTS[$agreement])
        );
    }

    /**
     * Normalize a country code.
     *
     * @param string $code The code to normalize
     * @return string The normalized code
     */
    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Get all available countries.
     *
     * @return array An array of country codes and names
     */
    public static function getAllCountries(): array
    {
        return self::COUNTRIES;
    }
}
