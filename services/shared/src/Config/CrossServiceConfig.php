<?php

namespace Shared\Config;

/**
 * Cross-Service Configuration Manager
 * 
 * Centralized configuration management for cross-service infrastructure
 * with environment-based settings and runtime configuration updates.
 */
class CrossServiceConfig
{
    private static ?self $instance = null;
    private array $config = [];
    private array $environmentOverrides = [];

    private function __construct()
    {
        $this->loadDefaultConfig();
        $this->loadEnvironmentConfig();
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load default configuration
     *
     * @return void
     */
    private function loadDefaultConfig(): void
    {
        $this->config = [
            'procedure_engine' => [
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
                'enable_tracing' => true,
                'enable_metrics' => true,
                'max_execution_time' => 300,
            ],

            'rest_handler' => [
                'enable_cors' => true,
                'cors_origins' => ['*'],
                'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'cors_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Trace-ID'],
                'rate_limit' => 1000,
                'enable_compression' => true,
                'max_request_size' => 10485760,
            ],

            'rpc_handler' => [
                'protocol' => 'json-rpc',
                'timeout' => 30,
                'max_connections' => 100,
                'enable_compression' => true,
                'compression_threshold' => 1024,
                'enable_load_balancing' => true,
                'load_balancing_strategy' => 'round_robin',
                'enable_circuit_breaker' => true,
                'circuit_breaker_threshold' => 5,
                'circuit_breaker_timeout' => 60,
                'enable_service_discovery' => true,
                'heartbeat_interval' => 30,
            ],

            'caching' => [
                'default_driver' => 'redis',
                'drivers' => [
                    'redis' => [
                        'host' => '127.0.0.1',
                        'port' => 6379,
                        'database' => 0,
                        'prefix' => 'cross_service:',
                    ],
                    'memcached' => [
                        'servers' => [
                            ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]
                        ],
                        'prefix' => 'cross_service:',
                    ],
                    'file' => [
                        'path' => storage_path('framework/cache/cross_service'),
                    ]
                ],
                'default_ttl' => 3600,
                'enable_compression' => true,
                'compression_threshold' => 1024,
            ],

            'events' => [
                'default_driver' => 'redis',
                'drivers' => [
                    'redis' => [
                        'host' => '127.0.0.1',
                        'port' => 6379,
                        'database' => 1,
                        'channel_prefix' => 'events:',
                    ],
                    'rabbitmq' => [
                        'host' => '127.0.0.1',
                        'port' => 5672,
                        'username' => 'guest',
                        'password' => 'guest',
                        'vhost' => '/',
                        'exchange' => 'cross_service_events',
                    ],
                    'kafka' => [
                        'brokers' => ['127.0.0.1:9092'],
                        'topic_prefix' => 'cross_service_',
                    ]
                ],
                'enable_dead_letter_queue' => true,
                'max_retry_attempts' => 3,
                'retry_delay' => 5000,
                'enable_event_replay' => true,
                'replay_retention_days' => 30,
            ],

            'security' => [
                'enable_authentication' => true,
                'authentication_driver' => 'jwt',
                'jwt' => [
                    'secret' => env('JWT_SECRET', 'your-secret-key'),
                    'ttl' => 3600,
                    'refresh_ttl' => 86400,
                    'algorithm' => 'HS256',
                ],
                'enable_authorization' => true,
                'enable_rate_limiting' => true,
                'rate_limiting' => [
                    'default_limit' => 1000,
                    'window' => 60,
                    'storage' => 'redis',
                ],
                'enable_ip_whitelisting' => false,
                'ip_whitelist' => [],
                'enable_encryption' => true,
                'encryption_algorithm' => 'AES-256-GCM',
            ],

            'monitoring' => [
                'enable_metrics' => true,
                'metrics_driver' => 'prometheus',
                'prometheus' => [
                    'namespace' => 'cross_service',
                    'gateway_url' => 'http://localhost:9091',
                ],
                'enable_health_checks' => true,
                'health_check_interval' => 30,
                'enable_distributed_tracing' => true,
                'tracing_driver' => 'jaeger',
                'jaeger' => [
                    'agent_host' => '127.0.0.1',
                    'agent_port' => 6832,
                ],
                'enable_alerting' => true,
                'alert_channels' => ['email', 'slack'],
            ],

            'database' => [
                'enable_transactions' => true,
                'transaction_timeout' => 30,
                'enable_connection_pooling' => true,
                'pool_size' => 10,
                'enable_query_logging' => false,
                'slow_query_threshold' => 1000,
                'enable_backup' => true,
                'backup_schedule' => '0 2 * * *', // Daily at 2 AM
                'backup_retention_days' => 30,
            ],

            'validation' => [
                'enable_strict_validation' => true,
                'enable_custom_validators' => true,
                'enable_cross_field_validation' => true,
                'max_validation_errors' => 50,
                'enable_validation_caching' => true,
                'validation_cache_ttl' => 300,
            ],

            'error_handling' => [
                'enable_global_handler' => true,
                'enable_error_reporting' => true,
                'error_reporting_channels' => ['log', 'sentry'],
                'enable_circuit_breaker' => true,
                'circuit_breaker_failure_threshold' => 5,
                'circuit_breaker_recovery_timeout' => 60,
                'enable_retry_logic' => true,
                'max_retry_attempts' => 3,
                'retry_backoff_multiplier' => 2,
            ],

            'logging' => [
                'default_channel' => 'cross_service',
                'channels' => [
                    'cross_service' => [
                        'driver' => 'daily',
                        'path' => storage_path('logs/cross_service.log'),
                        'level' => 'info',
                        'days' => 14,
                    ],
                    'performance' => [
                        'driver' => 'daily',
                        'path' => storage_path('logs/cross_service_performance.log'),
                        'level' => 'info',
                        'days' => 7,
                    ],
                    'security' => [
                        'driver' => 'daily',
                        'path' => storage_path('logs/cross_service_security.log'),
                        'level' => 'warning',
                        'days' => 30,
                    ]
                ],
                'enable_structured_logging' => true,
                'enable_log_aggregation' => false,
                'log_aggregation_endpoint' => null,
            ],

            'testing' => [
                'enable_integration_testing' => true,
                'enable_contract_testing' => true,
                'enable_performance_testing' => true,
                'test_data_retention_days' => 7,
                'enable_mocking' => true,
                'mock_external_services' => true,
            ],

            'documentation' => [
                'enable_auto_generation' => true,
                'output_format' => 'openapi',
                'output_path' => storage_path('docs/cross_service'),
                'include_examples' => true,
                'include_schemas' => true,
                'enable_interactive_docs' => true,
            ]
        ];
    }

