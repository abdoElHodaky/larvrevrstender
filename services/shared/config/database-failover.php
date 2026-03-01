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
            'allow_readonly_fallback' => false,
            'critical_operations' => ['payment_processing', 'refund_processing'],
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
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for database failover alerting and incident response.
    |
    */

    'alerting' => [
        'enabled' => env('DB_FAILOVER_ALERTING_ENABLED', true),
        'suppression_window' => env('DB_ALERT_SUPPRESSION_WINDOW', 300), // 5 minutes
        'dashboard_base_url' => env('DB_FAILOVER_DASHBOARD_URL', 'http://localhost:3000'),
        'logs_base_url' => env('DB_FAILOVER_LOGS_URL', 'http://localhost:5601'),
        
        'channels' => [
            [
                'type' => 'slack',
                'webhook_url' => env('SLACK_WEBHOOK_URL'),
                'severities' => ['critical', 'high', 'medium'],
                'enabled' => env('SLACK_ALERTS_ENABLED', false),
            ],
            [
                'type' => 'pagerduty',
                'integration_key' => env('PAGERDUTY_INTEGRATION_KEY'),
                'severities' => ['critical', 'high'],
                'enabled' => env('PAGERDUTY_ALERTS_ENABLED', false),
            ],
            [
                'type' => 'email',
                'recipients' => explode(',', env('ALERT_EMAIL_RECIPIENTS', '')),
                'severities' => ['critical', 'high', 'medium'],
                'enabled' => env('EMAIL_ALERTS_ENABLED', false),
            ],
            [
                'type' => 'webhook',
                'url' => env('ALERT_WEBHOOK_URL'),
                'headers' => [
                    'Authorization' => 'Bearer ' . env('ALERT_WEBHOOK_TOKEN'),
                    'Content-Type' => 'application/json',
                ],
                'severities' => ['critical', 'high', 'medium', 'low'],
                'enabled' => env('WEBHOOK_ALERTS_ENABLED', false),
            ],
            [
                'type' => 'teams',
                'webhook_url' => env('TEAMS_WEBHOOK_URL'),
                'severities' => ['critical', 'high', 'medium'],
                'enabled' => env('TEAMS_ALERTS_ENABLED', false),
            ],
        ],

        'escalation' => [
            'critical' => [
                'delay' => env('CRITICAL_ESCALATION_DELAY', 300), // 5 minutes
                'channels' => ['pagerduty', 'slack'],
                'recipients' => explode(',', env('CRITICAL_ESCALATION_RECIPIENTS', '')),
            ],
            'high' => [
                'delay' => env('HIGH_ESCALATION_DELAY', 900), // 15 minutes
                'channels' => ['slack', 'email'],
                'recipients' => explode(',', env('HIGH_ESCALATION_RECIPIENTS', '')),
            ],
            'medium' => [
                'delay' => env('MEDIUM_ESCALATION_DELAY', 1800), // 30 minutes
                'channels' => ['email'],
                'recipients' => explode(',', env('MEDIUM_ESCALATION_RECIPIENTS', '')),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic recovery and failback to primary connections.
    |
    */

    'recovery' => [
        'enabled' => env('DB_RECOVERY_ENABLED', true),
        'required_consecutive_successes' => env('DB_RECOVERY_REQUIRED_SUCCESSES', 3),
        'soak_time_minutes' => env('DB_RECOVERY_SOAK_TIME', 10),
        'check_interval_minutes' => env('DB_RECOVERY_CHECK_INTERVAL', 5),
        'gradual_migration_delay' => env('DB_RECOVERY_MIGRATION_DELAY', 30), // seconds
        
        'strategies' => [
            'primary' => env('DB_RECOVERY_PRIMARY_STRATEGY', 'validation_first'),
            'secondary' => env('DB_RECOVERY_SECONDARY_STRATEGY', 'gradual'),
            'fallback' => env('DB_RECOVERY_FALLBACK_STRATEGY', 'immediate'),
            'default' => env('DB_RECOVERY_DEFAULT_STRATEGY', 'immediate'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Coordination Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for multi-service coordination during failover events.
    |
    */

    'coordination' => [
        'enabled' => env('DB_COORDINATION_ENABLED', true),
        'notification_timeout' => env('DB_COORDINATION_NOTIFICATION_TIMEOUT', 10), // seconds
        'acknowledgment_timeout' => env('DB_COORDINATION_ACK_TIMEOUT', 30), // seconds
        'recovery_confirmation_timeout' => env('DB_COORDINATION_RECOVERY_TIMEOUT', 60), // seconds
        'stage_delay' => env('DB_COORDINATION_STAGE_DELAY', 10), // seconds
        
        'services' => [
            'auth-service' => [
                'name' => 'auth-service',
                'health_endpoint' => env('AUTH_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8000/health'),
                'coordination_endpoint' => env('AUTH_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8000/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql'],
                'recovery_priority' => 1, // Critical service
                'dependencies' => [],
            ],
            'user-service' => [
                'name' => 'user-service',
                'health_endpoint' => env('USER_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8001/health'),
                'coordination_endpoint' => env('USER_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8001/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql'],
                'recovery_priority' => 2,
                'dependencies' => ['auth-service'],
            ],
            'auction-service' => [
                'name' => 'auction-service',
                'health_endpoint' => env('AUCTION_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8002/health'),
                'coordination_endpoint' => env('AUCTION_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8002/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql', 'mongodb_atlas'],
                'recovery_priority' => 1, // Critical service
                'dependencies' => ['auth-service', 'user-service'],
            ],
            'bidding-service' => [
                'name' => 'bidding-service',
                'health_endpoint' => env('BIDDING_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8004/health'),
                'coordination_endpoint' => env('BIDDING_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8004/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql'],
                'recovery_priority' => 1, // Critical service
                'dependencies' => ['auction-service'],
            ],
            'payment-service' => [
                'name' => 'payment-service',
                'health_endpoint' => env('PAYMENT_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8005/health'),
                'coordination_endpoint' => env('PAYMENT_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8005/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql'],
                'recovery_priority' => 1, // Critical service
                'dependencies' => ['auth-service'],
            ],
            'order-service' => [
                'name' => 'order-service',
                'health_endpoint' => env('ORDER_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8006/health'),
                'coordination_endpoint' => env('ORDER_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8006/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql'],
                'recovery_priority' => 2,
                'dependencies' => ['payment-service', 'auction-service'],
            ],
            'notification-service' => [
                'name' => 'notification-service',
                'health_endpoint' => env('NOTIFICATION_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8007/health'),
                'coordination_endpoint' => env('NOTIFICATION_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8007/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql', 'mongodb_atlas'],
                'recovery_priority' => 3,
                'dependencies' => [],
            ],
            'analytics-service' => [
                'name' => 'analytics-service',
                'health_endpoint' => env('ANALYTICS_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8008/health'),
                'coordination_endpoint' => env('ANALYTICS_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8008/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql', 'mongodb_atlas'],
                'recovery_priority' => 4, // Non-critical
                'dependencies' => [],
            ],
            'vin-ocr-service' => [
                'name' => 'vin-ocr-service',
                'health_endpoint' => env('VIN_OCR_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8009/health'),
                'coordination_endpoint' => env('VIN_OCR_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8009/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql'],
                'recovery_priority' => 3,
                'dependencies' => [],
            ],
            'gateway-service' => [
                'name' => 'gateway-service',
                'health_endpoint' => env('GATEWAY_SERVICE_HEALTH_ENDPOINT', 'http://localhost:8003/health'),
                'coordination_endpoint' => env('GATEWAY_SERVICE_COORDINATION_ENDPOINT', 'http://localhost:8003/health/coordination'),
                'connections' => ['neon_postgresql', 'cloud_postgresql'],
                'recovery_priority' => 1, // Critical service
                'dependencies' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Consistency Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data consistency validation during failover scenarios.
    |
    */

    'consistency' => [
        'enabled' => env('DB_CONSISTENCY_VALIDATION_ENABLED', true),
        'critical_tables' => [
            'users',
            'auctions', 
            'bids',
            'payments',
            'orders',
            'user_profiles',
            'auction_items'
        ],
        'max_acceptable_lag' => env('DB_CONSISTENCY_MAX_LAG', 30), // seconds
        'max_sequence_lag' => env('DB_CONSISTENCY_MAX_SEQUENCE_LAG', 100),
        'validation_timeout' => env('DB_CONSISTENCY_VALIDATION_TIMEOUT', 300), // seconds
        'sample_size' => env('DB_CONSISTENCY_SAMPLE_SIZE', 100),
        'split_brain_check_interval' => env('DB_SPLIT_BRAIN_CHECK_INTERVAL', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Database-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for database-specific optimizations and health checks.
    |
    */

    'database_specific' => [
        'postgresql' => [
            'max_replica_lag' => env('DB_POSTGRESQL_MAX_REPLICA_LAG', 30), // seconds
            'max_locks' => env('DB_POSTGRESQL_MAX_LOCKS', 100),
            'max_query_time' => env('DB_POSTGRESQL_MAX_QUERY_TIME', 1000), // milliseconds
            'connection_pool_warning_threshold' => env('DB_POSTGRESQL_POOL_WARNING', 80), // percent
        ],
        'mongodb' => [
            'max_query_time' => env('DB_MONGODB_MAX_QUERY_TIME', 2000), // milliseconds
            'oplog_warning_threshold' => env('DB_MONGODB_OPLOG_WARNING', 80), // percent
            'replica_set_required' => env('DB_MONGODB_REPLICA_SET_REQUIRED', true),
            'write_concern_required' => env('DB_MONGODB_WRITE_CONCERN_REQUIRED', true),
        ],
        'mysql' => [
            'max_replica_lag' => env('DB_MYSQL_MAX_REPLICA_LAG', 30), // seconds
            'max_query_time' => env('DB_MYSQL_MAX_QUERY_TIME', 1000), // milliseconds
        ],
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
