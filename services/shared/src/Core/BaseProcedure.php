<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

/**
 * Base Procedure Class
 * 
 * Provides common functionality for both micro and macro procedures
 * including logging, caching, validation, and error handling utilities.
 */
abstract class BaseProcedure
{
    protected array $config;
    protected string $procedureName;
    protected string $procedureType; // 'micro' or 'macro'
    protected array $context;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->procedureName = static::class;
        $this->procedureType = $this->determineProcedureType();
        $this->context = [];
    }

    /**
     * Get default configuration for procedures
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'enable_logging' => true,
            'enable_caching' => true,
            'cache_ttl' => 3600, // 1 hour
            'enable_validation' => true,
            'enable_metrics' => true,
            'timeout' => 30,
            'retry_attempts' => 3
        ];
    }

    /**
     * Determine procedure type based on class name or namespace
     *
     * @return string
     */
    protected function determineProcedureType(): string
    {
        $className = static::class;
        
        if (strpos($className, 'Micro\\') !== false) {
            return 'micro';
        } elseif (strpos($className, 'Macro\\') !== false) {
            return 'macro';
        }
        
        return 'micro'; // Default to micro
    }

    /**
     * Set execution context
     *
     * @param array $context
     * @return void
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Get execution context
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Log procedure execution
     *
     * @param string $level
     * @param string $message
     * @param array $data
     * @return void
     */
    protected function log(string $level, string $message, array $data = []): void
    {
        if (!$this->config['enable_logging']) {
            return;
        }

        $logData = array_merge([
            'procedure' => $this->procedureName,
            'type' => $this->procedureType,
            'trace_id' => $this->context['trace_id'] ?? null,
            'timestamp' => now()->toISOString()
        ], $data);

        Log::log($level, $message, $logData);
    }

    /**
     * Validate input parameters
     *
     * @param array $params
     * @param array $rules
     * @return array
     */
    protected function validateParams(array $params, array $rules): array
    {
        if (!$this->config['enable_validation']) {
            return ['success' => true, 'errors' => []];
        }

        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $params[$field] ?? null;
            
            // Required validation
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field][] = "Field '{$field}' is required";
                continue;
            }

            // Skip other validations if field is empty and not required
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }

            // Type validation
            if (isset($rule['type'])) {
                if (!$this->validateType($value, $rule['type'])) {
                    $errors[$field][] = "Field '{$field}' must be of type {$rule['type']}";
                }
            }

            // Min/Max validation
            if (isset($rule['min']) && strlen($value) < $rule['min']) {
                $errors[$field][] = "Field '{$field}' must be at least {$rule['min']} characters";
            }

            if (isset($rule['max']) && strlen($value) > $rule['max']) {
                $errors[$field][] = "Field '{$field}' must not exceed {$rule['max']} characters";
            }

            // Custom validation
            if (isset($rule['custom']) && is_callable($rule['custom'])) {
                $customResult = $rule['custom']($value);
                if ($customResult !== true) {
                    $errors[$field][] = $customResult;
                }
            }
        }

        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate parameter type
     *
     * @param mixed $value
     * @param string $type
     * @return bool
     */
    private function validateType($value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'integer':
            case 'int':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'float':
            case 'double':
                return is_float($value) || is_numeric($value);
            case 'boolean':
            case 'bool':
                return is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0]);
            case 'array':
                return is_array($value);
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'uuid':
                return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
            default:
                return true;
        }
    }

    /**
     * Cache data with automatic key generation
     *
     * @param string $key
     * @param mixed $data
     * @param int|null $ttl
     * @return bool
     */
    protected function cache(string $key, $data, ?int $ttl = null): bool
    {
        if (!$this->config['enable_caching']) {
            return false;
        }

        $cacheKey = $this->generateCacheKey($key);
        $ttl = $ttl ?? $this->config['cache_ttl'];

        try {
            return Cache::put($cacheKey, $data, $ttl);
        } catch (Exception $e) {
            $this->log('warning', 'Cache write failed', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Retrieve cached data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getCached(string $key, $default = null)
    {
        if (!$this->config['enable_caching']) {
            return $default;
        }

        $cacheKey = $this->generateCacheKey($key);

        try {
            return Cache::get($cacheKey, $default);
        } catch (Exception $e) {
            $this->log('warning', 'Cache read failed', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * Clear cached data
     *
     * @param string $key
     * @return bool
     */
    protected function clearCache(string $key): bool
    {
        if (!$this->config['enable_caching']) {
            return false;
        }

        $cacheKey = $this->generateCacheKey($key);

        try {
            return Cache::forget($cacheKey);
        } catch (Exception $e) {
            $this->log('warning', 'Cache clear failed', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate cache key with procedure namespace
     *
     * @param string $key
     * @return string
     */
    private function generateCacheKey(string $key): string
    {
        $procedureKey = str_replace(['\\', '/'], '_', strtolower($this->procedureName));
        return "cross_service:{$procedureKey}:{$key}";
    }

    /**
     * Execute database transaction
     *
     * @param callable $callback
     * @return mixed
     */
    protected function transaction(callable $callback)
    {
        try {
            return DB::transaction($callback);
        } catch (Exception $e) {
            $this->log('error', 'Database transaction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Format success response
     *
     * @param mixed $data
     * @param string $message
     * @param array $metadata
     * @return array
     */
    protected function successResponse($data = null, string $message = 'Operation completed successfully', array $metadata = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'metadata' => array_merge([
                'procedure' => $this->procedureName,
                'type' => $this->procedureType,
                'timestamp' => now()->toISOString()
            ], $metadata)
        ];
    }

    /**
     * Format error response
     *
     * @param string $message
     * @param mixed $data
     * @param array $metadata
     * @return array
     */
    protected function errorResponse(string $message, $data = null, array $metadata = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
            'metadata' => array_merge([
                'procedure' => $this->procedureName,
                'type' => $this->procedureType,
                'timestamp' => now()->toISOString()
            ], $metadata)
        ];
    }

    /**
     * Record metrics for procedure execution
     *
     * @param string $metric
     * @param mixed $value
     * @param array $tags
     * @return void
     */
    protected function recordMetric(string $metric, $value, array $tags = []): void
    {
        if (!$this->config['enable_metrics']) {
            return;
        }

        $metricData = [
            'metric' => $metric,
            'value' => $value,
            'procedure' => $this->procedureName,
            'type' => $this->procedureType,
            'tags' => $tags,
            'timestamp' => now()->toISOString()
        ];

        // This would integrate with your metrics system (Prometheus, StatsD, etc.)
        $this->log('info', 'Metric recorded', $metricData);
    }

    /**
     * Get procedure metadata
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->procedureName,
            'type' => $this->procedureType,
            'config' => $this->config,
            'context' => $this->context
        ];
    }

    /**
     * Health check for the procedure
     *
     * @return array
     */
    public function healthCheck(): array
    {
        return [
            'status' => 'healthy',
            'procedure' => $this->procedureName,
            'type' => $this->procedureType,
            'timestamp' => now()->toISOString()
        ];
    }
}
