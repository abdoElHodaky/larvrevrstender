<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;
use Shared\Patterns\CircuitBreaker;
use Shared\Patterns\CircuitBreakerOpenException;
use Exception;

/**
 * Circuit Breaker Micro Procedure
 * 
 * Provides circuit breaker pattern integration for cross-service operations,
 * enabling fault tolerance and resilience in distributed systems.
 */
trait CircuitBreakerProcedure
{
    private array $circuitBreakers = [];

    /**
     * Execute operation with circuit breaker protection
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function executeWithCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service_name' => ['required' => true, 'type' => 'string'],
                'operation' => ['required' => true, 'type' => 'string'],
                'operation_params' => ['type' => 'array'],
                'fallback_operation' => ['type' => 'string'],
                'fallback_params' => ['type' => 'array'],
                'circuit_config' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $serviceName = $params['service_name'];
            $operation = $params['operation'];
            $operationParams = $params['operation_params'] ?? [];
            $fallbackOperation = $params['fallback_operation'] ?? null;
            $fallbackParams = $params['fallback_params'] ?? [];
            $circuitConfig = $params['circuit_config'] ?? [];

            // Get or create circuit breaker
            $circuitBreaker = $this->getCircuitBreaker($serviceName, $circuitConfig);

            // Define the main operation
            $mainOperation = function() use ($operation, $operationParams, $context) {
                return $this->executeServiceOperation($operation, $operationParams, $context);
            };

            // Define the fallback operation
            $fallbackCallback = null;
            if ($fallbackOperation) {
                $fallbackCallback = function() use ($fallbackOperation, $fallbackParams, $context) {
                    return $this->executeServiceOperation($fallbackOperation, $fallbackParams, $context);
                };
            }

            // Execute with circuit breaker protection
            $result = $circuitBreaker->execute($mainOperation, $fallbackCallback);

            $this->recordMetric('circuit_breaker_execution', 1, [
                'service' => $serviceName,
                'operation' => $operation,
                'state' => $circuitBreaker->getState(),
                'success' => true
            ]);

            return $this->successResponse([
                'result' => $result,
                'circuit_breaker_state' => $circuitBreaker->getState(),
                'service_name' => $serviceName,
                'operation' => $operation
            ], 'Operation executed successfully with circuit breaker protection');

        } catch (CircuitBreakerOpenException $e) {
            $this->log('warning', 'Circuit breaker is open', [
                'service' => $params['service_name'] ?? null,
                'operation' => $params['operation'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Service temporarily unavailable', [
                'circuit_breaker_open' => true,
                'service_name' => $params['service_name'],
                'message' => $e->getMessage()
            ]);

        } catch (Exception $e) {
            $this->log('error', 'Circuit breaker execution failed', [
                'error' => $e->getMessage(),
                'service' => $params['service_name'] ?? null,
                'operation' => $params['operation'] ?? null
            ]);

            return $this->errorResponse('Circuit breaker execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Get circuit breaker statistics
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getCircuitBreakerStats(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service_name' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $serviceName = $params['service_name'] ?? null;

            if ($serviceName) {
                // Get stats for specific service
                $circuitBreaker = $this->getCircuitBreaker($serviceName);
                $stats = [$serviceName => $circuitBreaker->getStats()];
            } else {
                // Get stats for all services
                $stats = [];
                foreach ($this->circuitBreakers as $service => $circuitBreaker) {
                    $stats[$service] = $circuitBreaker->getStats();
                }
            }

            return $this->successResponse([
                'circuit_breaker_stats' => $stats,
                'total_services' => count($stats),
                'retrieved_at' => now()->toISOString()
            ], 'Circuit breaker statistics retrieved');

        } catch (Exception $e) {
            $this->log('error', 'Circuit breaker stats retrieval failed', [
                'error' => $e->getMessage(),
                'service' => $params['service_name'] ?? null
            ]);

            return $this->errorResponse('Circuit breaker stats retrieval failed: ' . $e->getMessage());
        }
    }

    /**
     * Reset circuit breaker
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function resetCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service_name' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $serviceName = $params['service_name'];

            $circuitBreaker = $this->getCircuitBreaker($serviceName);
            $oldState = $circuitBreaker->getState();
            
            $circuitBreaker->reset();

            $this->log('info', 'Circuit breaker reset', [
                'service' => $serviceName,
                'old_state' => $oldState,
                'new_state' => $circuitBreaker->getState()
            ]);

            $this->recordMetric('circuit_breaker_reset', 1, [
                'service' => $serviceName,
                'old_state' => $oldState
            ]);

            return $this->successResponse([
                'service_name' => $serviceName,
                'old_state' => $oldState,
                'new_state' => $circuitBreaker->getState(),
                'reset_at' => now()->toISOString()
            ], 'Circuit breaker reset successfully');

        } catch (Exception $e) {
            $this->log('error', 'Circuit breaker reset failed', [
                'error' => $e->getMessage(),
                'service' => $params['service_name'] ?? null
            ]);

            return $this->errorResponse('Circuit breaker reset failed: ' . $e->getMessage());
        }
    }

    /**
     * Force open circuit breaker (for testing/maintenance)
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function forceOpenCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service_name' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $serviceName = $params['service_name'];

            $circuitBreaker = $this->getCircuitBreaker($serviceName);
            $oldState = $circuitBreaker->getState();
            
            $circuitBreaker->forceOpen();

            $this->log('warning', 'Circuit breaker forced open', [
                'service' => $serviceName,
                'old_state' => $oldState,
                'new_state' => $circuitBreaker->getState()
            ]);

            $this->recordMetric('circuit_breaker_forced_open', 1, [
                'service' => $serviceName,
                'old_state' => $oldState
            ]);

            return $this->successResponse([
                'service_name' => $serviceName,
                'old_state' => $oldState,
                'new_state' => $circuitBreaker->getState(),
                'forced_open_at' => now()->toISOString()
            ], 'Circuit breaker forced open');

        } catch (Exception $e) {
            $this->log('error', 'Circuit breaker force open failed', [
                'error' => $e->getMessage(),
                'service' => $params['service_name'] ?? null
            ]);

            return $this->errorResponse('Circuit breaker force open failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute HTTP request with circuit breaker protection
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function executeHttpWithCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service_name' => ['required' => true, 'type' => 'string'],
                'url' => ['required' => true, 'type' => 'string'],
                'method' => ['required' => true, 'type' => 'string'],
                'data' => ['type' => 'array'],
                'headers' => ['type' => 'array'],
                'timeout' => ['type' => 'int'],
                'circuit_config' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $serviceName = $params['service_name'];
            $url = $params['url'];
            $method = strtoupper($params['method']);
            $data = $params['data'] ?? [];
            $headers = $params['headers'] ?? [];
            $timeout = $params['timeout'] ?? 30;
            $circuitConfig = $params['circuit_config'] ?? [];

            // Get circuit breaker
            $circuitBreaker = $this->getCircuitBreaker($serviceName, $circuitConfig);

            // Define HTTP operation
            $httpOperation = function() use ($url, $method, $data, $headers, $timeout) {
                return $this->makeHttpRequest($url, $method, $data, $headers, $timeout);
            };

            // Define fallback (return cached response or default)
            $fallbackOperation = function() use ($serviceName, $url, $method) {
                return $this->getHttpFallbackResponse($serviceName, $url, $method);
            };

            // Execute with circuit breaker
            $result = $circuitBreaker->execute($httpOperation, $fallbackOperation);

            return $this->successResponse([
                'result' => $result,
                'circuit_breaker_state' => $circuitBreaker->getState(),
                'service_name' => $serviceName,
                'url' => $url,
                'method' => $method
            ], 'HTTP request executed with circuit breaker protection');

        } catch (CircuitBreakerOpenException $e) {
            return $this->errorResponse('Service temporarily unavailable', [
                'circuit_breaker_open' => true,
                'service_name' => $params['service_name'],
                'url' => $params['url'],
                'message' => $e->getMessage()
            ]);

        } catch (Exception $e) {
            $this->log('error', 'HTTP circuit breaker execution failed', [
                'error' => $e->getMessage(),
                'service' => $params['service_name'] ?? null,
                'url' => $params['url'] ?? null
            ]);

            return $this->errorResponse('HTTP circuit breaker execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Get or create circuit breaker for service
     *
     * @param string $serviceName
     * @param array $config
     * @return CircuitBreaker
     */
    private function getCircuitBreaker(string $serviceName, array $config = []): CircuitBreaker
    {
        if (!isset($this->circuitBreakers[$serviceName])) {
            $failureThreshold = $config['failure_threshold'] ?? 5;
            $recoveryTimeout = $config['recovery_timeout'] ?? 60;
            $requestTimeout = $config['request_timeout'] ?? 30;

            $this->circuitBreakers[$serviceName] = new CircuitBreaker(
                $serviceName,
                $failureThreshold,
                $recoveryTimeout,
                $requestTimeout
            );
        }

        return $this->circuitBreakers[$serviceName];
    }

