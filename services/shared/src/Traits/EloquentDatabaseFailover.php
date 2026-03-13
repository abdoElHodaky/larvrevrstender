<?php

namespace Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shared\Facades\SharedLog;
use Shared\Traits\DatabaseQueryCircuitBreaker;
use Shared\Traits\EloquentDatabaseFailoverEvents;

/**
 * EloquentDatabaseFailover Trait
 * 
 * Provides database failover capabilities for Eloquent models using modern PHP 8.3 & Laravel 12 features.
 * Integrates circuit breaker protection, automatic retry logic, and comprehensive logging
 * for resilient database operations in microservices architecture.
 * 
 * Features:
 * - Circuit breaker protection for all database operations
 * - Automatic retry logic with exponential backoff
 * - Read/write operation separation for failover scenarios
 * - Comprehensive logging and monitoring integration
 * - PHP 8.3 match expressions and typed properties
 * - Laravel 12 collection methods and modern syntax
 * 
 * Usage:
 * ```php
 * use Shared\Traits\EloquentDatabaseFailover;
 * 
 * class BusinessMetric extends Model {
 *     use EloquentDatabaseFailover;
 *     
 *     // Model automatically gets failover protection for all operations
 *     public function getMetricsWithFailover() {
 *         return $this->executeFailsafeQuery('business_metrics_read', function() {
 *             return static::where('active', true)->get();
 *         });
 *     }
 * }
 * ```
 */
trait EloquentDatabaseFailover
{
    use DatabaseQueryCircuitBreaker, EloquentDatabaseFailoverEvents;

    /**
     * Failover configuration cache
     */
    private static array $failoverConfig = [];

    /**
     * Execute a failsafe Eloquent query with circuit breaker protection
     *
     * @param string $operationName Unique name for the operation
     * @param callable $query The Eloquent query to execute
     * @param array $options Failover configuration options
     * @return mixed Query result
     */
    protected function executeFailsafeQuery(string $operationName, callable $query, array $options = []): mixed
    {
        $circuitName = $this->buildCircuitName($operationName);
        $config = $this->getFailoverConfig($options);
        
        return $this->executeProtectedQuery($circuitName, $query, $config);
    }

    /**
     * Execute a failsafe Eloquent transaction with circuit breaker protection
     *
     * @param string $operationName Unique name for the operation
     * @param callable $transaction The transaction to execute
     * @param array $options Failover configuration options
     * @return mixed Transaction result
     */
    protected function executeFailsafeTransaction(string $operationName, callable $transaction, array $options = []): mixed
    {
        $circuitName = $this->buildCircuitName($operationName);
        $config = $this->getFailoverConfig($options);
        
        return $this->executeProtectedTransaction($circuitName, $transaction, $config);
    }

    /**
     * Safe find operation with failover protection
     *
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return static|null Model instance or null
     */
    public function findSafely(mixed $id, array $columns = ['*']): ?static
    {
        return $this->executeFailsafeQuery('find_operation', function() use ($id, $columns) {
            return static::find($id, $columns);
        });
    }

    /**
     * Safe find or fail operation with failover protection
     *
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return static Model instance
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFailSafely(mixed $id, array $columns = ['*']): static
    {
        return $this->executeFailsafeQuery('find_or_fail_operation', function() use ($id, $columns) {
            return static::findOrFail($id, $columns);
        });
    }

    /**
     * Safe create operation with failover protection
     *
     * @param array $attributes Model attributes
     * @return static Created model instance
     */
    public function createSafely(array $attributes): static
    {
        return $this->executeFailsafeTransaction('create_operation', function() use ($attributes) {
            return static::create($attributes);
        });
    }

    /**
     * Safe update operation with failover protection
     *
     * @param array $attributes Attributes to update
     * @return bool Update success status
     */
    public function updateSafely(array $attributes): bool
    {
        return $this->executeFailsafeTransaction('update_operation', function() use ($attributes) {
            return $this->update($attributes);
        });
    }

