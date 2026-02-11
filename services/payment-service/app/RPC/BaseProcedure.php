<?php

namespace App\RPC;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

// Check if Sajya packages are available before using them
if (class_exists('Sajya\Server\Procedure')) {
    // Full RPC implementation when Sajya is available
    abstract class BaseProcedure extends \Sajya\Server\Procedure
    {
        /**
         * Validate request parameters
         *
         * @throws RuntimeException
         */
        protected function validate(array $data, array $rules): void
        {
            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                throw new \Sajya\Server\Exceptions\RuntimeException(
                    'Invalid parameters',
                    -32602,
                    $validator->errors()->toArray()
                );
            }
        }

        /**
         * Get correlation ID from request
         */
        protected function getCorrelationId(): string
        {
            return request()->header('X-Correlation-ID', uniqid('rpc_', true));
        }

        /**
         * Log RPC performance metrics
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
                'success' => ! is_null($result),
                'timestamp' => now()->toISOString(),
            ];

            Log::channel('performance')->info('RPC_PERFORMANCE', $metrics);
        }

        /**
         * Handle RPC exceptions with proper error codes
         *
         * @throws RuntimeException
         */
        protected function handleException(\Exception $e, string $context = ''): void
        {
            $errorCode = match (get_class($e)) {
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
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Sajya\Server\Exceptions\RuntimeException(
                $context ? "$context: {$e->getMessage()}" : $e->getMessage(),
                $errorCode,
                ['context' => $context, 'original_error' => $e->getMessage()]
            );
        }

        /**
         * Execute procedure with performance logging
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
} else {
    // Stub implementation when Sajya is not available (for CI/development)
    abstract class BaseProcedure
    {
        /**
         * Stub validate method
         */
        protected function validate(array $data, array $rules): void
        {
            // Stub implementation - validation disabled when Sajya not available
        }

        /**
         * Stub correlation ID method
         */
        protected function getCorrelationId(): string
        {
            return uniqid('rpc_', true);
        }

        /**
         * Stub performance logging method
         */
        protected function logPerformance(string $method, array $params, mixed $result, float $startTime): void
        {
            // Stub implementation - logging disabled when Sajya not available
        }

        /**
         * Stub exception handling method
         */
        protected function handleException(\Exception $e, string $context = ''): void
        {
            throw $e; // Re-throw as-is when Sajya not available
        }

        /**
         * Stub execution with logging method
         */
        protected function executeWithLogging(string $method, array $params, callable $callback): mixed
        {
            return $callback(); // Execute without logging when Sajya not available
        }

        /**
         * Stub service info method
         */
        protected function getServiceInfo(): array
        {
            return [
                'service' => config('app.name', 'unknown'),
                'rpc_enabled' => false,
                'note' => 'RPC functionality disabled - Sajya packages not available',
            ];
        }

        /**
         * Stub sanitize method
         */
        protected function sanitizeForLogging(array $data): array
        {
            return $data; // No sanitization when Sajya not available
        }
    }
}