    /**
     * Load environment-specific configuration
     *
     * @return void
     */
    private function loadEnvironmentConfig(): void
    {
        // Load from environment variables
        $this->environmentOverrides = [
            'procedure_engine.timeout' => env('CROSS_SERVICE_TIMEOUT', null),
            'procedure_engine.enable_metrics' => env('CROSS_SERVICE_ENABLE_METRICS', null),
            
            'caching.default_driver' => env('CROSS_SERVICE_CACHE_DRIVER', null),
            'caching.drivers.redis.host' => env('REDIS_HOST', null),
            'caching.drivers.redis.port' => env('REDIS_PORT', null),
            
            'events.default_driver' => env('CROSS_SERVICE_EVENT_DRIVER', null),
            'events.drivers.redis.host' => env('REDIS_HOST', null),
            
            'security.jwt.secret' => env('JWT_SECRET', null),
            'security.jwt.ttl' => env('JWT_TTL', null),
            
            'monitoring.enable_metrics' => env('CROSS_SERVICE_ENABLE_MONITORING', null),
            'monitoring.prometheus.gateway_url' => env('PROMETHEUS_GATEWAY_URL', null),
            
            'database.pool_size' => env('DB_POOL_SIZE', null),
            'database.enable_backup' => env('DB_ENABLE_BACKUP', null),
        ];

        // Apply environment overrides
        foreach ($this->environmentOverrides as $key => $value) {
            if ($value !== null) {
                $this->setNestedValue($this->config, $key, $value);
            }
        }
    }

    /**
     * Get configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    /**
     * Set configuration value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->setNestedValue($this->config, $key, $value);
    }

    /**
     * Get all configuration
     *
     * @return array
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Get configuration for a specific component
     *
     * @param string $component
     * @return array
     */
    public function getComponent(string $component): array
    {
        return $this->config[$component] ?? [];
    }

    /**
     * Merge configuration with existing values
     *
     * @param array $config
     * @return void
     */
    public function merge(array $config): void
    {
        $this->config = array_merge_recursive($this->config, $config);
    }

    /**
     * Load configuration from file
     *
     * @param string $filePath
     * @return bool
     */
    public function loadFromFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $fileConfig = include $filePath;
        if (is_array($fileConfig)) {
            $this->merge($fileConfig);
            return true;
        }

        return false;
    }

    /**
     * Save configuration to file
     *
     * @param string $filePath
     * @return bool
     */
    public function saveToFile(string $filePath): bool
    {
        $configContent = "<?php\n\nreturn " . var_export($this->config, true) . ";\n";
        
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($filePath, $configContent) !== false;
    }

    /**
     * Validate configuration
     *
     * @return array
     */
    public function validate(): array
    {
        $errors = [];

        // Validate required components
        $requiredComponents = [
            'procedure_engine', 'rest_handler', 'rpc_handler', 
            'caching', 'events', 'security', 'monitoring'
        ];

        foreach ($requiredComponents as $component) {
            if (!isset($this->config[$component])) {
                $errors[] = "Missing required component: {$component}";
            }
        }

        // Validate specific settings
        if (isset($this->config['procedure_engine']['timeout']) && 
            $this->config['procedure_engine']['timeout'] <= 0) {
            $errors[] = "Procedure engine timeout must be greater than 0";
        }

        if (isset($this->config['caching']['default_ttl']) && 
            $this->config['caching']['default_ttl'] <= 0) {
            $errors[] = "Cache TTL must be greater than 0";
        }

        return $errors;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getNestedValue(array $array, string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set nested value in array using dot notation
     *
     * @param array &$array
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * Get environment-specific configuration
     *
     * @return array
     */
    public function getEnvironmentConfig(): array
    {
        return array_filter($this->environmentOverrides, function($value) {
            return $value !== null;
        });
    }

    /**
     * Reset configuration to defaults
     *
     * @return void
     */
    public function reset(): void
    {
        $this->loadDefaultConfig();
        $this->loadEnvironmentConfig();
    }

    /**
     * Get configuration summary for debugging
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'components' => array_keys($this->config),
            'environment_overrides' => count(array_filter($this->environmentOverrides)),
            'validation_errors' => $this->validate(),
            'total_settings' => $this->countSettings($this->config),
        ];
    }

    /**
     * Count total number of settings recursively
     *
     * @param array $array
     * @return int
     */
    private function countSettings(array $array): int
    {
        $count = 0;
        foreach ($array as $value) {
            if (is_array($value)) {
                $count += $this->countSettings($value);
            } else {
                $count++;
            }
        }
        return $count;
    }
}
