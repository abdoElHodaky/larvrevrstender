<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Cross-Service Procedure Execution Engine
 * 
 * Handles execution of both micro procedures (single-responsibility) 
 * and macro procedures (complex workflows) with unified error handling,
 * logging, and response formatting.
 */
class ProcedureEngine
{
    private array $registeredProcedures = [];
    private array $middleware = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
            'enable_tracing' => true,
            'enable_metrics' => true,
            'max_execution_time' => 300, // 5 minutes for macro procedures
        ], $config);
    }

    /**
     * Register a procedure for execution
     *
     * @param string $name
     * @param string $class
     * @param string $type 'micro' or 'macro'
     * @param array $metadata
     * @return void
     */
    public function registerProcedure(string $name, string $class, string $type = 'micro', array $metadata = []): void
    {
        $this->registeredProcedures[$name] = [
            'class' => $class,
            'type' => $type,
            'metadata' => $metadata,
            'registered_at' => now()->toISOString()
        ];

        Log::info('Procedure registered', [
            'name' => $name,
            'class' => $class,
            'type' => $type
        ]);
    }

    /**
     * Register a callable procedure for execution
     * 
     * This method provides compatibility with the legacy registration pattern
     * used by services that register callable arrays. Supports both object methods
     * and direct callable objects.
     *
     * @param string $name
     * @param callable $callable
     * @param string $type 'micro' or 'macro'
     * @param array $metadata
     * @return void
     */
    public function register(string $name, callable $callable, string $type = 'micro', array $metadata = []): void
    {
        $this->registeredProcedures[$name] = [
            'callable' => $callable,
            'type' => $type,
            'metadata' => $metadata,
            'registered_at' => now()->toISOString()
        ];

        Log::info('Callable procedure registered', [
            'name' => $name,
            'type' => $type
        ]);
    }

    /**
     * Execute a procedure with comprehensive error handling
     *
     * @param string $procedureName
     * @param string $method
     * @param array $params
     * @param array $context
     * @return array
     */
    public function execute(string $procedureName, string $method, array $params = [], array $context = []): array
    {
        $startTime = microtime(true);
        $traceId = $context['trace_id'] ?? $this->generateTraceId();
        
        try {
            // Validate procedure exists
            if (!isset($this->registeredProcedures[$procedureName])) {
                throw new Exception("Procedure '{$procedureName}' not found");
            }

            $procedureInfo = $this->registeredProcedures[$procedureName];
            
            // Handle both class-based and callable-based procedures
            if (isset($procedureInfo['callable'])) {
                // Callable-based procedure (legacy pattern)
                $callable = $procedureInfo['callable'];
                if (!is_callable($callable)) {
                    throw new Exception("Registered callable for '{$procedureName}' is not callable");
                }
                $procedure = $callable;
            } else {
                // Class-based procedure (new pattern)
                $procedureClass = $procedureInfo['class'];

                // Validate procedure class exists
                if (!class_exists($procedureClass)) {
                    throw new Exception("Procedure class '{$procedureClass}' not found");
                }

                // Create procedure instance
                $procedure = new $procedureClass();

                // Validate method exists
                if (!method_exists($procedure, $method)) {
                    throw new Exception("Method '{$method}' not found in procedure '{$procedureName}'");
                }
            }

            // Execute middleware (authentication, authorization, rate limiting)
            $middlewareResult = $this->executeMiddleware($procedureName, $method, $params, $context);
            if (!$middlewareResult['success']) {
                return $middlewareResult;
            }

            // Set execution timeout based on procedure type
            $timeout = $procedureInfo['type'] === 'macro' 
                ? $this->config['max_execution_time'] 
                : $this->config['timeout'];

            set_time_limit($timeout);

            // Execute the procedure with retry logic
            $result = $this->executeWithRetry($procedure, $method, $params, $context);

            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Log successful execution
            Log::info('Procedure executed successfully', [
                'procedure' => $procedureName,
                'method' => $method,
                'execution_time_ms' => $executionTime,
                'trace_id' => $traceId,
                'type' => $procedureInfo['type']
            ]);

            // Collect metrics if enabled
            if ($this->config['enable_metrics']) {
                $this->collectMetrics($procedureName, $method, $executionTime, 'success', $procedureInfo['type']);
            }

            return [
                'success' => true,
                'data' => $result,
                'metadata' => [
                    'procedure' => $procedureName,
                    'method' => $method,
                    'execution_time_ms' => $executionTime,
                    'trace_id' => $traceId,
                    'type' => $procedureInfo['type'],
                    'timestamp' => now()->toISOString()
                ]
            ];

        } catch (Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('Procedure execution failed', [
                'procedure' => $procedureName,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time_ms' => $executionTime,
                'trace_id' => $traceId
            ]);

            // Collect error metrics
            if ($this->config['enable_metrics']) {
                $this->collectMetrics($procedureName, $method, $executionTime, 'error', 
                    $this->registeredProcedures[$procedureName]['type'] ?? 'unknown');
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'metadata' => [
                    'procedure' => $procedureName,
                    'method' => $method,
                    'execution_time_ms' => $executionTime,
                    'trace_id' => $traceId,
                    'timestamp' => now()->toISOString()
                ]
            ];
        }
    }

    /**
     * Execute procedure with retry logic
     *
     * @param object $procedure
     * @param string $method
     * @param array $params
     * @param array $context
     * @return mixed
     */
    private function executeWithRetry($procedure, string $method, array $params, array $context)
    {
        $attempts = 0;
        $maxAttempts = $this->config['retry_attempts'];

        while ($attempts < $maxAttempts) {
            try {
                // Handle both callable and class-based procedures
                if (is_callable($procedure)) {
                    // For callable procedures, call directly
                    return call_user_func($procedure, $params, $context);
                } else {
                    // For class-based procedures, call the method
                    return $procedure->$method($params, $context);
                }
            } catch (Exception $e) {
                $attempts++;
                
                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                // Check if error is retryable
                if (!$this->isRetryableError($e)) {
                    throw $e;
                }

                // Wait before retry
                usleep($this->config['retry_delay'] * 1000 * $attempts); // Exponential backoff
                
                Log::warning('Procedure execution failed, retrying', [
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Execute middleware stack
     *
     * @param string $procedureName
     * @param string $method
     * @param array $params
     * @param array $context
     * @return array
     */
    private function executeMiddleware(string $procedureName, string $method, array $params, array $context): array
    {
        foreach ($this->middleware as $middleware) {
            $result = $middleware->handle($procedureName, $method, $params, $context);
            if (!$result['success']) {
                return $result;
            }
        }

        return ['success' => true];
    }

    /**
     * Check if error is retryable
     *
     * @param Exception $e
     * @return bool
     */
    private function isRetryableError(Exception $e): bool
    {
        $retryableErrors = [
            'Connection timeout',
            'Database connection lost',
            'Service temporarily unavailable',
            'Rate limit exceeded'
        ];

        foreach ($retryableErrors as $retryableError) {
            if (strpos($e->getMessage(), $retryableError) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate unique trace ID
     *
     * @return string
     */
    private function generateTraceId(): string
    {
        return uniqid('trace_', true) . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Collect execution metrics
     *
     * @param string $procedure
     * @param string $method
     * @param float $executionTime
     * @param string $status
     * @param string $type
     * @return void
     */
    private function collectMetrics(string $procedure, string $method, float $executionTime, string $status, string $type): void
    {
        // This would integrate with your metrics collection system (Prometheus, StatsD, etc.)
        // For now, we'll log the metrics
        Log::info('Procedure metrics', [
            'procedure' => $procedure,
            'method' => $method,
            'execution_time_ms' => $executionTime,
            'status' => $status,
            'type' => $type,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Add middleware to the execution stack
     *
     * @param object $middleware
     * @return void
     */
    public function addMiddleware($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Get all registered procedures
     *
     * @return array
     */
    public function getRegisteredProcedures(): array
    {
        return $this->registeredProcedures;
    }

    /**
     * Get procedure information
     *
     * @param string $name
     * @return array|null
     */
    public function getProcedureInfo(string $name): ?array
    {
        return $this->registeredProcedures[$name] ?? null;
    }

    /**
     * Health check for the procedure engine
     *
     * @return array
     */
    public function healthCheck(): array
    {
        return [
            'status' => 'healthy',
            'registered_procedures' => count($this->registeredProcedures),
            'middleware_count' => count($this->middleware),
            'config' => $this->config,
            'timestamp' => now()->toISOString()
        ];
    }
}
