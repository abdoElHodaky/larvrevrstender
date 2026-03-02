<?php

namespace Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shared\Facades\SharedLog;
use Shared\Traits\DatabaseQueryCircuitBreaker;
use Fuse\CircuitBreaker;

/**
 * QueryExecutionService
 * 
 * A dedicated service for executing database queries with circuit breaker protection.
 * This service provides a clean API for protected database operations and integrates
 * with the existing Fuse circuit breaker infrastructure and SharedLog monitoring.
 * 
 * Features:
 * - Circuit breaker protection for all database operations
 * - Comprehensive logging and monitoring
 * - Transaction support with rollback protection
 * - Query performance tracking
 * - Automatic retry mechanisms
 * - Connection-aware circuit breaker management
 */
class QueryExecutionService
{
    use DatabaseQueryCircuitBreaker;

    /**
     * Service name for logging context
     */
    private string $serviceName;

    /**
     * Default circuit breaker configuration
     */
    private array $defaultConfig;

    /**
     * Query performance tracking
     */
    private array $performanceMetrics = [];

    public function __construct(string $serviceName = null)
    {
        $this->serviceName = $serviceName ?? $this->detectServiceName();
        $this->defaultConfig = config('circuit-breaker.query_defaults', [
            'failure_threshold' => 5,
            'recovery_timeout' => 30,
            'expected_exceptions' => [
                \Illuminate\Database\QueryException::class,
                \PDOException::class,
                \Illuminate\Database\ConnectionException::class
            ]
        ]);
    }

    /**
     * Execute a SELECT query with circuit breaker protection
     *
     * @param string $query SQL query string
     * @param array $bindings Query parameter bindings
     * @param string $connection Database connection name
     * @param array $circuitOptions Circuit breaker configuration overrides
     * @return \Illuminate\Support\Collection Query results
     */
    public function select(string $query, array $bindings = [], string $connection = null, array $circuitOptions = [])
    {
        $circuitName = $this->generateCircuitName('select', $connection);
        $startTime = microtime(true);
        
        try {
            $result = $this->executeProtectedQuery($circuitName, function() use ($query, $bindings, $connection) {
                return collect(DB::connection($connection)->select($query, $bindings));
            }, $circuitOptions);
            
            $this->recordPerformanceMetric('select', $startTime, true);
            return $result;
            
        } catch (\Exception $e) {
            $this->recordPerformanceMetric('select', $startTime, false);
            throw $e;
        }
    }

