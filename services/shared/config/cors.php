<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'rpc/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Correlation-ID',
        'X-Request-ID',
    ],

    'max_age' => 0,

    'supports_credentials' => false,

    /*
    |--------------------------------------------------------------------------
    | Service-Specific CORS Policies
    |--------------------------------------------------------------------------
    |
    | Define different CORS policies for different services or endpoints.
    | Each policy can override the default settings above.
    |
    */

    'policies' => [
        'default' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'X-Service-Auth',
                'X-Service-Token',
                'X-Correlation-ID',
                'X-Request-ID',
            ],
            'exposed_headers' => [
                'X-RateLimit-Limit',
                'X-RateLimit-Remaining',
                'X-RateLimit-Reset',
                'X-Correlation-ID',
                'X-Request-ID',
            ],
            'max_age' => 86400, // 24 hours
            'supports_credentials' => true,
        ],

        'auth-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
                'https://app.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'X-Device-ID',
                'X-App-Version',
            ],
            'supports_credentials' => true,
            'max_age' => 3600, // 1 hour for auth endpoints
        ],

        'user-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
                'https://app.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'supports_credentials' => true,
        ],

        'auction-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://app.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'supports_credentials' => true,
        ],

        'bidding-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://app.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'supports_credentials' => true,
            'exposed_headers' => [
                'X-RateLimit-Limit',
                'X-RateLimit-Remaining',
                'X-RateLimit-Reset',
                'X-Bid-Status',
                'X-Auction-Status',
            ],
        ],

        'payment-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'OPTIONS'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'X-Payment-Method',
                'X-Idempotency-Key',
            ],
            'supports_credentials' => true,
            'max_age' => 1800, // 30 minutes for payment endpoints
        ],

        'order-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
                'https://app.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'supports_credentials' => true,
        ],

        'notification-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
                'https://app.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'supports_credentials' => true,
            'exposed_headers' => [
                'X-RateLimit-Limit',
                'X-RateLimit-Remaining',
                'X-RateLimit-Reset',
                'X-Notification-Status',
            ],
        ],

        'analytics-service' => [
            'allowed_origins' => [
                'https://admin.reversetender.com',
                'https://analytics.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            'supports_credentials' => true,
            'max_age' => 7200, // 2 hours for analytics
        ],

        'vin-ocr-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://app.reversetender.com',
            ],
            'allowed_methods' => ['POST', 'OPTIONS'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'X-Upload-Type',
            ],
            'supports_credentials' => true,
            'max_age' => 3600, // 1 hour
        ],

        'gateway-service' => [
            'allowed_origins' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
                'https://app.reversetender.com',
                'https://api.reversetender.com',
            ],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'X-Service-Auth',
                'X-Service-Token',
                'X-Correlation-ID',
                'X-Request-ID',
                'X-Client-Version',
                'X-Platform',
            ],
            'exposed_headers' => [
                'X-RateLimit-Limit',
                'X-RateLimit-Remaining',
                'X-RateLimit-Reset',
                'X-Correlation-ID',
                'X-Request-ID',
                'X-Gateway-Version',
            ],
            'supports_credentials' => true,
            'max_age' => 86400, // 24 hours
        ],

        /*
        |--------------------------------------------------------------------------
        | Development Policies
        |--------------------------------------------------------------------------
        |
        | More permissive policies for development environments
        |
        */

        'development' => [
            'allowed_origins' => [
                'http://localhost:3000',
                'http://localhost:3001',
                'http://localhost:8080',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:3001',
                'http://127.0.0.1:8080',
                'https://localhost:3000',
                'https://localhost:3001',
                'https://localhost:8080',
            ],
            'allowed_origins_patterns' => [
                '/^http:\/\/localhost:\d+$/',
                '/^https:\/\/localhost:\d+$/',
                '/^http:\/\/127\.0\.0\.1:\d+$/',
                '/^https:\/\/127\.0\.0\.1:\d+$/',
            ],
            'allowed_methods' => ['*'],
            'allowed_headers' => ['*'],
            'supports_credentials' => true,
            'max_age' => 86400,
        ],

        /*
        |--------------------------------------------------------------------------
        | Testing Policies
        |--------------------------------------------------------------------------
        |
        | Policies for testing environments
        |
        */

        'testing' => [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['*'],
            'allowed_headers' => ['*'],
            'supports_credentials' => false,
            'max_age' => 0,
        ],
    ],
];
