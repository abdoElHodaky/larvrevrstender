<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RpcLoggingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $correlationId = $request->header('X-Correlation-ID');
        $startTime = microtime(true);

        // Log incoming request
        Log::info('RPC Request', [
            'correlation_id' => $correlationId,
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'payload_size' => strlen($request->getContent()),
            'timestamp' => now()->toISOString(),
        ]);

        try {
            $response = $next($request);

            $executionTime = (microtime(true) - $startTime) * 1000;

            // Log successful response
            Log::info('RPC Response', [
                'correlation_id' => $correlationId,
                'status_code' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200,
                'execution_time_ms' => round($executionTime, 2),
                'response_size' => method_exists($response, 'getContent') ? strlen($response->getContent()) : 0,
                'timestamp' => now()->toISOString(),
            ]);

            return $response;

        } catch (\Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Log error response
            Log::error('RPC Error', [
                'correlation_id' => $correlationId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'execution_time_ms' => round($executionTime, 2),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toISOString(),
            ]);

            throw $e;
        }
    }
}