    /**
     * Execute an INSERT query with circuit breaker protection
     *
     * @param string $query SQL query string
     * @param array $bindings Query parameter bindings
     * @param string $connection Database connection name
     * @param array $circuitOptions Circuit breaker configuration overrides
     * @return bool Success status
     */
    public function insert(string $query, array $bindings = [], string $connection = null, array $circuitOptions = [])
    {
        $circuitName = $this->generateCircuitName('insert', $connection);
        $startTime = microtime(true);
        
        try {
            $result = $this->executeProtectedQuery($circuitName, function() use ($query, $bindings, $connection) {
                return DB::connection($connection)->insert($query, $bindings);
            }, $circuitOptions);
            
            $this->recordPerformanceMetric('insert', $startTime, true);
            
            // Log successful insert operation
            SharedLog::databaseFailover('query_execution_insert_success', [
                'service_name' => $this->serviceName,
                'connection' => $connection ?? DB::getDefaultConnection(),
                'circuit_name' => $circuitName,
                'query_type' => 'insert'
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->recordPerformanceMetric('insert', $startTime, false);
            throw $e;
        }
    }

    /**
     * Execute an UPDATE query with circuit breaker protection
     *
     * @param string $query SQL query string
     * @param array $bindings Query parameter bindings
     * @param string $connection Database connection name
     * @param array $circuitOptions Circuit breaker configuration overrides
     * @return int Number of affected rows
     */
    public function update(string $query, array $bindings = [], string $connection = null, array $circuitOptions = [])
    {
        $circuitName = $this->generateCircuitName('update', $connection);
        $startTime = microtime(true);
        
        try {
            $result = $this->executeProtectedQuery($circuitName, function() use ($query, $bindings, $connection) {
                return DB::connection($connection)->update($query, $bindings);
            }, $circuitOptions);
            
            $this->recordPerformanceMetric('update', $startTime, true);
            
            // Log update operation with affected rows
            SharedLog::databaseFailover('query_execution_update_success', [
                'service_name' => $this->serviceName,
                'connection' => $connection ?? DB::getDefaultConnection(),
                'circuit_name' => $circuitName,
                'query_type' => 'update',
                'affected_rows' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->recordPerformanceMetric('update', $startTime, false);
            throw $e;
        }
    }

    /**
     * Execute a DELETE query with circuit breaker protection
     *
     * @param string $query SQL query string
     * @param array $bindings Query parameter bindings
     * @param string $connection Database connection name
     * @param array $circuitOptions Circuit breaker configuration overrides
     * @return int Number of affected rows
     */
    public function delete(string $query, array $bindings = [], string $connection = null, array $circuitOptions = [])
    {
        $circuitName = $this->generateCircuitName('delete', $connection);
        $startTime = microtime(true);
        
        try {
            $result = $this->executeProtectedQuery($circuitName, function() use ($query, $bindings, $connection) {
                return DB::connection($connection)->delete($query, $bindings);
            }, $circuitOptions);
            
            $this->recordPerformanceMetric('delete', $startTime, true);
            
            // Log delete operation with affected rows
            SharedLog::databaseFailover('query_execution_delete_success', [
                'service_name' => $this->serviceName,
                'connection' => $connection ?? DB::getDefaultConnection(),
                'circuit_name' => $circuitName,
                'query_type' => 'delete',
                'affected_rows' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->recordPerformanceMetric('delete', $startTime, false);
            throw $e;
        }
    }

    /**
     * Execute a database transaction with circuit breaker protection
     *
     * @param callable $callback Transaction callback
     * @param int $attempts Number of retry attempts
     * @param string $connection Database connection name
     * @param array $circuitOptions Circuit breaker configuration overrides
     * @return mixed Transaction result
     */
    public function transaction(callable $callback, int $attempts = 1, string $connection = null, array $circuitOptions = [])
    {
        $circuitName = $this->generateCircuitName('transaction', $connection);
        $startTime = microtime(true);
        
        try {
            $result = $this->executeProtectedTransaction($circuitName, $callback, $circuitOptions);
            
            $this->recordPerformanceMetric('transaction', $startTime, true);
            return $result;
            
        } catch (\Exception $e) {
            $this->recordPerformanceMetric('transaction', $startTime, false);
            throw $e;
        }
    }

    /**
     * Execute a raw database statement with circuit breaker protection
     *
     * @param string $query SQL statement
     * @param array $bindings Parameter bindings
     * @param string $connection Database connection name
     * @param array $circuitOptions Circuit breaker configuration overrides
     * @return bool Success status
     */
    public function statement(string $query, array $bindings = [], string $connection = null, array $circuitOptions = [])
    {
        $circuitName = $this->generateCircuitName('statement', $connection);
        $startTime = microtime(true);
        
        try {
            $result = $this->executeProtectedQuery($circuitName, function() use ($query, $bindings, $connection) {
                return DB::connection($connection)->statement($query, $bindings);
            }, $circuitOptions);
            
            $this->recordPerformanceMetric('statement', $startTime, true);
            return $result;
            
        } catch (\Exception $e) {
            $this->recordPerformanceMetric('statement', $startTime, false);
            throw $e;
        }
    }

    /**
     * Execute an Eloquent query with circuit breaker protection
     *
     * @param callable $queryBuilder Eloquent query builder callback
     * @param string $modelName Model name for circuit naming
     * @param array $circuitOptions Circuit breaker configuration overrides
     * @return mixed Query result
     */
    public function eloquent(callable $queryBuilder, string $modelName = 'eloquent', array $circuitOptions = [])
    {
        $circuitName = $this->generateCircuitName($modelName, null);
        $startTime = microtime(true);
        
        try {
            $result = $this->executeProtectedQuery($circuitName, $queryBuilder, $circuitOptions);
            
            $this->recordPerformanceMetric('eloquent', $startTime, true);
            
            // Log Eloquent operation
            SharedLog::databaseFailover('query_execution_eloquent_success', [
                'service_name' => $this->serviceName,
                'model_name' => $modelName,
                'circuit_name' => $circuitName,
                'query_type' => 'eloquent'
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->recordPerformanceMetric('eloquent', $startTime, false);
            throw $e;
        }
    }

    /**
     * Get performance metrics for monitoring
     *
     * @return array Performance statistics
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * Get circuit breaker health status for all managed circuits
     *
     * @return array Health status for all circuits
     */
    public function getCircuitHealthStatus(): array
    {
        $status = [];
        
        foreach (self::$circuitBreakers as $cacheKey => $circuitBreaker) {
            $parts = explode('_', $cacheKey);
            $circuitName = implode('_', array_slice($parts, 0, -1));
            
            $status[$circuitName] = [
                'state' => $circuitBreaker->getState(),
                'failure_count' => $circuitBreaker->getFailureCount(),
                'service_name' => $this->serviceName
            ];
        }
        
        return $status;
    }

    /**
     * Reset a specific circuit breaker
     *
     * @param string $circuitName Circuit breaker name
     * @return bool Success status
     */
    public function resetCircuitBreaker(string $circuitName): bool
    {
        $cacheKey = $circuitName . '_' . $this->serviceName;
        
        if (isset(self::$circuitBreakers[$cacheKey])) {
            self::$circuitBreakers[$cacheKey]->reset();
            
            SharedLog::databaseFailover('query_circuit_breaker_reset', [
                'circuit_name' => $circuitName,
                'service_name' => $this->serviceName,
                'reset_timestamp' => now()->toISOString()
            ]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Generate a circuit breaker name based on operation and connection
     *
     * @param string $operation Database operation type
     * @param string $connection Database connection name
     * @return string Circuit breaker name
     */
    private function generateCircuitName(string $operation, string $connection = null): string
    {
        $connection = $connection ?? DB::getDefaultConnection();
        return "{$this->serviceName}_{$operation}_{$connection}";
    }

    /**
     * Record performance metrics for monitoring
     *
     * @param string $operation Operation type
     * @param float $startTime Start timestamp
     * @param bool $success Success status
     */
    private function recordPerformanceMetric(string $operation, float $startTime, bool $success): void
    {
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        if (!isset($this->performanceMetrics[$operation])) {
            $this->performanceMetrics[$operation] = [
                'total_queries' => 0,
                'successful_queries' => 0,
                'failed_queries' => 0,
                'total_duration_ms' => 0,
                'average_duration_ms' => 0,
                'min_duration_ms' => PHP_FLOAT_MAX,
                'max_duration_ms' => 0
            ];
        }
        
        $metrics = &$this->performanceMetrics[$operation];
        $metrics['total_queries']++;
        $metrics['total_duration_ms'] += $duration;
        $metrics['average_duration_ms'] = $metrics['total_duration_ms'] / $metrics['total_queries'];
        $metrics['min_duration_ms'] = min($metrics['min_duration_ms'], $duration);
        $metrics['max_duration_ms'] = max($metrics['max_duration_ms'], $duration);
        
        if ($success) {
            $metrics['successful_queries']++;
        } else {
            $metrics['failed_queries']++;
        }
        
        // Log performance metrics periodically
        if ($metrics['total_queries'] % 100 === 0) {
            SharedLog::databaseFailover('query_execution_performance_metrics', [
                'service_name' => $this->serviceName,
                'operation' => $operation,
                'metrics' => $metrics
            ]);
        }
    }

    /**
     * Detect service name from environment
     *
     * @return string Service name
     */
    private function detectServiceName(): string
    {
        if (defined('SERVICE_NAME')) {
            return SERVICE_NAME;
        }
        
        if ($serviceName = env('SERVICE_NAME')) {
            return $serviceName;
        }
        
        // Try to detect from application name
        if ($appName = config('app.name')) {
            return strtolower(str_replace(' ', '-', $appName));
        }
        
        return 'query-execution-service';
    }

    /**
     * Override getServiceName from trait
     *
     * @return string Service name
     */
    protected function getServiceName(): string
    {
        return $this->serviceName;
    }
}
