<?php

namespace Shared\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shared\Facades\SharedLog;
use Fuse\CircuitBreaker;
use Fuse\CircuitBreakerFactory;

/**
 * DatabaseQueryCircuitBreaker Trait
 * 
 * Provides circuit breaker protection for database queries to prevent cascading failures
 * and improve system resilience. Integrates with Fuse circuit breaker and SharedLog
 * for comprehensive monitoring and alerting.
 * 
 * Usage:
 * ```php
 * use Shared\Traits\DatabaseQueryCircuitBreaker;
 * 
 * class MyService {
 *     use DatabaseQueryCircuitBreaker;
 *     
 *     public function getData() {
 *         return $this->executeProtectedQuery('user_queries', function() {
 *             return DB::table('users')->where('active', true)->get();
 *         });
 *     }
 * }
 * ```
 */
trait DatabaseQueryCircuitBreaker
{
    /**
     * Circuit breaker instances cache
     */
    private static array $circuitBreakers = [];

    /**
     * Execute a database query with circuit breaker protection
     *
     * @param string $circuitName Unique name for the circuit breaker
     * @param callable $query The database query to execute
     * @param array $options Circuit breaker configuration options
     * @return mixed Query result
     * @throws \Exception When circuit is open or query fails
     */
    protected function executeProtectedQuery(string $circuitName, callable $query, array $options = [])
    {
        $circuitBreaker = $this->getQueryCircuitBreaker($circuitName, $options);
        $queryId = $this->generateQueryId();
        
        // Log query execution attempt
        SharedLog::databaseFailover('query_circuit_breaker_attempt', [
            'circuit_name' => $circuitName,
            'query_id' => $queryId,
            'circuit_state' => $circuitBreaker->getState(),
            'connection' => DB::getDefaultConnection(),
            'service_name' => $this->getServiceName()
        ]);

        try {
            $result = $circuitBreaker->call($query);
            
            // Log successful query execution
            SharedLog::databaseFailover('query_circuit_breaker_success', [
                'circuit_name' => $circuitName,
                'query_id' => $queryId,
                'circuit_state' => $circuitBreaker->getState(),
                'connection' => DB::getDefaultConnection(),
                'service_name' => $this->getServiceName()
            ]);
            
            return $result;
            
        } catch (\Fuse\CircuitOpenException $e) {
            // Circuit is open - log critical alert
            SharedLog::databaseFailover('query_circuit_breaker_open', [
                'circuit_name' => $circuitName,
                'query_id' => $queryId,
                'circuit_state' => 'open',
                'connection' => DB::getDefaultConnection(),
                'service_name' => $this->getServiceName(),
                'failure_count' => $circuitBreaker->getFailureCount(),
                'severity' => 'critical'
            ]);
            
            Log::error("Database query circuit breaker is open", [
                'circuit_name' => $circuitName,
                'query_id' => $queryId,
                'service' => $this->getServiceName()
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            // Query failed - log failure
            SharedLog::databaseFailover('query_circuit_breaker_failure', [
                'circuit_name' => $circuitName,
                'query_id' => $queryId,
                'circuit_state' => $circuitBreaker->getState(),
                'connection' => DB::getDefaultConnection(),
                'service_name' => $this->getServiceName(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'failure_count' => $circuitBreaker->getFailureCount()
            ]);
            
            Log::error("Database query failed in circuit breaker", [
                'circuit_name' => $circuitName,
                'query_id' => $queryId,
                'error' => $e->getMessage(),
                'service' => $this->getServiceName()
            ]);
            
            throw $e;
        }
    }

    /**
     * Execute a database transaction with circuit breaker protection
     *
     * @param string $circuitName Unique name for the circuit breaker
     * @param callable $transaction The transaction to execute
     * @param array $options Circuit breaker configuration options
     * @return mixed Transaction result
     * @throws \Exception When circuit is open or transaction fails
     */
    protected function executeProtectedTransaction(string $circuitName, callable $transaction, array $options = [])
    {
        $circuitBreaker = $this->getQueryCircuitBreaker($circuitName, $options);
        $transactionId = $this->generateQueryId();
        
        // Log transaction attempt
        SharedLog::databaseFailover('transaction_circuit_breaker_attempt', [
            'circuit_name' => $circuitName,
            'transaction_id' => $transactionId,
            'circuit_state' => $circuitBreaker->getState(),
            'connection' => DB::getDefaultConnection(),
            'service_name' => $this->getServiceName()
        ]);

        try {
            $result = $circuitBreaker->call(function() use ($transaction) {
                return DB::transaction($transaction);
            });
            
            // Log successful transaction
            SharedLog::databaseFailover('transaction_circuit_breaker_success', [
                'circuit_name' => $circuitName,
                'transaction_id' => $transactionId,
                'circuit_state' => $circuitBreaker->getState(),
                'connection' => DB::getDefaultConnection(),
                'service_name' => $this->getServiceName()
            ]);
            
            return $result;
            
        } catch (\Fuse\CircuitOpenException $e) {
            // Circuit is open for transactions - critical alert
            SharedLog::databaseFailover('transaction_circuit_breaker_open', [
                'circuit_name' => $circuitName,
                'transaction_id' => $transactionId,
                'circuit_state' => 'open',
                'connection' => DB::getDefaultConnection(),
                'service_name' => $this->getServiceName(),
                'failure_count' => $circuitBreaker->getFailureCount(),
                'severity' => 'critical'
            ]);
            
            throw $e;
            
        } catch (\Exception $e) {
            // Transaction failed
            SharedLog::databaseFailover('transaction_circuit_breaker_failure', [
                'circuit_name' => $circuitName,
                'transaction_id' => $transactionId,
                'circuit_state' => $circuitBreaker->getState(),
                'connection' => DB::getDefaultConnection(),
                'service_name' => $this->getServiceName(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'failure_count' => $circuitBreaker->getFailureCount()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get or create a circuit breaker for database queries
     *
     * @param string $circuitName Unique circuit breaker name
     * @param array $options Configuration options
     * @return CircuitBreaker
     */
    protected function getQueryCircuitBreaker(string $circuitName, array $options = []): CircuitBreaker
    {
        $cacheKey = $circuitName . '_' . $this->getServiceName();
        
        if (!isset(self::$circuitBreakers[$cacheKey])) {
            $config = $this->getQueryCircuitBreakerConfig($options);
            
            // Log circuit breaker creation
            SharedLog::databaseFailover('query_circuit_breaker_created', [
                'circuit_name' => $circuitName,
                'service_name' => $this->getServiceName(),
                'config' => $config,
                'connection' => DB::getDefaultConnection()
            ]);
            
            self::$circuitBreakers[$cacheKey] = CircuitBreakerFactory::create($config);
            
            // Set up state change listener
            self::$circuitBreakers[$cacheKey]->onStateChange(function($state) use ($circuitName) {
                $this->handleCircuitStateChange($circuitName, $state);
            });
        }
        
        return self::$circuitBreakers[$cacheKey];
    }

    /**
     * Handle circuit breaker state changes
     *
     * @param string $circuitName Circuit breaker name
     * @param string $newState New circuit state
     */
    protected function handleCircuitStateChange(string $circuitName, string $newState): void
    {
        $severity = $newState === 'open' ? 'critical' : 'info';
        
        SharedLog::databaseFailover('query_circuit_breaker_state_change', [
            'circuit_name' => $circuitName,
            'new_state' => $newState,
            'service_name' => $this->getServiceName(),
            'connection' => DB::getDefaultConnection(),
            'timestamp' => now()->toISOString(),
            'severity' => $severity
        ]);
        
        if ($newState === 'open') {
            Log::critical("Database query circuit breaker opened", [
                'circuit_name' => $circuitName,
                'service' => $this->getServiceName()
            ]);
        } elseif ($newState === 'closed') {
            Log::info("Database query circuit breaker recovered", [
                'circuit_name' => $circuitName,
                'service' => $this->getServiceName()
            ]);
        }
    }

    /**
     * Get circuit breaker configuration for queries
     *
     * @param array $options Override options
     * @return array Configuration array
     */
    protected function getQueryCircuitBreakerConfig(array $options = []): array
    {
        $defaultConfig = config('circuit-breaker.query_defaults', [
            'failure_threshold' => 5,
            'recovery_timeout' => 30,
            'expected_exceptions' => [
                \Illuminate\Database\QueryException::class,
                \PDOException::class,
                \Illuminate\Database\ConnectionException::class
            ]
        ]);
        
        return array_merge($defaultConfig, $options);
    }

    /**
     * Generate a unique query ID for tracking
     *
     * @return string Unique query identifier
     */
    protected function generateQueryId(): string
    {
        return 'query_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }

    /**
     * Get the service name for logging context
     *
     * @return string Service name
     */
    protected function getServiceName(): string
    {
        // Try to detect service name from various sources
        if (defined('SERVICE_NAME')) {
            return SERVICE_NAME;
        }
        
        if ($serviceName = env('SERVICE_NAME')) {
            return $serviceName;
        }
        
        // Fallback to class name
        return class_basename(static::class);
    }

    /**
     * Get circuit breaker statistics for monitoring
     *
     * @param string $circuitName Circuit breaker name
     * @return array Statistics array
     */
    protected function getCircuitBreakerStats(string $circuitName): array
    {
        $cacheKey = $circuitName . '_' . $this->getServiceName();
        
        if (!isset(self::$circuitBreakers[$cacheKey])) {
            return ['status' => 'not_initialized'];
        }
        
        $circuitBreaker = self::$circuitBreakers[$cacheKey];
        
        return [
            'circuit_name' => $circuitName,
            'state' => $circuitBreaker->getState(),
            'failure_count' => $circuitBreaker->getFailureCount(),
            'service_name' => $this->getServiceName(),
            'connection' => DB::getDefaultConnection()
        ];
    }
}
