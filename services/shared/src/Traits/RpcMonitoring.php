<?php

namespace Shared\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RPC Monitoring Trait
 * 
 * Provides performance monitoring, distributed tracing, and metrics collection
 * for RPC procedures without external dependencies.
 */
trait RpcMonitoring
{
    private array $performanceMetrics = [];
    private ?string $currentTraceId = null;
    private array $traceSpans = [];

    /**
     * Start performance monitoring for an RPC method
     */
    protected function startMonitoring(string $method, array $params = []): string
    {
        $traceId = $this->generateTraceId();
        $this->currentTraceId = $traceId;
        
        $this->performanceMetrics[$traceId] = [
            'method' => $method,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'params_size' => strlen(json_encode($params)),
            'correlation_id' => $this->getCorrelationId(),
            'spans' => []
        ];

        Log::info('RPC method started', [
            'trace_id' => $traceId,
            'method' => $method,
            'params_size' => $this->performanceMetrics[$traceId]['params_size'],
            'correlation_id' => $this->getCorrelationId()
        ]);

        return $traceId;
    }

    /**
     * End performance monitoring and log metrics
     */
    protected function endMonitoring(string $traceId, array $result = [], ?string $error = null): array
    {
        if (!isset($this->performanceMetrics[$traceId])) {
            return [];
        }

        $metrics = $this->performanceMetrics[$traceId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $performanceData = [
            'trace_id' => $traceId,
            'method' => $metrics['method'],
            'execution_time_ms' => round(($endTime - $metrics['start_time']) * 1000, 2),
            'memory_usage_mb' => round(($endMemory - $metrics['start_memory']) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'params_size_bytes' => $metrics['params_size'],
            'result_size_bytes' => strlen(json_encode($result)),
            'correlation_id' => $metrics['correlation_id'],
            'spans_count' => count($metrics['spans']),
            'status' => $error ? 'error' : 'success',
            'error' => $error
        ];

        // Log performance metrics
        Log::info('RPC method completed', $performanceData);

        // Log detailed spans if any
        if (!empty($metrics['spans'])) {
            Log::debug('RPC method spans', [
                'trace_id' => $traceId,
                'spans' => $metrics['spans']
            ]);
        }

        // Clean up
        unset($this->performanceMetrics[$traceId]);

        return $performanceData;
    }

    /**
     * Start a span within the current trace
     */
    protected function startSpan(string $spanName, array $attributes = []): string
    {
        if (!$this->currentTraceId || !isset($this->performanceMetrics[$this->currentTraceId])) {
            return '';
        }

        $spanId = $this->generateSpanId();
        $span = [
            'span_id' => $spanId,
            'span_name' => $spanName,
            'start_time' => microtime(true),
            'attributes' => $attributes
        ];

        $this->performanceMetrics[$this->currentTraceId]['spans'][$spanId] = $span;
        $this->traceSpans[] = $spanId;

        return $spanId;
    }

    /**
     * End a span
     */
    protected function endSpan(string $spanId, array $attributes = []): void
    {
        if (!$this->currentTraceId || !isset($this->performanceMetrics[$this->currentTraceId]['spans'][$spanId])) {
            return;
        }

        $span = &$this->performanceMetrics[$this->currentTraceId]['spans'][$spanId];
        $span['end_time'] = microtime(true);
        $span['duration_ms'] = round(($span['end_time'] - $span['start_time']) * 1000, 2);
        $span['attributes'] = array_merge($span['attributes'], $attributes);

        // Remove from active spans
        $this->traceSpans = array_filter($this->traceSpans, fn($id) => $id !== $spanId);
    }

    /**
     * Add custom metrics to current trace
     */
    protected function addMetric(string $name, $value, array $tags = []): void
    {
        if (!$this->currentTraceId) {
            return;
        }

        Log::info('RPC custom metric', [
            'trace_id' => $this->currentTraceId,
            'metric_name' => $name,
            'metric_value' => $value,
            'tags' => $tags,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Record RPC call metrics (for cross-service calls)
     */
    protected function recordRpcCall(string $serviceName, string $method, float $duration, bool $success, ?string $error = null): void
    {
        $metrics = [
            'type' => 'rpc_call',
            'service' => $serviceName,
            'method' => $method,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'error' => $error,
            'timestamp' => now()->toISOString(),
            'correlation_id' => $this->getCorrelationId()
        ];

        if ($this->currentTraceId) {
            $metrics['trace_id'] = $this->currentTraceId;
        }

        Log::info('RPC call metrics', $metrics);
    }

    /**
     * Record database query metrics
     */
    protected function recordDbQuery(string $query, float $duration, int $affectedRows = 0): void
    {
        $spanId = $this->startSpan('db_query', [
            'query_type' => $this->getQueryType($query),
            'affected_rows' => $affectedRows
        ]);

        $this->endSpan($spanId, [
            'duration_ms' => round($duration * 1000, 2)
        ]);
    }

    /**
     * Record cache operation metrics
     */
    protected function recordCacheOperation(string $operation, string $key, bool $hit = null, float $duration = null): void
    {
        $attributes = [
            'operation' => $operation,
            'cache_key' => $key
        ];

        if ($hit !== null) {
            $attributes['cache_hit'] = $hit;
        }

        if ($duration !== null) {
            $attributes['duration_ms'] = round($duration * 1000, 2);
        }

        $spanId = $this->startSpan('cache_operation', $attributes);
        $this->endSpan($spanId);
    }

    /**
     * Get current performance metrics
     */
    protected function getCurrentMetrics(): array
    {
        if (!$this->currentTraceId || !isset($this->performanceMetrics[$this->currentTraceId])) {
            return [];
        }

        $metrics = $this->performanceMetrics[$this->currentTraceId];
        $currentTime = microtime(true);
        
        return [
            'trace_id' => $this->currentTraceId,
            'method' => $metrics['method'],
            'elapsed_time_ms' => round(($currentTime - $metrics['start_time']) * 1000, 2),
            'memory_usage_mb' => round((memory_get_usage(true) - $metrics['start_memory']) / 1024 / 1024, 2),
            'active_spans' => count($this->traceSpans),
            'correlation_id' => $metrics['correlation_id']
        ];
    }

    /**
     * Generate trace ID
     */
    private function generateTraceId(): string
    {
        return 'trace_' . Str::random(16) . '_' . time();
    }

    /**
     * Generate span ID
     */
    private function generateSpanId(): string
    {
        return 'span_' . Str::random(12);
    }

    /**
     * Get correlation ID (should be implemented by the using class)
     */
    protected function getCorrelationId(): string
    {
        return method_exists($this, 'generateCorrelationId') 
            ? $this->generateCorrelationId() 
            : 'corr_' . Str::random(8);
    }

    /**
     * Get query type from SQL
     */
    private function getQueryType(string $query): string
    {
        $query = trim(strtoupper($query));
        
        if (str_starts_with($query, 'SELECT')) return 'SELECT';
        if (str_starts_with($query, 'INSERT')) return 'INSERT';
        if (str_starts_with($query, 'UPDATE')) return 'UPDATE';
        if (str_starts_with($query, 'DELETE')) return 'DELETE';
        if (str_starts_with($query, 'CREATE')) return 'CREATE';
        if (str_starts_with($query, 'ALTER')) return 'ALTER';
        if (str_starts_with($query, 'DROP')) return 'DROP';
        
        return 'OTHER';
    }

    /**
     * Get aggregated metrics for reporting
     */
    public static function getAggregatedMetrics(int $hours = 1): array
    {
        // This would typically query a metrics storage system
        // For now, return empty array as this is a basic implementation
        return [
            'period_hours' => $hours,
            'total_requests' => 0,
            'average_response_time_ms' => 0,
            'error_rate_percent' => 0,
            'throughput_per_minute' => 0
        ];
    }

    /**
     * Health check for monitoring system
     */
    public function monitoringHealthCheck(): array
    {
        return [
            'monitoring_enabled' => true,
            'active_traces' => count($this->performanceMetrics),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp' => now()->toISOString()
        ];
    }
}
