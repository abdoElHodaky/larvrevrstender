<?php

namespace App\RPC;

use Sajya\Server\Procedure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Sajya\Server\Exceptions\RuntimeException;

abstract class BaseProcedure extends Procedure
{
    /**
     * Validate request parameters
     * 
     * @param array $data
     * @param array $rules
     * @throws RuntimeException
     */
    protected function validate(array $data, array $rules): void
    {
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new RuntimeException(
                'Invalid parameters',
                -32602,
                $validator->errors()->toArray()
            );
        }
    }

    /**
     * Get correlation ID from request
     * 
     * @return string
     */
    protected function getCorrelationId(): string
    {
        return request()->header('X-Correlation-ID', uniqid('rpc_', true));
    }

    /**
     * Log RPC performance metrics
     * 
     * @param string $method
     * @param array $params
     * @param mixed $result
     * @param float $startTime
     */
    protected function logPerformance(string $method, array $params, mixed $result, float $startTime): void
    {
        $endTime = microtime(true);
        $metrics = [
            'type' => 'rpc_call',
            'service' => config('app.name'),
            'method' => $method,
            'correlation_id' => $this->getCorrelationId(),
            'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'params_count' => count($params),
            'success' => !is_null($result),
            'timestamp' => now()->toISOString(),
        ];
        
        Log::channel('performance')->info('RPC_PERFORMANCE', $metrics);
    }

    /**
     * Handle RPC exceptions with proper error codes
     * 
     * @param \Exception $e
     * @param string $context
     * @throws RuntimeException
     */
    protected function handleException(\Exception $e, string $context = ''): void
    {
        $errorCode = match(get_class($e)) {
            'Illuminate\Validation\ValidationException' => -32602,
            'Illuminate\Database\Eloquent\ModelNotFoundException' => -32001,
            'Illuminate\Auth\AuthenticationException' => -32003,
            'Illuminate\Auth\Access\AuthorizationException' => -32004,
            'Firebase\JWT\ExpiredException' => -32005,
            'Firebase\JWT\SignatureInvalidException' => -32006,
            default => -32603
        };

        Log::error('RPC Exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'context' => $context,
            'correlation_id' => $this->getCorrelationId(),
            'trace' => $e->getTraceAsString()
        ]);

        throw new RuntimeException(
            $context ? "$context: {$e->getMessage()}" : $e->getMessage(),
            $errorCode,
            ['context' => $context, 'original_error' => $e->getMessage()]
        );
    }

    /**
     * Execute procedure with performance logging
     * 
     * @param string $method
     * @param array $params
     * @param callable $callback
     * @return mixed
     */
    protected function executeWithLogging(string $method, array $params, callable $callback): mixed
    {
        $startTime = microtime(true);
        
        try {
            $result = $callback();
            $this->logPerformance($method, $params, $result, $startTime);
            return $result;
        } catch (\Exception $e) {
            $this->logPerformance($method, $params, null, $startTime);
            $this->handleException($e, $method);
        }
    }

    /**
     * Get service information
     * 
     * @return array
     */
    protected function getServiceInfo(): array
    {
        return [
            'service' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'octane_enabled' => config('octane.server') !== null,
            'rpc_enabled' => true,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Sanitize sensitive data for logging
     * 
     * @param array $data
     * @return array
     */
    protected function sanitizeForLogging(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'hash'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
}
