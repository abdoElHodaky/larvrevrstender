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
         *
         * @param  mixed  $result
         */
        protected function logPerformance(string $method, array $params, $result, float $startTime): void
        {
            $duration = microtime(true) - $startTime;

            Log::info('RPC Performance', [
                'correlation_id' => $this->getCorrelationId(),
                'method' => $method,
                'duration_ms' => round($duration * 1000, 2),
                'params_count' => count($params),
                'success' => $result !== null,
            ]);
        }

        /**
         * Handle RPC errors consistently
         *
         * @throws RuntimeException
         */
        protected function handleError(\Exception $e, string $method, array $params): void
        {
            Log::error('RPC Error', [
                'correlation_id' => $this->getCorrelationId(),
                'method' => $method,
                'error' => $e->getMessage(),
                'params' => $params,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Sajya\Server\Exceptions\RuntimeException(
                $e->getMessage(),
                -32603,
                ['method' => $method, 'original_error' => $e->getMessage()]
            );
        }
    }
} else {
    // Fallback implementation when Sajya is not available
    abstract class BaseProcedure
    {
        protected function validate(array $data, array $rules): void
        {
            // Fallback validation
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                throw new \RuntimeException('Validation failed: '.json_encode($validator->errors()));
            }
        }

        protected function getCorrelationId(): string
        {
            return uniqid('rpc_fallback_', true);
        }

        protected function logPerformance(string $method, array $params, $result, float $startTime): void
        {
            Log::info("RPC Fallback: {$method} completed");
        }

        protected function handleError(\Exception $e, string $method, array $params): void
        {
            Log::error("RPC Fallback Error in {$method}: ".$e->getMessage());
            throw $e;
        }
    }
}
