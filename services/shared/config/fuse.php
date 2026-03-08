<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel Fuse Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file defines the circuit breaker settings for the
    | reverse tender platform's microservices. Each service can have its own
    | thresholds and timeouts based on criticality and expected behavior.
    |
    */

    'enabled' => env('FUSE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Circuit Breaker Settings
    |--------------------------------------------------------------------------
    |
    | These are the default settings that will be used for any service that
    | doesn't have specific configuration defined in the services array below.
    |
    */

    'default_threshold' => env('FUSE_DEFAULT_THRESHOLD', 50), // 50% failure rate
    'default_timeout' => env('FUSE_DEFAULT_TIMEOUT', 60), // 60 seconds recovery test
    'default_min_requests' => env('FUSE_DEFAULT_MIN_REQUESTS', 10), // Minimum requests before evaluation

    /*
    |--------------------------------------------------------------------------
    | Service-Specific Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configure circuit breaker settings for each microservice based on their
    | criticality and expected behavior patterns. Critical services like
    | payments have lower thresholds for faster protection.
    |
    */

    'services' => [
        /*
        |--------------------------------------------------------------------------
        | Payment Service - Critical Financial Operations
        |--------------------------------------------------------------------------
        */
        'payment' => [
            'threshold' => env('FUSE_PAYMENT_THRESHOLD', 30), // 30% - Very sensitive
            'timeout' => env('FUSE_PAYMENT_TIMEOUT', 15), // 15s - Quick recovery test
            'min_requests' => env('FUSE_PAYMENT_MIN_REQUESTS', 5), // Low threshold for quick detection
            'peak_hours_threshold' => env('FUSE_PAYMENT_PEAK_THRESHOLD', 40), // 40% during business hours
            'peak_hours_start' => env('FUSE_PAYMENT_PEAK_START', 9), // 9 AM
            'peak_hours_end' => env('FUSE_PAYMENT_PEAK_END', 17), // 5 PM
        ],

        'stripe' => [
            'threshold' => env('FUSE_STRIPE_THRESHOLD', 30),
            'timeout' => env('FUSE_STRIPE_TIMEOUT', 15),
            'min_requests' => env('FUSE_STRIPE_MIN_REQUESTS', 5),
        ],

        'escrow' => [
            'threshold' => env('FUSE_ESCROW_THRESHOLD', 35),
            'timeout' => env('FUSE_ESCROW_TIMEOUT', 20),
            'min_requests' => env('FUSE_ESCROW_MIN_REQUESTS', 5),
        ],

        /*
        |--------------------------------------------------------------------------
        | Notification Services - Multi-Channel Delivery
        |--------------------------------------------------------------------------
        */
        'notification' => [
            'threshold' => env('FUSE_NOTIFICATION_THRESHOLD', 60), // 60% - More tolerant
            'timeout' => env('FUSE_NOTIFICATION_TIMEOUT', 45), // 45s recovery
            'min_requests' => env('FUSE_NOTIFICATION_MIN_REQUESTS', 8),
        ],

        'email' => [
            'threshold' => env('FUSE_EMAIL_THRESHOLD', 70), // 70% - Non-critical
            'timeout' => env('FUSE_EMAIL_TIMEOUT', 60), // 1 minute
            'min_requests' => env('FUSE_EMAIL_MIN_REQUESTS', 10),
        ],

        'mailgun' => [
            'threshold' => env('FUSE_MAILGUN_THRESHOLD', 70),
            'timeout' => env('FUSE_MAILGUN_TIMEOUT', 60),
            'min_requests' => env('FUSE_MAILGUN_MIN_REQUESTS', 10),
        ],

        'sms' => [
            'threshold' => env('FUSE_SMS_THRESHOLD', 65),
            'timeout' => env('FUSE_SMS_TIMEOUT', 45),
            'min_requests' => env('FUSE_SMS_MIN_REQUESTS', 8),
        ],

        'sms_providers' => [
            'threshold' => env('FUSE_SMS_THRESHOLD', 65),
            'timeout' => env('FUSE_SMS_TIMEOUT', 45),
            'min_requests' => env('FUSE_SMS_MIN_REQUESTS', 8),
        ],

        'push' => [
            'threshold' => env('FUSE_PUSH_THRESHOLD', 75),
            'timeout' => env('FUSE_PUSH_TIMEOUT', 30),
            'min_requests' => env('FUSE_PUSH_MIN_REQUESTS', 10),
        ],

        'signal' => [
            'threshold' => env('FUSE_SIGNAL_THRESHOLD', 80),
            'timeout' => env('FUSE_SIGNAL_TIMEOUT', 90),
            'min_requests' => env('FUSE_SIGNAL_MIN_REQUESTS', 5),
        ],

        'telegram' => [
            'threshold' => env('FUSE_TELEGRAM_THRESHOLD', 80),
            'timeout' => env('FUSE_TELEGRAM_TIMEOUT', 90),
            'min_requests' => env('FUSE_TELEGRAM_MIN_REQUESTS', 5),
        ],

        'whatsapp' => [
            'threshold' => env('FUSE_WHATSAPP_THRESHOLD', 75),
            'timeout' => env('FUSE_WHATSAPP_TIMEOUT', 60),
            'min_requests' => env('FUSE_WHATSAPP_MIN_REQUESTS', 8),
        ],

        /*
        |--------------------------------------------------------------------------
        | Bidding Service - Real-time Auction Processing
        |--------------------------------------------------------------------------
        */
        'bidding' => [
            'threshold' => env('FUSE_BIDDING_THRESHOLD', 40), // 40% - Moderately sensitive
            'timeout' => env('FUSE_BIDDING_TIMEOUT', 30), // 30s recovery
            'min_requests' => env('FUSE_BIDDING_MIN_REQUESTS', 8),
            'peak_hours_threshold' => env('FUSE_BIDDING_PEAK_THRESHOLD', 50), // 50% during peak
            'peak_hours_start' => env('FUSE_BIDDING_PEAK_START', 8), // 8 AM
            'peak_hours_end' => env('FUSE_BIDDING_PEAK_END', 20), // 8 PM
        ],

        /*
        |--------------------------------------------------------------------------
        | VIN-OCR Service - Image Processing
        |--------------------------------------------------------------------------
        */
        'vin-ocr' => [
            'threshold' => env('FUSE_VIN_OCR_THRESHOLD', 55), // 55% - Processing intensive
            'timeout' => env('FUSE_VIN_OCR_TIMEOUT', 120), // 2 minutes - Longer recovery
            'min_requests' => env('FUSE_VIN_OCR_MIN_REQUESTS', 5),
        ],

        'ocr' => [
            'threshold' => env('FUSE_OCR_THRESHOLD', 60),
            'timeout' => env('FUSE_OCR_TIMEOUT', 90),
            'min_requests' => env('FUSE_OCR_MIN_REQUESTS', 5),
        ],

        /*
        |--------------------------------------------------------------------------
        | Analytics Service - Data Processing
        |--------------------------------------------------------------------------
        */
        'analytics' => [
            'threshold' => env('FUSE_ANALYTICS_THRESHOLD', 70), // 70% - Non-critical
            'timeout' => env('FUSE_ANALYTICS_TIMEOUT', 90), // 90s recovery
            'min_requests' => env('FUSE_ANALYTICS_MIN_REQUESTS', 15),
        ],

        /*
        |--------------------------------------------------------------------------
        | Authentication Service - User Security
        |--------------------------------------------------------------------------
        */
        'auth' => [
            'threshold' => env('FUSE_AUTH_THRESHOLD', 35), // 35% - Security critical
            'timeout' => env('FUSE_AUTH_TIMEOUT', 20), // 20s quick recovery
            'min_requests' => env('FUSE_AUTH_MIN_REQUESTS', 5),
        ],

        'otp' => [
            'threshold' => env('FUSE_OTP_THRESHOLD', 40),
            'timeout' => env('FUSE_OTP_TIMEOUT', 30),
            'min_requests' => env('FUSE_OTP_MIN_REQUESTS', 5),
        ],

        /*
        |--------------------------------------------------------------------------
        | User Service - Profile Management
        |--------------------------------------------------------------------------
        */
        'user' => [
            'threshold' => env('FUSE_USER_THRESHOLD', 50), // 50% - Standard
            'timeout' => env('FUSE_USER_TIMEOUT', 45), // 45s recovery
            'min_requests' => env('FUSE_USER_MIN_REQUESTS', 10),
        ],

        'kyc' => [
            'threshold' => env('FUSE_KYC_THRESHOLD', 45), // 45% - Important for compliance
            'timeout' => env('FUSE_KYC_TIMEOUT', 60), // 1 minute
            'min_requests' => env('FUSE_KYC_MIN_REQUESTS', 5),
        ],

        /*
        |--------------------------------------------------------------------------
        | External Services - Third-party Integrations
        |--------------------------------------------------------------------------
        */
        'aws' => [
            'threshold' => env('FUSE_AWS_THRESHOLD', 40),
            'timeout' => env('FUSE_AWS_TIMEOUT', 30),
            'min_requests' => env('FUSE_AWS_MIN_REQUESTS', 8),
        ],

        'google' => [
            'threshold' => env('FUSE_GOOGLE_THRESHOLD', 50),
            'timeout' => env('FUSE_GOOGLE_TIMEOUT', 45),
            'min_requests' => env('FUSE_GOOGLE_MIN_REQUESTS', 10),
        ],

        /*
        |--------------------------------------------------------------------------
        | Default Service Configuration
        |--------------------------------------------------------------------------
        */
        'default' => [
            'threshold' => env('FUSE_DEFAULT_SERVICE_THRESHOLD', 50),
            'timeout' => env('FUSE_DEFAULT_SERVICE_TIMEOUT', 60),
            'min_requests' => env('FUSE_DEFAULT_SERVICE_MIN_REQUESTS', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Query Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configure circuit breaker settings specifically for database queries.
    | These settings provide protection at the query level to prevent
    | cascading failures when database operations become unreliable.
    |
    */

    'query_defaults' => [
        'failure_threshold' => env('FUSE_QUERY_FAILURE_THRESHOLD', 5), // Number of failures before opening
        'recovery_timeout' => env('FUSE_QUERY_RECOVERY_TIMEOUT', 30), // Seconds before attempting recovery
        'expected_exceptions' => [
            \Illuminate\Database\QueryException::class,
            \PDOException::class,
            \Illuminate\Database\ConnectionException::class,
            \Illuminate\Database\DeadlockException::class,
        ],
        'success_threshold' => env('FUSE_QUERY_SUCCESS_THRESHOLD', 3), // Successes needed to close circuit
        'timeout' => env('FUSE_QUERY_TIMEOUT', 10), // Query timeout in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Type Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Different query types may have different failure tolerances and
    | recovery requirements. Configure them individually here.
    |
    */

    'query_types' => [
        'select' => [
            'failure_threshold' => env('FUSE_SELECT_FAILURE_THRESHOLD', 8), // More tolerant for reads
            'recovery_timeout' => env('FUSE_SELECT_RECOVERY_TIMEOUT', 20),
            'timeout' => env('FUSE_SELECT_TIMEOUT', 15),
        ],
        
        'insert' => [
            'failure_threshold' => env('FUSE_INSERT_FAILURE_THRESHOLD', 3), // Less tolerant for writes
            'recovery_timeout' => env('FUSE_INSERT_RECOVERY_TIMEOUT', 45),
            'timeout' => env('FUSE_INSERT_TIMEOUT', 10),
        ],
        
        'update' => [
            'failure_threshold' => env('FUSE_UPDATE_FAILURE_THRESHOLD', 3), // Less tolerant for writes
            'recovery_timeout' => env('FUSE_UPDATE_RECOVERY_TIMEOUT', 45),
            'timeout' => env('FUSE_UPDATE_TIMEOUT', 10),
        ],
        
        'delete' => [
            'failure_threshold' => env('FUSE_DELETE_FAILURE_THRESHOLD', 2), // Very sensitive for deletes
            'recovery_timeout' => env('FUSE_DELETE_RECOVERY_TIMEOUT', 60),
            'timeout' => env('FUSE_DELETE_TIMEOUT', 8),
        ],
        
        'transaction' => [
            'failure_threshold' => env('FUSE_TRANSACTION_FAILURE_THRESHOLD', 2), // Very sensitive for transactions
            'recovery_timeout' => env('FUSE_TRANSACTION_RECOVERY_TIMEOUT', 60),
            'timeout' => env('FUSE_TRANSACTION_TIMEOUT', 30),
        ],
        
        'statement' => [
            'failure_threshold' => env('FUSE_STATEMENT_FAILURE_THRESHOLD', 5),
            'recovery_timeout' => env('FUSE_STATEMENT_RECOVERY_TIMEOUT', 30),
            'timeout' => env('FUSE_STATEMENT_TIMEOUT', 15),
        ],
        
        'eloquent' => [
            'failure_threshold' => env('FUSE_ELOQUENT_FAILURE_THRESHOLD', 6),
            'recovery_timeout' => env('FUSE_ELOQUENT_RECOVERY_TIMEOUT', 25),
            'timeout' => env('FUSE_ELOQUENT_TIMEOUT', 12),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection-Specific Query Configuration
    |--------------------------------------------------------------------------
    |
    | Configure circuit breaker settings per database connection.
    | Critical connections like payment databases should have stricter settings.
    |
    */

    'query_connections' => [
        'mysql' => [
            'failure_threshold' => env('FUSE_MYSQL_QUERY_FAILURE_THRESHOLD', 5),
            'recovery_timeout' => env('FUSE_MYSQL_QUERY_RECOVERY_TIMEOUT', 30),
        ],
        
        'postgresql' => [
            'failure_threshold' => env('FUSE_POSTGRESQL_QUERY_FAILURE_THRESHOLD', 5),
            'recovery_timeout' => env('FUSE_POSTGRESQL_QUERY_RECOVERY_TIMEOUT', 30),
        ],
        
        'mongodb' => [
            'failure_threshold' => env('FUSE_MONGODB_QUERY_FAILURE_THRESHOLD', 8), // More tolerant for NoSQL
            'recovery_timeout' => env('FUSE_MONGODB_QUERY_RECOVERY_TIMEOUT', 20),
        ],
        
        'redis' => [
            'failure_threshold' => env('FUSE_REDIS_QUERY_FAILURE_THRESHOLD', 10), // Very tolerant for cache
            'recovery_timeout' => env('FUSE_REDIS_QUERY_RECOVERY_TIMEOUT', 15),
        ],
        
        // Critical database connections
        'payment_db' => [
            'failure_threshold' => env('FUSE_PAYMENT_DB_FAILURE_THRESHOLD', 2), // Very strict
            'recovery_timeout' => env('FUSE_PAYMENT_DB_RECOVERY_TIMEOUT', 60),
        ],
        
        'audit_db' => [
            'failure_threshold' => env('FUSE_AUDIT_DB_FAILURE_THRESHOLD', 3), // Strict for audit
            'recovery_timeout' => env('FUSE_AUDIT_DB_RECOVERY_TIMEOUT', 45),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable event dispatching for circuit breaker state changes.
    | These events can be used for monitoring, alerting, and logging.
    |
    */

    'events' => [
        'enabled' => env('FUSE_EVENTS_ENABLED', true),
        'dispatch_opened' => env('FUSE_DISPATCH_OPENED', true),
        'dispatch_half_open' => env('FUSE_DISPATCH_HALF_OPEN', true),
        'dispatch_closed' => env('FUSE_DISPATCH_CLOSED', true),
        'dispatch_query_events' => env('FUSE_DISPATCH_QUERY_EVENTS', true), // Query-specific events
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and logging behavior for circuit breaker operations.
    |
    */

    'monitoring' => [
        'log_state_changes' => env('FUSE_LOG_STATE_CHANGES', true),
        'log_level' => env('FUSE_LOG_LEVEL', 'info'),
        'metrics_enabled' => env('FUSE_METRICS_ENABLED', true),
    ],
];
