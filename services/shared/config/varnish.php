<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Varnish Cache Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for Varnish cache integration.
    | These settings control how your Laravel application interacts with Varnish.
    |
    */

    'enabled' => env('VARNISH_ENABLED', false),

    'host' => env('VARNISH_HOST', 'varnish'),

    'port' => env('VARNISH_PORT', 80),

    'admin_port' => env('VARNISH_ADMIN_PORT', 6081),

    'ttl' => env('VARNISH_TTL', 300), // 5 minutes

    'grace' => env('VARNISH_GRACE', 3600), // 1 hour

    'public' => env('CACHE_CONTROL_PUBLIC', true),

    'etag' => env('ETAG_ENABLED', true),

    'last_modified' => env('LAST_MODIFIED_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL by Content Type
    |--------------------------------------------------------------------------
    |
    | Define different TTL values for different content types.
    |
    */

    'ttl_by_content_type' => [
        'text/html' => 300,        // 5 minutes
        'application/json' => 120, // 2 minutes
        'text/css' => 3600,        // 1 hour
        'application/javascript' => 3600, // 1 hour
        'image/png' => 86400,      // 24 hours
        'image/jpeg' => 86400,     // 24 hours
        'image/gif' => 86400,      // 24 hours
        'image/svg+xml' => 86400,  // 24 hours
        'application/pdf' => 86400, // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Rules
    |--------------------------------------------------------------------------
    |
    | Define which routes should be cached and which should not.
    |
    */

    'cache_rules' => [
        // Routes that should never be cached
        'never_cache' => [
            '/api/auth/*',
            '/api/*/create',
            '/api/*/update',
            '/api/*/delete',
            '/api/*/store',
            '/admin/*',
            '/private/*',
        ],

        // Routes that should be cached with specific TTL
        'cache_with_ttl' => [
            '/api/auctions' => 300,     // 5 minutes
            '/api/users' => 600,        // 10 minutes
            '/api/analytics' => 1800,   // 30 minutes
            '/health' => 60,            // 1 minute
            '/status' => 60,            // 1 minute
        ],

        // Static assets (cached for longer)
        'static_assets' => [
            '*.css',
            '*.js',
            '*.png',
            '*.jpg',
            '*.jpeg',
            '*.gif',
            '*.svg',
            '*.ico',
            '*.pdf',
            '*.woff',
            '*.woff2',
            '*.ttf',
            '*.eot',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Tags
    |--------------------------------------------------------------------------
    |
    | Define cache tags for different types of content to enable
    | selective cache invalidation.
    |
    */

    'tags' => [
        'auctions' => 'auction',
        'users' => 'user',
        'bids' => 'bid',
        'orders' => 'order',
        'payments' => 'payment',
        'notifications' => 'notification',
        'analytics' => 'analytics',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purge Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for cache purging and invalidation.
    |
    */

    'purge' => [
        'enabled' => env('VARNISH_PURGE_ENABLED', true),
        
        'allowed_ips' => [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ],

        'timeout' => 5, // seconds

        'retry_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    |
    | Configuration for Varnish health checks.
    |
    */

    'health_check' => [
        'enabled' => true,
        'url' => '/health',
        'timeout' => 5,
        'interval' => 10,
        'window' => 5,
        'threshold' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging
    |--------------------------------------------------------------------------
    |
    | Enable debugging headers and logging for cache operations.
    |
    */

    'debug' => [
        'enabled' => env('VARNISH_DEBUG', env('APP_DEBUG', false)),
        'headers' => true,
        'log_hits' => false,
        'log_misses' => false,
        'log_purges' => true,
    ],
];
