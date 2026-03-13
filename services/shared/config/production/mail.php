<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mail Configuration - Production Environment
    |--------------------------------------------------------------------------
    |
    | Production mail configuration optimized for SMTP2Go service with
    | database failover notification settings.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp2go'),

    'mailers' => [
        'smtp2go' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'mail.smtp2go.com'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => env('MAIL_TIMEOUT', 30),
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'failover_smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_FAILOVER_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_FAILOVER_PORT', 587),
            'encryption' => env('MAIL_FAILOVER_ENCRYPTION', 'tls'),
            'username' => env('MAIL_FAILOVER_USERNAME'),
            'password' => env('MAIL_FAILOVER_PASSWORD'),
            'timeout' => env('MAIL_FAILOVER_TIMEOUT', 30),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'alerts@company.com'),
        'name' => env('MAIL_FROM_NAME', 'Database Failover System'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Failover Email Configuration
    |--------------------------------------------------------------------------
    */
    'database_failover' => [
        'from' => [
            'address' => env('DB_FAILOVER_FROM_ADDRESS', 'database-alerts@company.com'),
            'name' => env('DB_FAILOVER_FROM_NAME', 'Database Failover System'),
        ],
        'reply_to' => [
            'address' => env('DB_FAILOVER_REPLY_TO', 'ops-team@company.com'),
            'name' => env('DB_FAILOVER_REPLY_TO_NAME', 'Operations Team'),
        ],
        'queue' => env('DB_FAILOVER_MAIL_QUEUE', 'emails'),
        'connection' => env('DB_FAILOVER_QUEUE_CONNECTION', 'redis'),
        'retry_attempts' => env('DB_FAILOVER_MAIL_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('DB_FAILOVER_MAIL_RETRY_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Email Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'from' => [
            'address' => env('CIRCUIT_BREAKER_FROM_ADDRESS', 'circuit-breaker-alerts@company.com'),
            'name' => env('CIRCUIT_BREAKER_FROM_NAME', 'Circuit Breaker System'),
        ],
        'queue' => env('CIRCUIT_BREAKER_MAIL_QUEUE', 'emails'),
        'connection' => env('CIRCUIT_BREAKER_QUEUE_CONNECTION', 'redis'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Topology Report Email Configuration
    |--------------------------------------------------------------------------
    */
    'topology_reports' => [
        'from' => [
            'address' => env('TOPOLOGY_REPORT_FROM_ADDRESS', 'topology-reports@company.com'),
            'name' => env('TOPOLOGY_REPORT_FROM_NAME', 'Database Topology System'),
        ],
        'queue' => env('TOPOLOGY_REPORT_MAIL_QUEUE', 'reports'),
        'connection' => env('TOPOLOGY_REPORT_QUEUE_CONNECTION', 'redis'),
        'schedule' => [
            'daily_summary' => env('TOPOLOGY_DAILY_SUMMARY_TIME', '08:00'),
            'weekly_report' => env('TOPOLOGY_WEEKLY_REPORT_DAY', 'monday'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Templates Configuration
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'database_failover' => [
            'alert' => 'emails.database-failover.alert',
            'plain_text' => 'emails.database-failover.alert-plain',
        ],
        'circuit_breaker' => [
            'alert' => 'emails.circuit-breaker.alert',
            'plain_text' => 'emails.circuit-breaker.alert-plain',
        ],
        'topology_report' => [
            'daily_summary' => 'emails.database-topology.daily-summary',
            'topology_mapping' => 'emails.database-topology.topology-mapping',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting and Throttling
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => env('MAIL_RATE_LIMITING_ENABLED', true),
        'max_emails_per_minute' => env('MAIL_MAX_PER_MINUTE', 10),
        'max_emails_per_hour' => env('MAIL_MAX_PER_HOUR', 100),
        'cooldown_period' => env('MAIL_COOLDOWN_PERIOD', 300), // seconds
        'burst_limit' => env('MAIL_BURST_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Delivery Monitoring
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'track_opens' => env('MAIL_TRACK_OPENS', false),
        'track_clicks' => env('MAIL_TRACK_CLICKS', false),
        'delivery_notifications' => env('MAIL_DELIVERY_NOTIFICATIONS', true),
        'bounce_handling' => env('MAIL_BOUNCE_HANDLING', true),
        'webhook_url' => env('MAIL_WEBHOOK_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Failover and Redundancy
    |--------------------------------------------------------------------------
    */
    'failover' => [
        'enabled' => env('MAIL_FAILOVER_ENABLED', true),
        'primary_failure_threshold' => env('MAIL_PRIMARY_FAILURE_THRESHOLD', 3),
        'failover_timeout' => env('MAIL_FAILOVER_TIMEOUT', 30), // seconds
        'health_check_interval' => env('MAIL_HEALTH_CHECK_INTERVAL', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'encrypt_queue_payloads' => env('MAIL_ENCRYPT_QUEUE_PAYLOADS', true),
        'verify_ssl' => env('MAIL_VERIFY_SSL', true),
        'allowed_recipients' => env('MAIL_ALLOWED_RECIPIENTS') ? 
            explode(',', env('MAIL_ALLOWED_RECIPIENTS')) : null,
        'blocked_domains' => env('MAIL_BLOCKED_DOMAINS') ? 
            explode(',', env('MAIL_BLOCKED_DOMAINS')) : [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing
    |--------------------------------------------------------------------------
    */
    'testing' => [
        'log_all_emails' => env('MAIL_LOG_ALL_EMAILS', false),
        'test_mode' => env('MAIL_TEST_MODE', false),
        'test_recipient' => env('MAIL_TEST_RECIPIENT'),
        'suppress_delivery' => env('MAIL_SUPPRESS_DELIVERY', false),
    ],
];
