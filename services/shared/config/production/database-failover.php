<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Failover Configuration - Production Environment
    |--------------------------------------------------------------------------
    |
    | This configuration file defines the production settings for the database
    | failover system. These settings are optimized for production workloads
    | with appropriate timeouts, retry counts, and notification settings.
    |
    */

    'enabled' => env('DATABASE_FAILOVER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Connection Health Check Settings
    |--------------------------------------------------------------------------
    */
    'health_check' => [
        'interval' => env('DB_HEALTH_CHECK_INTERVAL', 30), // seconds
        'timeout' => env('DB_HEALTH_CHECK_TIMEOUT', 5), // seconds
        'retry_count' => env('DB_HEALTH_CHECK_RETRY_COUNT', 3),
        'failure_threshold' => env('DB_HEALTH_FAILURE_THRESHOLD', 3), // consecutive failures
    ],

    /*
    |--------------------------------------------------------------------------
    | Failover Timing Configuration
    |--------------------------------------------------------------------------
    */
    'failover' => [
        'timeout' => env('DB_FAILOVER_TIMEOUT', 10), // seconds
        'retry_attempts' => env('DB_FAILOVER_RETRY_ATTEMPTS', 2),
        'backoff_multiplier' => env('DB_FAILOVER_BACKOFF_MULTIPLIER', 2),
        'max_backoff' => env('DB_FAILOVER_MAX_BACKOFF', 30), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Priority and Fallback Chain
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'primary' => [
            'name' => 'pgsql',
            'priority' => 1,
            'criticality' => 'critical',
            'max_connections' => env('DB_PRIMARY_MAX_CONNECTIONS', 100),
        ],
        'secondary' => [
            'name' => 'pgsql_secondary',
            'priority' => 2,
            'criticality' => 'high',
            'max_connections' => env('DB_SECONDARY_MAX_CONNECTIONS', 80),
        ],
        'fallback' => [
            'name' => 'mongodb',
            'priority' => 3,
            'criticality' => 'medium',
            'max_connections' => env('DB_FALLBACK_MAX_CONNECTIONS', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 10),
        'recovery_timeout' => env('CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 60), // seconds
        'half_open_max_calls' => env('CIRCUIT_BREAKER_HALF_OPEN_MAX_CALLS', 5),
        'expected_exception_types' => [
            'Illuminate\Database\QueryException',
            'PDOException',
            'MongoDB\Driver\Exception\ConnectionException',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Notification Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'email' => [
            'enabled' => env('DB_FAILOVER_EMAIL_NOTIFICATIONS', true),
            'recipients' => [
                'ops_team' => explode(',', env('DB_FAILOVER_OPS_TEAM_EMAILS', 'ops@company.com')),
                'engineering_leads' => explode(',', env('DB_FAILOVER_ENG_LEADS_EMAILS', 'engineering@company.com')),
                'on_call' => explode(',', env('DB_FAILOVER_ON_CALL_EMAILS', 'oncall@company.com')),
            ],
            'severity_routing' => [
                'critical' => ['ops_team', 'engineering_leads', 'on_call'],
                'high' => ['ops_team', 'engineering_leads'],
                'medium' => ['ops_team'],
                'low' => ['ops_team'],
                'info' => [],
            ],
            'rate_limiting' => [
                'max_emails_per_hour' => env('DB_FAILOVER_MAX_EMAILS_PER_HOUR', 10),
                'cooldown_period' => env('DB_FAILOVER_EMAIL_COOLDOWN', 300), // seconds
            ],
        ],
        'slack' => [
            'enabled' => env('DB_FAILOVER_SLACK_NOTIFICATIONS', false),
            'webhook_url' => env('DB_FAILOVER_SLACK_WEBHOOK_URL'),
            'channels' => [
                'critical' => env('DB_FAILOVER_SLACK_CRITICAL_CHANNEL', '#alerts-critical'),
                'high' => env('DB_FAILOVER_SLACK_HIGH_CHANNEL', '#alerts-high'),
                'medium' => env('DB_FAILOVER_SLACK_MEDIUM_CHANNEL', '#alerts-medium'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('DB_FAILOVER_LOGGING_ENABLED', true),
        'level' => env('DB_FAILOVER_LOG_LEVEL', 'info'),
        'channels' => [
            'default' => env('DB_FAILOVER_LOG_CHANNEL', 'stack'),
            'failover_events' => env('DB_FAILOVER_EVENTS_CHANNEL', 'database_failover'),
            'performance_metrics' => env('DB_FAILOVER_METRICS_CHANNEL', 'database_metrics'),
        ],
        'retention_days' => env('DB_FAILOVER_LOG_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Metrics
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => env('DB_FAILOVER_MONITORING_ENABLED', true),
        'metrics_collection_interval' => env('DB_FAILOVER_METRICS_INTERVAL', 60), // seconds
        'performance_tracking' => [
            'query_response_time_threshold' => env('DB_QUERY_RESPONSE_TIME_THRESHOLD', 1000), // milliseconds
            'connection_pool_usage_threshold' => env('DB_CONNECTION_POOL_THRESHOLD', 80), // percentage
        ],
        'alerting' => [
            'consecutive_failures_alert' => env('DB_CONSECUTIVE_FAILURES_ALERT', 5),
            'response_time_degradation_alert' => env('DB_RESPONSE_TIME_ALERT', 2000), // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery and Failback Settings
    |--------------------------------------------------------------------------
    */
    'recovery' => [
        'auto_failback_enabled' => env('DB_AUTO_FAILBACK_ENABLED', true),
        'failback_health_check_duration' => env('DB_FAILBACK_HEALTH_CHECK_DURATION', 300), // seconds
        'failback_success_threshold' => env('DB_FAILBACK_SUCCESS_THRESHOLD', 10), // consecutive successes
        'manual_intervention_required' => [
            'split_brain_detected',
            'data_consistency_issues_detected',
            'multiple_connection_failures',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Settings
    |--------------------------------------------------------------------------
    */
    'environment' => [
        'name' => env('APP_ENV', 'production'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'cluster_id' => env('DB_CLUSTER_ID', 'prod-cluster-01'),
        'service_version' => env('APP_VERSION', '1.0.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security and Compliance
    |--------------------------------------------------------------------------
    */
    'security' => [
        'encrypt_sensitive_logs' => env('DB_FAILOVER_ENCRYPT_LOGS', true),
        'audit_trail_enabled' => env('DB_FAILOVER_AUDIT_TRAIL', true),
        'pii_data_handling' => [
            'mask_connection_strings' => true,
            'mask_query_parameters' => true,
            'log_retention_compliance' => 'GDPR', // GDPR, CCPA, SOX
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'connection_pooling' => [
            'enabled' => env('DB_CONNECTION_POOLING_ENABLED', true),
            'min_connections' => env('DB_MIN_CONNECTIONS', 5),
            'max_connections' => env('DB_MAX_CONNECTIONS', 100),
            'idle_timeout' => env('DB_IDLE_TIMEOUT', 300), // seconds
        ],
        'query_optimization' => [
            'slow_query_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
            'query_cache_enabled' => env('DB_QUERY_CACHE_ENABLED', true),
            'prepared_statements' => env('DB_PREPARED_STATEMENTS', true),
        ],
    ],
];
