<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Failover Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the database failover strategy for the
    | Laravel Reverse Tender Platform. It manages the 3-tier database
    | architecture with automatic failover capabilities.
    |
    */

    'enabled' => env('DATABASE_FAILOVER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Connection Priority Order
    |--------------------------------------------------------------------------
    |
    | Define the order of database connections for failover. The system will
    | attempt connections in this order during failover scenarios.
    |
    */

    'connections' => [
        'primary' => env('DB_PRIMARY_CONNECTION', 'neon_postgresql'),
        'secondary' => env('DB_SECONDARY_CONNECTION', 'cloud_postgresql'),
        'fallback' => env('DB_FALLBACK_CONNECTION', 'mongodb_atlas'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for database health monitoring and failure detection.
    |
    */

    'health_check' => [
        'interval' => env('DB_HEALTH_CHECK_INTERVAL', 30), // seconds
        'timeout' => env('DB_HEALTH_CHECK_TIMEOUT', 5), // seconds
        'retry_attempts' => env('DB_HEALTH_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('DB_HEALTH_RETRY_DELAY', 1000), // milliseconds
        'failure_threshold' => env('DB_FAILURE_THRESHOLD', 3), // consecutive failures
        'recovery_threshold' => env('DB_RECOVERY_THRESHOLD', 2), // consecutive successes
    ],

    /*
    |--------------------------------------------------------------------------
    | Failover Behavior Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the system behaves during failover scenarios.
    |
    */

    'failover' => [
        'automatic' => env('DB_AUTOMATIC_FAILOVER', true),
        'switch_delay' => env('DB_FAILOVER_SWITCH_DELAY', 500), // milliseconds
        'max_attempts' => env('DB_FAILOVER_MAX_ATTEMPTS', 3),
        'circuit_breaker_timeout' => env('DB_CIRCUIT_BREAKER_TIMEOUT', 60), // seconds
        'enable_graceful_degradation' => env('DB_ENABLE_GRACEFUL_DEGRADATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Define service-specific failover rules and database preferences.
    |
    */

    'services' => [
        'auth-service' => [
            'database' => 'reverse_tender_auth',
            'allow_readonly_fallback' => true,
            'critical_operations' => ['login', 'register', 'password_reset'],
        ],
        'user-service' => [
            'database' => 'reverse_tender_users',
            'allow_readonly_fallback' => true,
            'critical_operations' => ['profile_update', 'verification'],
        ],
        'auction-service' => [
            'database' => 'reverse_tender',
            'allow_readonly_fallback' => false,
            'critical_operations' => ['bid_placement', 'auction_creation'],
        ],
        'bidding-service' => [
            'database' => 'reverse_tender_bidding',
            'allow_readonly_fallback' => false,
            'critical_operations' => ['bid_submission', 'bid_evaluation'],
        ],
        'payment-service' => [
            'database' => 'reverse_tender_payments',
            'allow_readonly_fallback' => true,
            'enable_write_buffering' => true,
            'critical_operations' => ['payment_processing', 'refund_processing'],
            'write_buffer_config' => [
                'queue_connection' => 'redis',
                'queue_name' => 'payment_write_operations',
                'max_buffer_size' => 5000,
                'buffer_timeout' => 120, // 2 minutes - strict for financial operations
                'replay_batch_size' => 50,
                'enable_idempotency' => true,
                'enable_encryption' => true, // Financial data encryption
            ],
            'operation_specific_rules' => [
                'payment_processing' => [
                    'consistency_level' => 'strict_acid',
                    'max_delay_seconds' => 5,
                    'enable_buffering' => true,
                    'priority' => 'critical',
                    'require_confirmation' => true,
                ],
                'refund_processing' => [
                    'consistency_level' => 'strong',
                    'max_delay_seconds' => 30,
                    'enable_buffering' => true,
                    'priority' => 'high',
                    'require_confirmation' => true,
                ],
            ],
        ],
        'order-service' => [
            'database' => 'reverse_tender_orders',
            'allow_readonly_fallback' => true,
            'critical_operations' => ['order_creation', 'status_update'],
        ],
        'notification-service' => [
            'database' => 'reverse_tender_notifications',
            'allow_readonly_fallback' => true,
            'critical_operations' => ['send_notification'],
        ],
        'analytics-service' => [
            'database' => 'reverse_tender_analytics',
            'allow_readonly_fallback' => true,
            'critical_operations' => [],
        ],
        'vin-ocr-service' => [
            'database' => 'reverse_tender_vehicles',
            'allow_readonly_fallback' => true,
            'critical_operations' => ['vin_processing'],
        ],
        'gateway-service' => [
            'database' => 'reverse_tender',
            'allow_readonly_fallback' => true,
            'critical_operations' => ['request_routing'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MongoDB Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for MongoDB Atlas fallback database operations.
    |
    */

    'mongodb_fallback' => [
        'enabled' => env('MONGODB_FALLBACK_ENABLED', true),
        'sync_strategy' => env('MONGODB_SYNC_STRATEGY', 'async'), // async, sync, manual
        'collection_mapping' => [
            'users' => 'user_profiles',
            'auctions' => 'auction_data',
            'bids' => 'bid_data',
            'orders' => 'order_data',
            'payments' => 'payment_transactions',
            'notifications' => 'notification_queue',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for failover event logging and monitoring.
    |
    */

    'logging' => [
        'enabled' => env('DB_FAILOVER_LOGGING_ENABLED', true),
        'channel' => env('DB_FAILOVER_LOG_CHANNEL', 'database_failover'),
        'level' => env('DB_FAILOVER_LOG_LEVEL', 'info'),
        'include_query_details' => env('DB_FAILOVER_LOG_QUERIES', false),
    ],

    'monitoring' => [
        'enabled' => env('DB_FAILOVER_MONITORING_ENABLED', true),
        'metrics_driver' => env('DB_FAILOVER_METRICS_DRIVER', 'prometheus'),
        'alert_webhook' => env('DB_FAILOVER_ALERT_WEBHOOK'),
        'dashboard_enabled' => env('DB_FAILOVER_DASHBOARD_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimization during failover scenarios.
    |
    */

    'performance' => [
        'connection_pooling' => env('DB_CONNECTION_POOLING_ENABLED', false),
        'pool_size' => env('DB_CONNECTION_POOL_SIZE', 10),
        'connection_timeout' => env('DB_CONNECTION_TIMEOUT', 30), // seconds
        'query_timeout' => env('DB_QUERY_TIMEOUT', 60), // seconds
        'enable_query_cache' => env('DB_ENABLE_QUERY_CACHE', true),
        'cache_ttl' => env('DB_QUERY_CACHE_TTL', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    |
    | Configuration for development and testing environments.
    |
    */

    'testing' => [
        'simulate_failures' => env('DB_SIMULATE_FAILURES', false),
        'failure_rate' => env('DB_FAILURE_SIMULATION_RATE', 0.1), // 10%
        'chaos_testing_enabled' => env('DB_CHAOS_TESTING_ENABLED', false),
        'mock_connections' => env('DB_MOCK_CONNECTIONS', false),
    ],
];