    /**
     * Execute service operation
     *
     * @param string $operation
     * @param array $params
     * @param array $context
     * @return mixed
     */
    private function executeServiceOperation(string $operation, array $params, array $context)
    {
        // This would call the actual service operation
        // For now, simulate operation execution
        
        if (method_exists($this, $operation)) {
            return $this->$operation($params, $context);
        }
        
        throw new Exception("Operation '{$operation}' not found");
    }

    /**
     * Make HTTP request
     *
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $headers
     * @param int $timeout
     * @return array
     */
    private function makeHttpRequest(string $url, string $method, array $data, array $headers, int $timeout): array
    {
        // This would use your actual HTTP client (Guzzle, Laravel HTTP, etc.)
        // For now, simulate HTTP request
        
        $startTime = microtime(true);
        
        // Simulate network delay
        usleep(rand(100000, 500000)); // 0.1-0.5 seconds
        
        // Simulate occasional failures
        if (rand(1, 10) === 1) {
            throw new Exception('Simulated network error');
        }
        
        $duration = microtime(true) - $startTime;
        
        return [
            'status' => 200,
            'data' => [
                'url' => $url,
                'method' => $method,
                'data' => $data,
                'duration' => $duration,
                'timestamp' => now()->toISOString()
            ],
            'headers' => $headers,
            'success' => true
        ];
    }

    /**
     * Get HTTP fallback response
     *
     * @param string $serviceName
     * @param string $url
     * @param string $method
     * @return array
     */
    private function getHttpFallbackResponse(string $serviceName, string $url, string $method): array
    {
        // This would return cached response or default fallback
        return [
            'status' => 503,
            'data' => [
                'message' => 'Service temporarily unavailable',
                'service' => $serviceName,
                'fallback' => true,
                'timestamp' => now()->toISOString()
            ],
            'success' => false,
            'fallback' => true
        ];
    }
}

