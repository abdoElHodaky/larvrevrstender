<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RpcPerformanceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = $endMemory - $startMemory;
        
        // Log performance metrics
        Log::info('RPC Performance Metrics', [
            'correlation_id' => $request->header('X-Correlation-ID'),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_bytes' => $memoryUsage,
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
        
        // Add performance headers to response
        if (method_exists($response, 'header')) {
            $response->header('X-Execution-Time', round($executionTime, 2) . 'ms');
            $response->header('X-Memory-Usage', round($memoryUsage / 1024, 2) . 'KB');
        }
        
        // Store metrics in Octane table if available
        if (function_exists('swoole_table_get')) {
            try {
                $table = app('octane.table.rpc_metrics');
                if ($table) {
                    $table->set(uniqid('rpc_'), [
                        'method' => $request->getRequestUri(),
                        'response_time' => $executionTime,
                        'memory_usage' => $memoryUsage,
                        'timestamp' => time(),
                    ]);
                }
            } catch (\Exception $e) {
                // Silently handle table errors
            }
        }
        
        return $response;
    }
}
