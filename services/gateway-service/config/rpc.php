<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RPC Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for RPC client connections to other services.
    | This configuration supports environment variable overrides for flexibility.
    |
    */

    'client' => [
        'timeout' => env('RPC_CLIENT_TIMEOUT', 30),
        'retry_attempts' => env('RPC_CLIENT_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('RPC_CLIENT_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Endpoints
    |--------------------------------------------------------------------------
    |
    | RPC endpoints for each service that the gateway communicates with.
    | Each service has multiple fallback URL configurations.
    |
    */

    'services' => [
        'auth' => [
            'url' => env('RPC_AUTH_SERVICE_URL', 
                env('AUTH_SERVICE_RPC_URL', 
                    env('AUTH_SERVICE_URL', 'http://auth-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_AUTH_SERVICE_TOKEN', env('AUTH_SERVICE_TOKEN')),
        ],

        'user' => [
            'url' => env('RPC_USER_SERVICE_URL', 
                env('USER_SERVICE_RPC_URL', 
                    env('USER_SERVICE_URL', 'http://user-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_USER_SERVICE_TOKEN', env('USER_SERVICE_TOKEN')),
        ],

        'order' => [
            'url' => env('RPC_ORDER_SERVICE_URL', 
                env('ORDER_SERVICE_RPC_URL', 
                    env('ORDER_SERVICE_URL', 'http://order-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_ORDER_SERVICE_TOKEN', env('ORDER_SERVICE_TOKEN')),
        ],

        'payment' => [
            'url' => env('RPC_PAYMENT_SERVICE_URL', 
                env('PAYMENT_SERVICE_RPC_URL', 
                    env('PAYMENT_SERVICE_URL', 'http://payment-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_PAYMENT_SERVICE_TOKEN', env('PAYMENT_SERVICE_TOKEN')),
        ],

        'bidding' => [
            'url' => env('RPC_BIDDING_SERVICE_URL', 
                env('BIDDING_SERVICE_RPC_URL', 
                    env('BIDDING_SERVICE_URL', 'http://bidding-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_BIDDING_SERVICE_TOKEN', env('BIDDING_SERVICE_TOKEN')),
        ],

        'auction' => [
            'url' => env('RPC_AUCTION_SERVICE_URL', 
                env('AUCTION_SERVICE_RPC_URL', 
                    env('AUCTION_SERVICE_URL', 'http://auction-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_AUCTION_SERVICE_TOKEN', env('AUCTION_SERVICE_TOKEN')),
        ],

        'notification' => [
            'url' => env('RPC_NOTIFICATION_SERVICE_URL', 
                env('NOTIFICATION_SERVICE_RPC_URL', 
                    env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_NOTIFICATION_SERVICE_TOKEN', env('NOTIFICATION_SERVICE_TOKEN')),
        ],

        'analytics' => [
            'url' => env('RPC_ANALYTICS_SERVICE_URL', 
                env('ANALYTICS_SERVICE_RPC_URL', 
                    env('ANALYTICS_SERVICE_URL', 'http://analytics-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_ANALYTICS_SERVICE_TOKEN', env('ANALYTICS_SERVICE_TOKEN')),
        ],

        'vin_ocr' => [
            'url' => env('RPC_VIN_OCR_SERVICE_URL', 
                env('VIN_OCR_SERVICE_RPC_URL', 
                    env('VIN_OCR_SERVICE_URL', 'http://vin-ocr-service:8080') . '/rpc'
                )
            ),
            'token' => env('RPC_VIN_OCR_SERVICE_TOKEN', env('VIN_OCR_SERVICE_TOKEN')),
        ],
    ],
];
