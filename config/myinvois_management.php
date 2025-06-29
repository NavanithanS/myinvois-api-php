<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MyInvois Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the MyInvois management interface.
    |
    */

    'admin' => [
        'default_role' => env('MYINVOIS_ADMIN_DEFAULT_ROLE', 'developer'),
        'max_api_keys_per_user' => env('MYINVOIS_MAX_API_KEYS_PER_USER', 5),
        'api_key_default_expiry_days' => env('MYINVOIS_API_KEY_EXPIRY_DAYS', 365),
    ],

    'rate_limits' => [
        'default' => [
            'requests_per_minute' => env('MYINVOIS_DEFAULT_RATE_LIMIT', 60),
            'requests_per_hour' => env('MYINVOIS_DEFAULT_HOURLY_RATE_LIMIT', 1000),
            'requests_per_day' => env('MYINVOIS_DEFAULT_DAILY_RATE_LIMIT', 10000),
        ],
        'premium' => [
            'requests_per_minute' => env('MYINVOIS_PREMIUM_RATE_LIMIT', 120),
            'requests_per_hour' => env('MYINVOIS_PREMIUM_HOURLY_RATE_LIMIT', 5000),
            'requests_per_day' => env('MYINVOIS_PREMIUM_DAILY_RATE_LIMIT', 50000),
        ],
    ],

    'webhooks' => [
        'max_per_user' => env('MYINVOIS_MAX_WEBHOOKS_PER_USER', 10),
        'default_timeout' => env('MYINVOIS_WEBHOOK_TIMEOUT', 30),
        'max_retries' => env('MYINVOIS_WEBHOOK_MAX_RETRIES', 3),
        'retry_delays' => [1, 5, 30], // minutes
    ],

    'analytics' => [
        'retention_days' => env('MYINVOIS_ANALYTICS_RETENTION_DAYS', 90),
        'real_time_enabled' => env('MYINVOIS_REAL_TIME_ANALYTICS', true),
        'cache_ttl' => env('MYINVOIS_ANALYTICS_CACHE_TTL', 300), // 5 minutes
    ],

    'security' => [
        'api_key_encryption' => env('MYINVOIS_API_KEY_ENCRYPTION', true),
        'ip_whitelist_enabled' => env('MYINVOIS_IP_WHITELIST_ENABLED', false),
        'audit_logging' => env('MYINVOIS_AUDIT_LOGGING', true),
        'session_timeout' => env('MYINVOIS_SESSION_TIMEOUT', 480), // 8 hours
    ],

    'environments' => [
        'sandbox' => [
            'name' => 'Sandbox',
            'base_url' => env('MYINVOIS_SANDBOX_URL', 'https://preprod.myinvois.hasil.gov.my'),
            'identity_url' => env('MYINVOIS_SANDBOX_IDENTITY_URL', 'https://preprod-api.myinvois.hasil.gov.my'),
        ],
        'production' => [
            'name' => 'Production',
            'base_url' => env('MYINVOIS_PRODUCTION_URL', 'https://myinvois.hasil.gov.my'),
            'identity_url' => env('MYINVOIS_PRODUCTION_IDENTITY_URL', 'https://api.myinvois.hasil.gov.my'),
        ],
    ],

    'documentation' => [
        'auto_generate' => env('MYINVOIS_AUTO_GENERATE_DOCS', true),
        'include_examples' => env('MYINVOIS_INCLUDE_EXAMPLES', true),
        'cache_enabled' => env('MYINVOIS_DOCS_CACHE_ENABLED', true),
    ],
];