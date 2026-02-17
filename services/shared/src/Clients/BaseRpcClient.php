<?php

namespace Shared\Clients;

use Shared\Procedures\CrossServiceProcedure;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Base RPC Client for Cross-Service Communication
 * 
 * Provides a foundation for RPC-based service clients with built-in
 * circuit breaker protection, distributed tracing, and error handling.
 */
abstract class BaseRpcClient
{
    protected CrossServiceProcedure $rpcProcedure;
    protected string $serviceName;
    protected array $config;
    
    public function __construct(string $serviceName, array $config = [])
    {
        $this->serviceName = $serviceName;
        $this->rpcProcedure = new CrossServiceProcedure();
        $this->config = array_merge([
            'timeout' => 30,
            'retries' => 3,
            'retry_delay' => 1000, // milliseconds
            'circuit_breaker' => true,
            'trace_requests' => true,
            'enable_batch' => true,
            'max_batch_size' => 10,
        ], $config);
    }
    
    /**
     * Make RPC call to target service
     *
     * @param string $method RPC method name (e.g., 'auction.initialize')
     * @param array $params Parameters to send
     * @param array $context Additional context for the call
     * @return array RPC response
     */
    protected function call(string $method, array $params = [], array $context = []): array
    {
        $startTime = microtime(true);
        $traceId = $context['trace_id'] ?? $this->generateTraceId();
        
        try {
            // Prepare RPC context with tracing information
            $rpcContext = array_merge($context, [
                'trace_id' => $traceId,
                'caller_service' => config('app.name'),
                'target_service' => $this->serviceName,
                'method' => $method,
                'timestamp' => now()->toISOString(),
                'client_class' => static::class,
            ]);
            
            // Make RPC call through CrossServiceProcedure
            $result = $this->rpcProcedure->callService(
                $this->serviceName,
                $method,
                $params,
                $rpcContext
            );
            
            $duration = microtime(true) - $startTime;
            $this->logRpcCall($method, $params, $result, $duration, $traceId);
            
            return $result;
            
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logRpcError($method, $params, $e, $duration, $traceId);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace_id' => $traceId,
                'service' => $this->serviceName,
                'method' => $method,
                'duration_ms' => round($duration * 1000, 2),
            ];
        }
    }
    
    /**
     * Make batch RPC calls
     *
     * @param array $calls Array of calls with 'method' and 'params' keys
     * @param array $context Additional context for the batch
     * @return array Array of RPC responses
     */
    protected function batchCall(array $calls, array $context = []): array
    {
        if (!$this->config['enable_batch']) {
            // Fall back to individual calls if batch is disabled
            return $this->fallbackToIndividualCalls($calls, $context);
        }
        
        if (count($calls) > $this->config['max_batch_size']) {
            // Split into smaller batches if too large
            return $this->splitBatchCalls($calls, $context);
        }
        
        $batchTraceId = $context['trace_id'] ?? $this->generateTraceId();
        $startTime = microtime(true);
        
        try {
            $results = [];
            
            foreach ($calls as $index => $call) {
                $callContext = array_merge($context, [
                    'batch_trace_id' => $batchTraceId,
                    'batch_index' => $index,
                    'trace_id' => $this->generateTraceId(),
                ]);
                
                $results[] = $this->call(
                    $call['method'],
                    $call['params'] ?? [],
                    $callContext
                );
            }
            
            $duration = microtime(true) - $startTime;
            $this->logBatchCall($calls, $results, $duration, $batchTraceId);
            
            return $results;
            
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logBatchError($calls, $e, $duration, $batchTraceId);
            
            return array_fill(0, count($calls), [
                'success' => false,
                'error' => $e->getMessage(),
                'batch_trace_id' => $batchTraceId,
            ]);
        }
    }
    
    /**
     * Health check via RPC
     *
     * @return array Health check response
     */
    public function healthCheck(): array
    {
        return $this->call('health.check', [], [
            'timeout' => 5, // Shorter timeout for health checks
        ]);
    }
    
    /**
     * Get service info via RPC
     *
     * @return array Service information
     */
    public function getServiceInfo(): array
    {
        return $this->call('system.info', [], [
            'timeout' => 10,
        ]);
    }
    
    /**
     * Get service metrics via RPC
     *
     * @return array Service metrics
     */
    public function getServiceMetrics(): array
    {
        return $this->call('system.metrics', [], [
            'timeout' => 10,
        ]);
    }
    
    /**
     * Ping the service for connectivity test
     *
     * @return array Ping response with latency
     */
    public function ping(): array
    {
        $startTime = microtime(true);
        $result = $this->call('system.ping', [], [
            'timeout' => 5,
        ]);
        $latency = (microtime(true) - $startTime) * 1000;
        
        if ($result['success'] ?? false) {
            $result['latency_ms'] = round($latency, 2);
        }
        
        return $result;
    }
    
    /**
     * Get client configuration
     *
     * @return array Current configuration
     */
    public function getConfig(): array
    {
        return [
            'service_name' => $this->serviceName,
            'config' => $this->config,
            'client_class' => static::class,
        ];
    }
    
    /**
     * Update client configuration
     *
     * @param array $config Configuration updates
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
    
    /**
     * Fall back to individual calls when batch is not available
     *
     * @param array $calls
     * @param array $context
     * @return array
     */
    private function fallbackToIndividualCalls(array $calls, array $context): array
    {
        $results = [];
        
        foreach ($calls as $call) {
            $results[] = $this->call(
                $call['method'],
                $call['params'] ?? [],
                $context
            );
        }
        
        return $results;
    }
    
    /**
     * Split large batch into smaller batches
     *
     * @param array $calls
     * @param array $context
     * @return array
     */
    private function splitBatchCalls(array $calls, array $context): array
    {
        $results = [];
        $chunks = array_chunk($calls, $this->config['max_batch_size']);
        
        foreach ($chunks as $chunk) {
            $chunkResults = $this->batchCall($chunk, $context);
            $results = array_merge($results, $chunkResults);
        }
        
        return $results;
    }
    
    /**
     * Log successful RPC call
     *
     * @param string $method
     * @param array $params
     * @param array $result
     * @param float $duration
     * @param string $traceId
     * @return void
     */
    private function logRpcCall(string $method, array $params, array $result, float $duration, string $traceId): void
    {
        if ($this->config['trace_requests']) {
            Log::info('RPC call completed', [
                'client' => static::class,
                'service' => $this->serviceName,
                'method' => $method,
                'success' => $result['success'] ?? false,
                'duration_ms' => round($duration * 1000, 2),
                'trace_id' => $traceId,
                'params_count' => count($params),
                'response_size' => strlen(json_encode($result)),
            ]);
        }
    }
    
    /**
     * Log RPC call error
     *
     * @param string $method
     * @param array $params
     * @param Exception $e
     * @param float $duration
     * @param string $traceId
     * @return void
     */
    private function logRpcError(string $method, array $params, Exception $e, float $duration, string $traceId): void
    {
        Log::error('RPC call failed', [
            'client' => static::class,
            'service' => $this->serviceName,
            'method' => $method,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'duration_ms' => round($duration * 1000, 2),
            'trace_id' => $traceId,
            'params_count' => count($params),
            'stack_trace' => $e->getTraceAsString(),
        ]);
    }
    
    /**
     * Log successful batch call
     *
     * @param array $calls
     * @param array $results
     * @param float $duration
     * @param string $batchTraceId
     * @return void
     */
    private function logBatchCall(array $calls, array $results, float $duration, string $batchTraceId): void
    {
        if ($this->config['trace_requests']) {
            $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
            
            Log::info('RPC batch call completed', [
                'client' => static::class,
                'service' => $this->serviceName,
                'batch_size' => count($calls),
                'success_count' => $successCount,
                'failure_count' => count($calls) - $successCount,
                'duration_ms' => round($duration * 1000, 2),
                'batch_trace_id' => $batchTraceId,
            ]);
        }
    }
    
    /**
     * Log batch call error
     *
     * @param array $calls
     * @param Exception $e
     * @param float $duration
     * @param string $batchTraceId
     * @return void
     */
    private function logBatchError(array $calls, Exception $e, float $duration, string $batchTraceId): void
    {
        Log::error('RPC batch call failed', [
            'client' => static::class,
            'service' => $this->serviceName,
            'batch_size' => count($calls),
            'error' => $e->getMessage(),
            'duration_ms' => round($duration * 1000, 2),
            'batch_trace_id' => $batchTraceId,
        ]);
    }
    
    /**
     * Generate unique trace ID for request correlation
     *
     * @return string
     */
    private function generateTraceId(): string
    {
        return 'rpc_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
}

