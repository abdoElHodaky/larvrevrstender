<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cross-service infrastructure components including
    | procedure engine, REST/RPC handlers, and service discovery.
    |
    */

    'service' => [
        'name' => env('SHARED_SERVICE_NAME', 'shared-service'),
        'port' => env('SHARED_SERVICE_PORT', 8010),
        'host' => env('SHARED_SERVICE_HOST', '0.0.0.0'),
        'timeout' => env('SHARED_SERVICE_TIMEOUT', 30),
    ],

    'procedure_engine' => [
        'enabled' => env('PROCEDURE_ENGINE_ENABLED', true),
        'max_execution_time' => env('PROCEDURE_MAX_EXECUTION_TIME', 300),
        'memory_limit' => env('PROCEDURE_MEMORY_LIMIT', '512M'),
        'logging' => [
            'enabled' => env('PROCEDURE_LOGGING_ENABLED', true),
            'level' => env('PROCEDURE_LOG_LEVEL', 'info'),
            'channel' => env('PROCEDURE_LOG_CHANNEL', 'shared'),
        ],
    ],

    'rest_handler' => [
        'enabled' => env('REST_HANDLER_ENABLED', true),
        'cors' => [
            'enabled' => env('CORS_ENABLED', true),
            'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
            'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),
            'allowed_headers' => explode(',', env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With')),
        ],
        'rate_limiting' => [
            'enabled' => env('RATE_LIMITING_ENABLED', true),
            'max_attempts' => env('RATE_LIMIT_MAX_ATTEMPTS', 60),
            'decay_minutes' => env('RATE_LIMIT_DECAY_MINUTES', 1),
        ],
    ],

    'rpc_handler' => [
        'enabled' => env('RPC_HANDLER_ENABLED', true),
        'version' => '2.0', // JSON-RPC version
        'service_discovery' => [
            'enabled' => env('SERVICE_DISCOVERY_ENABLED', true),
            'registry_url' => env('SERVICE_REGISTRY_URL', 'http://gateway-service:8000/registry'),
            'health_check_interval' => env('HEALTH_CHECK_INTERVAL', 30),
            'timeout' => env('SERVICE_DISCOVERY_TIMEOUT', 5),
        ],
    ],

    'caching' => [
        'enabled' => env('SHARED_CACHE_ENABLED', true),
        'driver' => env('SHARED_CACHE_DRIVER', 'redis'),
        'prefix' => env('SHARED_CACHE_PREFIX', 'shared:'),
        'ttl' => env('SHARED_CACHE_TTL', 3600),
        'compression' => [
            'enabled' => env('CACHE_COMPRESSION_ENABLED', true),
            'algorithm' => env('CACHE_COMPRESSION_ALGORITHM', 'gzip'),
        ],
    ],

    'events' => [
        'enabled' => env('EVENT_PUBLISHING_ENABLED', true),
        'driver' => env('EVENT_DRIVER', 'redis'),
        'channels' => [
            'default' => env('EVENT_DEFAULT_CHANNEL', 'shared-events'),
            'audit' => env('EVENT_AUDIT_CHANNEL', 'audit-events'),
            'metrics' => env('EVENT_METRICS_CHANNEL', 'metrics-events'),
        ],
        'retry' => [
            'enabled' => env('EVENT_RETRY_ENABLED', true),
            'max_attempts' => env('EVENT_MAX_RETRY_ATTEMPTS', 3),
            'delay' => env('EVENT_RETRY_DELAY', 1000), // milliseconds
        ],
    ],

    'security' => [
        'encryption' => [
            'enabled' => env('SHARED_ENCRYPTION_ENABLED', true),
            'algorithm' => env('SHARED_ENCRYPTION_ALGORITHM', 'AES-256-GCM'),
            'key' => env('SHARED_ENCRYPTION_KEY'),
        ],
        'authentication' => [
            'enabled' => env('SHARED_AUTH_ENABLED', true),
            'driver' => env('SHARED_AUTH_DRIVER', 'jwt'),
            'secret' => env('SHARED_AUTH_SECRET'),
        ],
        'rate_limiting' => [
            'enabled' => env('SECURITY_RATE_LIMITING_ENABLED', true),
            'max_requests' => env('SECURITY_MAX_REQUESTS', 1000),
            'window_minutes' => env('SECURITY_WINDOW_MINUTES', 60),
        ],
    ],

    'monitoring' => [
        'metrics' => [
            'enabled' => env('METRICS_ENABLED', true),
            'driver' => env('METRICS_DRIVER', 'prometheus'),
            'endpoint' => env('METRICS_ENDPOINT', '/metrics'),
        ],
        'tracing' => [
            'enabled' => env('TRACING_ENABLED', true),
            'driver' => env('TRACING_DRIVER', 'jaeger'),
            'service_name' => env('TRACING_SERVICE_NAME', 'shared-service'),
        ],
        'health_checks' => [
            'enabled' => env('HEALTH_CHECKS_ENABLED', true),
            'endpoint' => env('HEALTH_CHECK_ENDPOINT', '/health'),
            'timeout' => env('HEALTH_CHECK_TIMEOUT', 5),
        ],
    ],

    'circuit_breaker' => [
        'enabled' => env('CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'recovery_timeout' => env('CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 60),
        'expected_exception_types' => [
            'connection_timeout',
            'service_unavailable',
            'rate_limit_exceeded',
        ],
    ],
];
