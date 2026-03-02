<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared Logging Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the centralized logging setup for all
    | microservices in the Laravel Reverse Tender Platform. It integrates
    | ka4ivan/laravel-logger with Laravel Telescope for comprehensive logging.
    |
    */

    'default' => env('LOG_CHANNEL', 'shared_stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | uses the Monolog PHP logging library, which includes a variety of
    | powerful log handlers and formatters that you may utilize.
    |
    */

    'channels' => [

        'shared_stack' => [
            'driver' => 'stack',
            'channels' => ['shared_daily', 'database_failover', 'telescope'],
            'ignore_exceptions' => false,
        ],

        'shared_single' => [
            'driver' => 'single',
            'path' => storage_path('logs/shared.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'shared_daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shared.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'database_failover' => [
            'driver' => 'daily',
            'path' => storage_path('logs/database_failover.log'),
            'level' => env('DB_FAILOVER_LOG_LEVEL', 'info'),
            'days' => env('DB_FAILOVER_LOG_DAYS', 30),
            'replace_placeholders' => true,
        ],

        'request_correlation' => [
            'driver' => 'daily',
            'path' => storage_path('logs/request_correlation.log'),
            'level' => env('REQUEST_CORRELATION_LOG_LEVEL', 'info'),
            'days' => env('REQUEST_CORRELATION_LOG_DAYS', 7),
            'replace_placeholders' => true,
        ],

        'health_checks' => [
            'driver' => 'daily',
            'path' => storage_path('logs/health_checks.log'),
            'level' => env('HEALTH_CHECK_LOG_LEVEL', 'warning'),
            'days' => env('HEALTH_CHECK_LOG_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'telescope' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\FingersCrossedHandler::class,
            'handler_with' => [
                'handler' => Monolog\Handler\StreamHandler::class,
                'handler_with' => [
                    'stream' => storage_path('logs/telescope.log'),
                ],
                'activation_strategy' => Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy::class,
                'activation_strategy_with' => [
                    'actionLevel' => Monolog\Logger::INFO,
                ],
            ],
            'processors' => [
                Monolog\Processor\PsrLogMessageProcessor::class,
            ],
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_SLACK_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', Monolog\Handler\SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [Monolog\Processor\PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [Monolog\Processor\PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Ka4ivan Laravel Logger Channels
        |--------------------------------------------------------------------------
        |
        | These channels are specifically configured for ka4ivan/laravel-logger
        | integration with structured logging and model tracking capabilities.
        |
        */

        'tracking' => [
            'driver' => 'daily',
            'path' => storage_path('logs/_tracking.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'active' => env('LOGGING_ROUTES_ACTIVE', true),
        ],

        'structured' => [
            'driver' => 'daily',
            'path' => storage_path('logs/_structured.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'replace_placeholders' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Ka4ivan Laravel Logger Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to ka4ivan/laravel-logger package integration.
    |
    */

    'ka4ivan' => [
        'default' => env('LOG_CHANNEL', 'shared_stack'),

        'tracking' => [
            'default' => 'tracking',
        ],

        'user' => [
            'fields' => ['id', 'email', 'name'],
        ],

        'max_file_size' => 52428800, // 50MB

        'pattern' => env('LOGGER_PATTERN', '*.log'),

        'storage_path' => env('LOGGER_STORAGE_PATH', storage_path('logs')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Correlation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cross-service request correlation and context tracking.
    |
    */

    'correlation' => [
        'enabled' => env('REQUEST_CORRELATION_ENABLED', true),
        'header_name' => env('REQUEST_CORRELATION_HEADER', 'X-Request-ID'),
        'service_header' => env('SERVICE_IDENTIFICATION_HEADER', 'X-Service-Name'),
        'context_keys' => [
            'request_id',
            'service_name',
            'user_id',
            'session_id',
            'trace_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Failover Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Specific configuration for database failover event logging.
    |
    */

    'database_failover' => [
        'enabled' => env('DB_FAILOVER_LOGGING_ENABLED', true),
        'channel' => env('DB_FAILOVER_LOG_CHANNEL', 'database_failover'),
        'level' => env('DB_FAILOVER_LOG_LEVEL', 'info'),
        'include_query_details' => env('DB_FAILOVER_LOG_QUERIES', false),
        'include_stack_trace' => env('DB_FAILOVER_LOG_STACK_TRACE', true),
        'events' => [
            'connection_switch' => true,
            'health_check_failure' => true,
            'recovery_attempt' => true,
            'circuit_breaker_open' => true,
            'graceful_degradation' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Laravel Telescope integration with the logging system.
    |
    */

    'telescope' => [
        'enabled' => env('TELESCOPE_LOGGING_ENABLED', true),
        'channel' => env('TELESCOPE_LOG_CHANNEL', 'telescope'),
        'capture_levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'info'],
        'capture_database_failover' => env('TELESCOPE_CAPTURE_DB_FAILOVER', true),
        'capture_request_correlation' => env('TELESCOPE_CAPTURE_REQUEST_CORRELATION', true),
        'max_entries' => env('TELESCOPE_MAX_LOG_ENTRIES', 1000),
    ],

];
