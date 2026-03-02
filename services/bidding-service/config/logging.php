<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

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

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'database_failover', 'shared_logger'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/bidding-service.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/bidding-service.log'),
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

        'shared_logger' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shared.log'),
            'level' => env('SHARED_LOG_LEVEL', 'info'),
            'days' => env('SHARED_LOG_DAYS', 14),
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
            'username' => 'Bidding Service',
            'emoji' => ':moneybag:',
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

    ],

];
