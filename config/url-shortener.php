<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Short Code Length
    |--------------------------------------------------------------------------
    | Length of the generated short code. 7 characters gives us ~3.5 trillion
    | unique combinations (62^7) while staying scannable as a URL.
    */
    'code_length' => (int) env('URL_SHORTENER_CODE_LENGTH', 7),

    /*
    |--------------------------------------------------------------------------
    | Shortener Domain
    |--------------------------------------------------------------------------
    | The base domain used when constructing short URLs.
    */
    'domain' => env('URL_SHORTENER_DOMAIN', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Max URLs Per User / IP (unauthenticated)
    |--------------------------------------------------------------------------
    */
    'max_per_user' => (int) env('URL_SHORTENER_MAX_PER_USER', 500),

    /*
    |--------------------------------------------------------------------------
    | Default Expiry
    |--------------------------------------------------------------------------
    | Default expiry in days when none is provided. Set to null for no expiry.
    */
    'default_expiry_days' => env('URL_SHORTENER_DEFAULT_EXPIRY_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (per minute)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'shorten'  => (int) env('RATE_LIMIT_SHORTEN', 10),
        'redirect' => (int) env('RATE_LIMIT_REDIRECT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'url_ttl'      => 3600,       // 1 hour per resolved URL
        'stats_ttl'    => 300,        // 5 mins for stats endpoint
        'url_list_ttl' => 60,         // 1 min for index listing
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Queue Name
    |--------------------------------------------------------------------------
    */
    'analytics_queue' => env('ANALYTICS_QUEUE', 'analytics'),

    /*
    |--------------------------------------------------------------------------
    | Geo IP Provider
    |--------------------------------------------------------------------------
    */
    'geo_ip' => [
        'provider' => env('GEO_IP_PROVIDER', 'ipapi'),
        'base_url' => env('IPAPI_BASE_URL', 'https://ipapi.co'),
        'timeout'  => 3,  // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved Codes (cannot be used as short codes)
    |--------------------------------------------------------------------------
    */
    'reserved_codes' => [
        'api', 'admin', 'docs', 'health', 'metrics', 'swagger',
        'login', 'register', 'logout', 'dashboard',
    ],
];
