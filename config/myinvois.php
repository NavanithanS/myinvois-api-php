<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MyInvois API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your MyInvois API credentials and environment settings here.
    |
     */

    'client_id'            => env('MYINVOIS_CLIENT_ID'),

    'client_secret'        => env('MYINVOIS_CLIENT_SECRET'),

    'base_url'             => env('MYINVOIS_BASE_URL', \Nava\MyInvois\MyInvoisClient::SANDBOX_URL),

    // Portal host for document share/QR links. Leave empty to derive it from base_url
    // (preprod-api -> preprod.myinvois.hasil.gov.my, api -> myinvois.hasil.gov.my).
    'portal_url'           => env('MYINVOIS_PORTAL_URL'),

    'sslcert_path'         => env('MYINVOIS_SSLCERT_PATH'),

    'signedsignature_path' => env('MYINVOIS_SIGNSIG_PATH'),

    'privatekey_path'      => env('MYINVOIS_PRIVATEKEY_PATH'),

    'tin'                  => env('MYINVOIS_SUPPLIER_TIN'),

    'ic'                   => env('MYINVOIS_SUPPLIER_IC'),

    /*
    |--------------------------------------------------------------------------
    | Local Validation
    |--------------------------------------------------------------------------
    |
    | field_rules: check LHDN field lengths, dates, code lists (unit, country,
    | currency, payment mode, tax type) and amount format before submitting.
    | Set to false only if a newly added LHDN code is rejected locally before
    | this library is updated; LHDN still validates on its side. SVDP rules
    | (1.2 unsigned / 1.3 signed, until 2027-12-31) are always checked.
    |
     */
    'validation'           => [
        'field_rules' => env('MYINVOIS_VALIDATE_FIELD_RULES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Intermediary Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to intermediary authentication and operations.
    |
     */
    'intermediary'         => [
        'enabled'      => env('MYINVOIS_INTERMEDIARY_ENABLED', false),
        'default_tin'  => env('MYINVOIS_DEFAULT_TAXPAYER_TIN'),
        'validate_tin' => env('MYINVOIS_VALIDATE_TIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authentication settings for the MyInvois identity service.
    | The client_id and client_secret are used for OAuth2 client credentials flow.
    |
     */
    'auth'                 => [
        'url'                  => env('MYINVOIS_AUTH_URL', \Nava\MyInvois\MyInvoisClient::IDENTITY_SANDBOX_URL),
        'token_ttl'            => env('MYINVOIS_AUTH_TOKEN_TTL', 3600),           // 1 hour
        'token_refresh_buffer' => env('MYINVOIS_AUTH_TOKEN_REFRESH_BUFFER', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching settings for auth tokens and other cacheable data.
    |
     */
    'cache'                => [
        'enabled' => env('MYINVOIS_CACHE_ENABLED', true),
        'store'   => env('MYINVOIS_CACHE_STORE', 'file'),
        'ttl'     => env('MYINVOIS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTP client settings for API requests.
    |
     */
    'http'                 => [
        'timeout'         => env('MYINVOIS_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('MYINVOIS_HTTP_CONNECT_TIMEOUT', 10),
        'retry'           => [
            'times' => env('MYINVOIS_HTTP_RETRY_TIMES', 3),
            'sleep' => env('MYINVOIS_HTTP_RETRY_SLEEP', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging settings for API requests and responses.
    |
     */
    'logging'              => [
        'enabled' => env('MYINVOIS_LOGGING_ENABLED', true),
        'channel' => env('MYINVOIS_LOG_CHANNEL', 'stack'),
    ],
];