    /**
     * Safe delete operation with failover protection
     *
     * @return bool|null Delete success status
     */
    public function deleteSafely(): ?bool
    {
        return $this->executeFailsafeTransaction('delete_operation', function() {
            return $this->delete();
        });
    }

    /**
     * Safe bulk insert operation with failover protection
     *
     * @param array $records Array of records to insert
     * @return bool Insert success status
     */
    public static function insertSafely(array $records): bool
    {
        $instance = new static();
        return $instance->executeFailsafeTransaction('bulk_insert_operation', function() use ($records) {
            return static::insert($records);
        });
    }

    /**
     * Safe bulk update operation with failover protection
     *
     * @param array $conditions Where conditions
     * @param array $attributes Attributes to update
     * @return int Number of affected rows
     */
    public static function updateWhereSafely(array $conditions, array $attributes): int
    {
        $instance = new static();
        return $instance->executeFailsafeTransaction('bulk_update_operation', function() use ($conditions, $attributes) {
            $query = static::query();
            foreach ($conditions as $column => $value) {
                $query->where($column, $value);
            }
            return $query->update($attributes);
        });
    }

    /**
     * Safe aggregation operations with failover protection
     *
     * @param string $operation Aggregation operation (count, sum, avg, max, min)
     * @param string|null $column Column name for aggregation
     * @param array $conditions Where conditions
     * @return mixed Aggregation result
     */
    public static function aggregateSafely(string $operation, ?string $column = null, array $conditions = []): mixed
    {
        $instance = new static();
        return $instance->executeFailsafeQuery('aggregate_operation', function() use ($operation, $column, $conditions) {
            $query = static::query();
            
            // Apply conditions using modern Laravel collection methods
            collect($conditions)->each(function($value, $key) use ($query) {
                $query->where($key, $value);
            });

            return match($operation) {
                'count' => $query->count($column),
                'sum' => $query->sum($column),
                'avg' => $query->avg($column),
                'max' => $query->max($column),
                'min' => $query->min($column),
                default => throw new \InvalidArgumentException("Unsupported aggregation operation: {$operation}")
            };
        });
    }

    /**
     * Safe paginated query with failover protection
     *
     * @param int $perPage Items per page
     * @param array $columns Columns to select
     * @param string $pageName Page parameter name
     * @param int|null $page Current page number
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function paginateSafely(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $instance = new static();
        return $instance->executeFailsafeQuery('paginate_operation', function() use ($perPage, $columns, $pageName, $page) {
            return static::paginate($perPage, $columns, $pageName, $page);
        });
    }

    /**
     * Safe relationship loading with failover protection
     *
     * @param array|string $relations Relations to load
     * @return static Model instance with loaded relations
     */
    public function loadSafely(array|string $relations): static
    {
        $this->executeFailsafeQuery('load_relations_operation', function() use ($relations) {
            $this->load($relations);
        });
        
        return $this;
    }

    /**
     * Safe relationship counting with failover protection
     *
     * @param array|string $relations Relations to count
     * @return static Model instance with relation counts
     */
    public function loadCountSafely(array|string $relations): static
    {
        $this->executeFailsafeQuery('load_count_operation', function() use ($relations) {
            $this->loadCount($relations);
        });
        
        return $this;
    }

    /**
     * Execute raw SQL query safely with failover protection
     *
     * @param string $sql Raw SQL query
     * @param array $bindings Query bindings
     * @param string $operationName Operation name for circuit breaker
     * @return mixed Query result
     */
    protected function executeRawQuerySafely(string $sql, array $bindings = [], string $operationName = 'raw_query'): mixed
    {
        return $this->executeFailsafeQuery($operationName, function() use ($sql, $bindings) {
            return DB::select($sql, $bindings);
        });
    }

    /**
     * Build circuit breaker name for the operation
     *
     * @param string $operationName Base operation name
     * @return string Circuit breaker name
     */
    private function buildCircuitName(string $operationName): string
    {
        $modelName = class_basename(static::class);
        $tableName = $this->getTable();
        
        return "eloquent_{$modelName}_{$tableName}_{$operationName}";
    }

