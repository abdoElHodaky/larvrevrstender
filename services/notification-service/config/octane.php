<?php

use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\TickTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;
use Laravel\Octane\Listeners\EnsureUploadedFilesAreValid;
use Laravel\Octane\Listeners\EnsureUploadedFilesCanBeMoved;
use Laravel\Octane\Listeners\FlushTemporaryContainerInstances;
use Laravel\Octane\Listeners\ReportException;
use Laravel\Octane\Listeners\StopWorkerIfNecessary;

return [
    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    |
    | This value determines the default "server" that will be used when
    | starting Octane. This server is used when issuing the `octane:start`
    | command or when the server is started via the Octane::start method.
    |
    */

    'server' => env('OCTANE_SERVER', 'frankenphp'),

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS
    |--------------------------------------------------------------------------
    |
    | When this configuration value is set to "true", Octane will inform the
    | framework that all absolute URLs should be generated using the HTTPS
    | protocol. Otherwise, your application may generate insecure assets.
    |
    */

    'https' => env('OCTANE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Octane Listeners
    |--------------------------------------------------------------------------
    |
    | All of the event listeners for Octane's events are defined below. These
    | listeners are responsible for resetting your application's state after
    | each request. You may even add your own listeners to this array.
    |
    */

    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeMoved::class,
        ],

        RequestReceived::class => [
            // Custom request received listeners
        ],

        RequestHandled::class => [
            FlushTemporaryContainerInstances::class,
        ],

        RequestTerminated::class => [
            // Custom cleanup listeners
        ],

        TaskReceived::class => [
            // Task handling listeners
        ],

        TaskTerminated::class => [
            // Task cleanup listeners
        ],

        TickReceived::class => [
            // Periodic task listeners
        ],

        TickTerminated::class => [
            // Periodic cleanup listeners
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerStopping::class => [
            // Worker cleanup listeners
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm / Flush Bindings
    |--------------------------------------------------------------------------
    |
    | The bindings listed below will either be pre-warmed when a worker boots
    | or they will be flushed before every new request. Flushing a binding
    | will force the container to resolve that binding again when asked.
    |
    */

    'warm' => [
        // Pre-warm these bindings when worker starts
        'procedures' => [
            \App\RPC\Procedures\HealthProcedure::class,
            \App\RPC\Procedures\UtilityProcedure::class,
        ],
    ],

    'flush' => [
        // Flush these bindings before each request
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane Cache Table
    |--------------------------------------------------------------------------
    |
    | While using Octane, you may leverage the Octane cache, which is powered
    | by a Swoole table. You may set the maximum number of rows as well as
    | the number of bytes per row using the configuration options below.
    |
    */

    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane Swoole Tables
    |--------------------------------------------------------------------------
    |
    | While using Octane, you may leverage Swoole's powerful table feature
    | as a fast, shared memory cache. You may register a table's structure
    | using the "columns" array below along with the maximum rows allowed.
    |
    */

    'tables' => [
        'rpc_metrics' => [
            'rows' => 1000,
            'columns' => [
                ['name' => 'method', 'type' => 'string', 'size' => 100],
                ['name' => 'response_time', 'type' => 'float'],
                ['name' => 'memory_usage', 'type' => 'int'],
                ['name' => 'timestamp', 'type' => 'int'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watching
    |--------------------------------------------------------------------------
    |
    | The following list of files and directories will be watched when using
    | the --watch option. If any of the files change, Octane will restart.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'resources/**/*.php',
        'routes',
        '.env',
    ],

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection Threshold
    |--------------------------------------------------------------------------
    |
    | When executing long-lived PHP scripts such as Octane, memory can build
    | up before being cleared by PHP. You can force Octane to run garbage
    | collection if memory usage exceeds the given number of megabytes.
    |
    */

    'garbage' => 50,

    /*
    |--------------------------------------------------------------------------
    | Maximum Execution Time
    |--------------------------------------------------------------------------
    |
    | The following setting configures the maximum execution time for requests
    | handled by Octane. You may set this value to 0 to indicate that there
    | should be no time limit on Octane request execution time.
    |
    */

    'max_execution_time' => 30,

    /*
    |--------------------------------------------------------------------------
    | Octane Swoole Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure some of the Swoole server options, including
    | the host and port. Please consult the Swoole documentation for more
    | information on the available configuration options.
    |
    */

    'swoole' => [
        'options' => [
            'log_file' => storage_path('logs/swoole_http.log'),
            'package_max_length' => 10 * 1024 * 1024,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane RoadRunner Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure some of the RoadRunner server options, including
    | the host and port. Please consult the RoadRunner documentation for more
    | information on the available configuration options.
    |
    */

    'roadrunner' => [
        'binary_path' => env('RR_BINARY_PATH', 'rr'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane FrankenPHP Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure FrankenPHP server options. FrankenPHP is a modern
    | application server for PHP built on top of the Caddy web server.
    |
    */

    'frankenphp' => [
        'host' => env('OCTANE_HOST', '127.0.0.1'),
        'port' => env('OCTANE_PORT', 8000),
        'workers' => env('OCTANE_WORKERS', 2),
        'task_workers' => env('OCTANE_TASK_WORKERS', 4),
        'max_requests' => env('OCTANE_MAX_REQUESTS', 500),
        'caddyfile' => base_path('Caddyfile'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Octane RPC Configuration
    |--------------------------------------------------------------------------
    |
    | These settings configure the RPC settings for Octane when using the
    | RoadRunner server. The RPC settings allow Octane to communicate with
    | the RoadRunner server and control various aspects of the server.
    |
    */

    'rpc' => [
        'host' => env('OCTANE_RPC_HOST', '127.0.0.1'),
        'port' => env('OCTANE_RPC_PORT', 6010),
        'timeout' => env('OCTANE_RPC_TIMEOUT', 30),
        'batch_size' => env('OCTANE_RPC_BATCH_SIZE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | These settings help optimize Octane performance for RPC workloads.
    |
    */

    'performance' => [
        'memory_limit' => env('OCTANE_MEMORY_LIMIT', '256M'),
        'opcache_preload' => env('OCTANE_OPCACHE_PRELOAD', true),
        'jit_enabled' => env('OCTANE_JIT_ENABLED', true),
        'gc_probability' => env('OCTANE_GC_PROBABILITY', 0.01),
    ],

    /*
    |--------------------------------------------------------------------------
    | RPC Procedure Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for RPC procedures and their caching behavior.
    |
    */

    'procedures' => [
        'cache_enabled' => env('RPC_CACHE_ENABLED', true),
        'cache_ttl' => env('RPC_CACHE_TTL', 300), // 5 minutes
        'validation_enabled' => env('RPC_VALIDATION_ENABLED', true),
        'logging_enabled' => env('RPC_LOGGING_ENABLED', true),
        'metrics_enabled' => env('RPC_METRICS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for health check endpoints and monitoring.
    |
    */

    'health' => [
        'enabled' => env('OCTANE_HEALTH_ENABLED', true),
        'endpoint' => env('OCTANE_HEALTH_ENDPOINT', '/health'),
        'detailed_endpoint' => env('OCTANE_HEALTH_DETAILED_ENDPOINT', '/health/detailed'),
        'metrics_endpoint' => env('OCTANE_METRICS_ENDPOINT', '/metrics'),
    ],
];
