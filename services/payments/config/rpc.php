<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | RPC Configuration - PHP 8.3 & Laravel 12
    |--------------------------------------------------------------------------
    |
    | Configuration for the modern RPC ecosystem with comprehensive
    | service discovery, timeouts, retries, and monitoring settings.
    |
    */

    'environment' => env('RPC_ENVIRONMENT', env('APP_ENV', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Service Discovery
    |--------------------------------------------------------------------------
    |
    | Configure how services discover and communicate with each other.
    | Supports local, docker, and kubernetes environments.
    |
    */
    'discovery' => [
        'local' => [
            'host' => 'localhost',
            'protocol' => 'http',
        ],
        'docker' => [
            'host_suffix' => '',
            'protocol' => 'http',
        ],
        'kubernetes' => [
            'namespace' => env('K8S_NAMESPACE', 'default'),
            'cluster_domain' => env('K8S_CLUSTER_DOMAIN', 'cluster.local'),
            'protocol' => 'http',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Timeouts & Retries
    |--------------------------------------------------------------------------
    |
    | Default configuration for RPC calls including timeouts,
    | retry policies, and circuit breaker settings.
    |
    */
    'defaults' => [
        'timeout_seconds' => env('RPC_TIMEOUT', 30),
        'max_retries' => env('RPC_MAX_RETRIES', 3),
        'backoff_ms' => env('RPC_BACKOFF_MS', 1000),
        'max_backoff_ms' => env('RPC_MAX_BACKOFF_MS', 30000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Override default settings for specific services based on their
    | performance characteristics and requirements.
    |
    */
    'services' => [
        'auth-service' => [
            'timeout_seconds' => 10,
            'max_retries' => 2,
            'critical' => true,
        ],
        'user-service' => [
            'timeout_seconds' => 15,
            'max_retries' => 3,
            'critical' => true,
        ],
        'auction-service' => [
            'timeout_seconds' => 20,
            'max_retries' => 3,
            'critical' => false,
        ],
        'bidding-service' => [
            'timeout_seconds' => 10,
            'max_retries' => 2,
            'critical' => false,
        ],
        'payment-service' => [
            'timeout_seconds' => 45,
            'max_retries' => 5,
            'critical' => true,
        ],
        'order-service' => [
            'timeout_seconds' => 30,
            'max_retries' => 3,
            'critical' => false,
        ],
        'notification-service' => [
            'timeout_seconds' => 15,
            'max_retries' => 2,
            'critical' => false,
        ],
        'analytics-service' => [
            'timeout_seconds' => 60,
            'max_retries' => 1,
            'critical' => false,
        ],
        'vin-ocr-service' => [
            'timeout_seconds' => 120,
            'max_retries' => 2,
            'critical' => false,
        ],
        'gateway-service' => [
            'timeout_seconds' => 5,
            'max_retries' => 1,
            'critical' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for comprehensive health monitoring across all services
    | and infrastructure components.
    |
    */
    'health' => [
        'enabled' => env('RPC_HEALTH_ENABLED', true),
        'cache_ttl' => env('RPC_HEALTH_CACHE_TTL', 30), // seconds
        'timeout' => env('RPC_HEALTH_TIMEOUT', 5), // seconds
        'thresholds' => [
            'memory_warning' => 80, // percentage
            'memory_critical' => 90, // percentage
            'disk_warning' => 85, // percentage
            'disk_critical' => 95, // percentage
            'response_time_warning' => 1000, // milliseconds
            'response_time_critical' => 5000, // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Observability
    |--------------------------------------------------------------------------
    |
    | Configuration for distributed tracing, metrics collection,
    | and structured logging.
    |
    */
    'monitoring' => [
        'correlation_id' => [
            'enabled' => env('RPC_CORRELATION_ID_ENABLED', true),
            'header' => 'X-Correlation-ID',
        ],
        'metrics' => [
            'enabled' => env('RPC_METRICS_ENABLED', false),
            'driver' => env('RPC_METRICS_DRIVER', 'prometheus'),
            'prefix' => env('RPC_METRICS_PREFIX', 'rpc_'),
        ],
        'tracing' => [
            'enabled' => env('RPC_TRACING_ENABLED', false),
            'driver' => env('RPC_TRACING_DRIVER', 'jaeger'),
            'endpoint' => env('RPC_TRACING_ENDPOINT'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for RPC authentication, encryption, and security policies.
    |
    */
    'security' => [
        'rpc_auth' => [
            'enabled' => env('RPC_AUTH_ENABLED', true),
            'token_header' => 'X-RPC-Token',
            'service_header' => 'X-Service-Client',
        ],
        'encryption' => [
            'enabled' => env('RPC_ENCRYPTION_ENABLED', false),
            'algorithm' => env('RPC_ENCRYPTION_ALGORITHM', 'AES-256-GCM'),
        ],
        'rate_limiting' => [
            'enabled' => env('RPC_RATE_LIMITING_ENABLED', false),
            'requests_per_minute' => env('RPC_RATE_LIMIT', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for circuit breaker pattern to handle service failures
    | gracefully and prevent cascade failures.
    |
    */
    'circuit_breaker' => [
        'enabled' => env('RPC_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('RPC_CB_FAILURE_THRESHOLD', 5),
        'recovery_timeout' => env('RPC_CB_RECOVERY_TIMEOUT', 60), // seconds
        'half_open_max_calls' => env('RPC_CB_HALF_OPEN_MAX_CALLS', 3),
    ],
];