    /**
     * Get failover configuration with model-specific defaults
     *
     * @param array $options Override options
     * @return array Configuration array
     */
    private function getFailoverConfig(array $options = []): array
    {
        $modelClass = static::class;
        
        if (!isset(self::$failoverConfig[$modelClass])) {
            $defaultConfig = [
                'failure_threshold' => 3, // Lower threshold for model operations
                'recovery_timeout' => 15, // Faster recovery for models
                'expected_exceptions' => [
                    \Illuminate\Database\QueryException::class,
                    \Illuminate\Database\Eloquent\ModelNotFoundException::class,
                    \PDOException::class,
                    \Illuminate\Database\ConnectionException::class,
                    \Illuminate\Database\DeadlockException::class,
                ],
                'tags' => [
                    'model' => class_basename(static::class),
                    'table' => $this->getTable(),
                    'service' => $this->getServiceName(),
                    'operation_type' => 'eloquent'
                ]
            ];
            
            // Merge with model-specific config if available
            $modelConfig = config("database-failover.models." . class_basename(static::class), []);
            self::$failoverConfig[$modelClass] = array_merge($defaultConfig, $modelConfig);
        }
        
        return array_merge(self::$failoverConfig[$modelClass], $options);
    }

    /**
     * Get model-specific health check information
     *
     * @return array Health check data
     */
    public function getModelHealthCheck(): array
    {
        $modelName = class_basename(static::class);
        $tableName = $this->getTable();
        
        try {
            // Test basic connectivity
            $connectionTest = $this->executeFailsafeQuery('health_check_connection', function() {
                return DB::connection($this->getConnectionName())->getPdo() !== null;
            });
            
            // Test table accessibility
            $tableTest = $this->executeFailsafeQuery('health_check_table', function() use ($tableName) {
                return DB::table($tableName)->limit(1)->exists();
            });
            
            // Get circuit breaker stats for common operations
            $circuitStats = [
                'find_operation' => $this->getCircuitBreakerStats($this->buildCircuitName('find_operation')),
                'create_operation' => $this->getCircuitBreakerStats($this->buildCircuitName('create_operation')),
                'update_operation' => $this->getCircuitBreakerStats($this->buildCircuitName('update_operation')),
            ];
            
            return [
                'model' => $modelName,
                'table' => $tableName,
                'connection' => $this->getConnectionName(),
                'connection_test' => $connectionTest,
                'table_test' => $tableTest,
                'circuit_breakers' => $circuitStats,
                'timestamp' => now()->toISOString(),
                'status' => $connectionTest && $tableTest ? 'healthy' : 'unhealthy'
            ];
            
        } catch (\Exception $e) {
            return [
                'model' => $modelName,
                'table' => $tableName,
                'connection' => $this->getConnectionName(),
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Log model operation for monitoring
     *
     * @param string $operation Operation name
     * @param array $context Additional context
     */
    protected function logModelOperation(string $operation, array $context = []): void
    {
        SharedLog::databaseFailover('eloquent_model_operation', array_merge([
            'model' => class_basename(static::class),
            'table' => $this->getTable(),
            'operation' => $operation,
            'connection' => $this->getConnectionName(),
            'service' => $this->getServiceName(),
            'timestamp' => now()->toISOString()
        ], $context));
    }

    /**
     * Handle model operation failure
     *
     * @param string $operation Operation name
     * @param \Exception $exception Exception that occurred
     * @param array $context Additional context
     */
    protected function handleModelOperationFailure(string $operation, \Exception $exception, array $context = []): void
    {
        $this->logModelOperation($operation . '_failed', array_merge([
            'error_message' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'severity' => 'error'
        ], $context));
        
        Log::error("Model operation failed", [
            'model' => class_basename(static::class),
            'operation' => $operation,
            'error' => $exception->getMessage(),
            'service' => $this->getServiceName()
        ]);
    }

    /**
     * Concrete implementations for EloquentDatabaseFailoverEvents trait
     * Modern PHP 8.3 & Laravel 12 implementation
     */

    /**
     * Perform model creation operation
     * Modern implementation with error handling
     */
    protected function performCreate(): bool
    {
        try {
            return $this->save();
        } catch (\Exception $e) {
            $this->handleModelOperationFailure('create', $e);
            return false;
        }
    }

    /**
     * Perform model update operation
     * Modern implementation with error handling
     */
    protected function performUpdate(): bool
    {
        try {
            return $this->save();
        } catch (\Exception $e) {
            $this->handleModelOperationFailure('update', $e);
            return false;
        }
    }

    /**
     * Perform model deletion operation
     * Modern implementation with error handling
     */
    protected function performDelete(): bool
    {
        try {
            return $this->delete() !== false;
        } catch (\Exception $e) {
            $this->handleModelOperationFailure('delete', $e);
            return false;
        }
    }

    /**
     * Build circuit breaker name for operation
     * Consistent naming convention across the trait
     */
    protected function buildCircuitName(string $operation): string
    {
        $modelName = class_basename(static::class);
        $tableName = $this->getTable();
        
        return "eloquent_{$modelName}_{$tableName}_{$operation}";
    }

    /**
     * Execute protected query with circuit breaker
     * Delegates to DatabaseQueryCircuitBreaker trait
     */
    protected function executeProtectedQuery(string $circuitName, callable $query): mixed
    {
        $config = $this->getFailoverConfig();
        return parent::executeProtectedQuery($circuitName, $query, $config);
    }

    /**
     * Check if circuit breaker is open for operation
     * Modern PHP 8.3 implementation
     */
    protected function isCircuitBreakerOpen(string $circuitName): bool
    {
        try {
            $stats = $this->getCircuitBreakerStats($circuitName);
            return ($stats['state'] ?? 'closed') === 'open';
        } catch (\Exception $e) {
            // If we can't determine state, assume closed for safety
            return false;
        }
    }

    /**
     * Record successful operation for circuit breaker
     * Modern implementation with comprehensive logging
     */
    protected function recordOperationSuccess(string $operation): void
    {
        $circuitName = $this->buildCircuitName($operation);
        
        try {
            // Record success in circuit breaker
            $this->recordCircuitBreakerSuccess($circuitName);
            
            // Log success for monitoring
            $this->logModelOperation($operation . '_success', [
                'circuit_name' => $circuitName,
                'model_id' => $this->getKey(),
                'success' => true,
            ]);
            
        } catch (\Exception $e) {
            // Don't fail the operation if logging fails
            Log::warning("Failed to record operation success", [
                'model' => class_basename(static::class),
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log successful failover operation
     * Modern PHP 8.3 implementation with comprehensive context
     */
    protected function logFailoverOperationSuccess(string $operation): void
    {
        SharedLog::databaseFailover('eloquent_failover_operation_success', [
            'model' => class_basename(static::class),
            'table' => $this->getTable(),
            'operation' => $operation,
            'model_id' => $this->getKey(),
            'connection' => $this->getConnectionName(),
            'circuit_breaker_protected' => true,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log failed failover operation
     * Modern PHP 8.3 implementation with error context
     */
    protected function logFailoverOperationFailure(string $operation, string $error): void
    {
        SharedLog::databaseFailover('eloquent_failover_operation_failure', [
            'model' => class_basename(static::class),
            'table' => $this->getTable(),
            'operation' => $operation,
            'model_id' => $this->getKey(),
            'connection' => $this->getConnectionName(),
            'error_message' => $error,
            'circuit_breaker_protected' => true,
            'severity' => 'error',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record circuit breaker success
     * Helper method for circuit breaker integration
     */
    protected function recordCircuitBreakerSuccess(string $circuitName): void
    {
        // This would integrate with the actual circuit breaker implementation
        // For now, we'll use cache to track success
        $successKey = "circuit_breaker_success_{$circuitName}";
        $currentCount = cache()->get($successKey, 0);
        cache()->put($successKey, $currentCount + 1, 3600);
    }

    /**
     * Get service name for logging context
     * Modern implementation with fallback
     */
    protected function getServiceName(): string
    {
        // Try to determine service name from environment or config
        return config('app.service_name') ?? 
               env('SERVICE_NAME') ?? 
               'unknown_service';
    }
}
