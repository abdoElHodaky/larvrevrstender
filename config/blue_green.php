<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blue-Green Deployment Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for blue-green deployment
    | including migration coordination, health checks, and deployment settings.
    |
    */

    'migrations' => [
        /*
        |--------------------------------------------------------------------------
        | Migration Timeout
        |--------------------------------------------------------------------------
        |
        | Maximum time in seconds to wait for a migration to complete.
        | If a migration takes longer than this, it will be considered failed.
        |
        */
        'timeout' => env('BLUE_GREEN_MIGRATION_TIMEOUT', 300),

        /*
        |--------------------------------------------------------------------------
        | Migration Batch Size
        |--------------------------------------------------------------------------
        |
        | Number of migrations to process in a single batch.
        | Smaller batches provide better progress tracking but may be slower.
        |
        */
        'batch_size' => env('BLUE_GREEN_MIGRATION_BATCH_SIZE', 100),

        /*
        |--------------------------------------------------------------------------
        | Backward Compatibility Validation
        |--------------------------------------------------------------------------
        |
        | Enable validation of migrations for backward compatibility.
        | When enabled, migrations that could break the old environment
        | will be rejected.
        |
        */
        'validation_enabled' => env('BLUE_GREEN_MIGRATION_VALIDATION', true),

        /*
        |--------------------------------------------------------------------------
        | Migration Rollback
        |--------------------------------------------------------------------------
        |
        | Enable rollback functionality for migrations.
        | When disabled, rollback operations will be rejected.
        |
        */
        'rollback_enabled' => env('BLUE_GREEN_MIGRATION_ROLLBACK', true),

        /*
        |--------------------------------------------------------------------------
        | Breaking Change Patterns
        |--------------------------------------------------------------------------
        |
        | SQL patterns that are considered potentially breaking changes
        | and should be flagged during backward compatibility validation.
        |
        */
        'breaking_patterns' => [
            '/DROP\s+TABLE/i',
            '/DROP\s+COLUMN/i',
            '/ALTER\s+TABLE.*DROP/i',
            '/RENAME\s+TABLE/i',
            '/RENAME\s+COLUMN/i',
            '/ALTER\s+TABLE.*CHANGE/i',
            '/ALTER\s+TABLE.*MODIFY/i',
        ],

        /*
        |--------------------------------------------------------------------------
        | Safe Change Patterns
        |--------------------------------------------------------------------------
        |
        | SQL patterns that are considered safe for blue-green deployments
        | and can be applied without breaking backward compatibility.
        |
        */
        'safe_patterns' => [
            '/CREATE\s+TABLE/i',
            '/ALTER\s+TABLE.*ADD/i',
            '/CREATE\s+INDEX/i',
            '/INSERT\s+INTO/i',
            '/UPDATE.*SET/i',
        ],
    ],

    'deployment' => [
        /*
        |--------------------------------------------------------------------------
        | Environment Colors
        |--------------------------------------------------------------------------
        |
        | Valid environment colors for blue-green deployment.
        | Only these values are accepted for environment identification.
        |
        */
        'colors' => ['blue', 'green'],

        /*
        |--------------------------------------------------------------------------
        | Health Check Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for health checks during blue-green deployments.
        |
        */
        'health_check' => [
            'timeout' => env('BLUE_GREEN_HEALTH_TIMEOUT', 300),
            'interval' => env('BLUE_GREEN_HEALTH_INTERVAL', 10),
            'retries' => env('BLUE_GREEN_HEALTH_RETRIES', 3),
            'required_services' => env('BLUE_GREEN_MIN_HEALTHY_SERVICES', 7),
        ],

        /*
        |--------------------------------------------------------------------------
        | Traffic Switch Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for traffic switching during deployments.
        |
        */
        'traffic_switch' => [
            'timeout' => env('BLUE_GREEN_TRAFFIC_TIMEOUT', 60),
            'validation_timeout' => env('BLUE_GREEN_TRAFFIC_VALIDATION_TIMEOUT', 30),
            'max_error_rate' => env('BLUE_GREEN_MAX_ERROR_RATE', 5.0),
            'min_success_rate' => env('BLUE_GREEN_MIN_SUCCESS_RATE', 95.0),
        ],

        /*
        |--------------------------------------------------------------------------
        | Rollback Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for automatic rollback during failed deployments.
        |
        */
        'rollback' => [
            'enabled' => env('BLUE_GREEN_ROLLBACK_ENABLED', true),
            'timeout' => env('BLUE_GREEN_ROLLBACK_TIMEOUT', 120),
            'max_attempts' => env('BLUE_GREEN_ROLLBACK_MAX_ATTEMPTS', 3),
            'trigger_on_error_rate' => env('BLUE_GREEN_ROLLBACK_ERROR_THRESHOLD', 10.0),
        ],
    ],

    'monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Metrics Collection
        |--------------------------------------------------------------------------
        |
        | Configuration for collecting deployment and migration metrics.
        |
        */
        'metrics' => [
            'enabled' => env('BLUE_GREEN_METRICS_ENABLED', true),
            'retention_days' => env('BLUE_GREEN_METRICS_RETENTION', 30),
            'export_interval' => env('BLUE_GREEN_METRICS_EXPORT_INTERVAL', 60),
        ],

        /*
        |--------------------------------------------------------------------------
        | Alerting Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for alerts during blue-green deployments.
        |
        */
        'alerts' => [
            'enabled' => env('BLUE_GREEN_ALERTS_ENABLED', true),
            'channels' => [
                'slack' => env('BLUE_GREEN_SLACK_WEBHOOK'),
                'email' => env('BLUE_GREEN_ALERT_EMAIL'),
            ],
            'thresholds' => [
                'deployment_duration' => env('BLUE_GREEN_ALERT_DEPLOYMENT_DURATION', 600), // 10 minutes
                'error_rate' => env('BLUE_GREEN_ALERT_ERROR_RATE', 5.0),
                'health_score' => env('BLUE_GREEN_ALERT_HEALTH_SCORE', 80.0),
            ],
        ],
    ],

    'logging' => [
        /*
        |--------------------------------------------------------------------------
        | Log Level
        |--------------------------------------------------------------------------
        |
        | Minimum log level for blue-green deployment operations.
        | Available levels: emergency, alert, critical, error, warning, notice, info, debug
        |
        */
        'level' => env('BLUE_GREEN_LOG_LEVEL', 'info'),

        /*
        |--------------------------------------------------------------------------
        | Log Channels
        |--------------------------------------------------------------------------
        |
        | Log channels to use for blue-green deployment logging.
        | Multiple channels can be specified as an array.
        |
        */
        'channels' => [
            'default' => env('LOG_CHANNEL', 'stack'),
            'deployment' => env('BLUE_GREEN_LOG_CHANNEL', 'daily'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Structured Logging
        |--------------------------------------------------------------------------
        |
        | Enable structured logging with additional context for better
        | log analysis and monitoring.
        |
        */
        'structured' => env('BLUE_GREEN_STRUCTURED_LOGGING', true),
    ],

    'cache' => [
        /*
        |--------------------------------------------------------------------------
        | Cache Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for caching deployment state and migration information.
        |
        */
        'ttl' => env('BLUE_GREEN_CACHE_TTL', 300), // 5 minutes
        'prefix' => env('BLUE_GREEN_CACHE_PREFIX', 'blue_green'),
        'store' => env('BLUE_GREEN_CACHE_STORE', 'redis'),
    ],

    'security' => [
        /*
        |--------------------------------------------------------------------------
        | Security Configuration
        |--------------------------------------------------------------------------
        |
        | Security settings for blue-green deployment operations.
        |
        */
        'require_confirmation' => env('BLUE_GREEN_REQUIRE_CONFIRMATION', true),
        'allowed_users' => env('BLUE_GREEN_ALLOWED_USERS', ''),
        'audit_logging' => env('BLUE_GREEN_AUDIT_LOGGING', true),
        'encryption_key' => env('BLUE_GREEN_ENCRYPTION_KEY'),
    ],
];
