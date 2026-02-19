<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RPC Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for RPC client connections and timeouts.
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
    | RPC service endpoints for inter-service communication.
    | Each service can have both RPC and REST endpoints configured.
    |
    */
    'services' => [
        'auth' => [
            'url' => env('RPC_AUTH_SERVICE_URL', env('AUTH_SERVICE_URL', 'http://auth-service:8080') . '/rpc'),
            'token' => env('RPC_AUTH_SERVICE_TOKEN', ''),
        ],
        'bidding' => [
            'url' => env('RPC_BIDDING_SERVICE_URL', env('BIDDING_SERVICE_URL', 'http://bidding-service:8080') . '/rpc'),
            'token' => env('RPC_BIDDING_SERVICE_TOKEN', ''),
        ],
        'order' => [
            'url' => env('RPC_ORDER_SERVICE_URL', env('ORDER_SERVICE_URL', 'http://order-service:8080') . '/rpc'),
            'token' => env('RPC_ORDER_SERVICE_TOKEN', ''),
        ],
        'user' => [
            'url' => env('RPC_USER_SERVICE_URL', env('USER_SERVICE_URL', 'http://user-service:8080') . '/rpc'),
            'token' => env('RPC_USER_SERVICE_TOKEN', ''),
        ],
        'notification' => [
            'url' => env('RPC_NOTIFICATION_SERVICE_URL', env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8080') . '/rpc'),
            'token' => env('RPC_NOTIFICATION_SERVICE_TOKEN', ''),
        ],
    ],
];
