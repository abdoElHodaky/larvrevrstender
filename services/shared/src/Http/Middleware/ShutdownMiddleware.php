<?php

namespace Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShutdownMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if graceful shutdown is in progress
        $shutdownInProgress = Cache::get('shutdown_in_progress', false);
        
        if ($shutdownInProgress) {
            // Log the rejected request
            Log::info('Request rejected due to graceful shutdown', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
            ]);
            
            // Return 503 Service Unavailable with appropriate headers
            return response()->json([
                'error' => 'Service Unavailable',
                'message' => 'Service is gracefully shutting down. Please try again in a moment.',
                'status' => 503,
                'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
                'shutdown_started_at' => Cache::get('shutdown_started_at'),
                'retry_after' => 30, // seconds
            ], 503, [
                'Retry-After' => '30',
                'Connection' => 'close',
                'X-Shutdown-Status' => 'in-progress',
                'X-Environment-Color' => env('ENVIRONMENT_COLOR', 'unknown'),
            ]);
        }
        
        // Track active requests for graceful shutdown
        $this->incrementActiveRequests();
        
        try {
            $response = $next($request);
            
            // Add environment color header to response
            $response->headers->set('X-Environment-Color', env('ENVIRONMENT_COLOR', 'unknown'));
            
            return $response;
            
        } finally {
            // Always decrement active requests count
            $this->decrementActiveRequests();
        }
    }
    
    /**
     * Increment the active requests counter
     */
    private function incrementActiveRequests(): void
    {
        try {
            $current = Cache::get('octane:active_requests', 0);
            Cache::put('octane:active_requests', $current + 1, 300);
            
            // Update metrics for monitoring
            $metrics = Cache::get('octane:metrics', []);
            $metrics['active_requests'] = $current + 1;
            $metrics['total_requests'] = ($metrics['total_requests'] ?? 0) + 1;
            $metrics['last_request_at'] = now()->toISOString();
            Cache::put('octane:metrics', $metrics, 300);
            
        } catch (\Exception $e) {
            Log::warning('Failed to increment active requests counter: ' . $e->getMessage());
        }
    }
    
    /**
     * Decrement the active requests counter
     */
    private function decrementActiveRequests(): void
    {
        try {
            $current = Cache::get('octane:active_requests', 0);
            $newCount = max(0, $current - 1);
            Cache::put('octane:active_requests', $newCount, 300);
            
            // Update metrics
            $metrics = Cache::get('octane:metrics', []);
            $metrics['active_requests'] = $newCount;
            Cache::put('octane:metrics', $metrics, 300);
            
        } catch (\Exception $e) {
            Log::warning('Failed to decrement active requests counter: ' . $e->getMessage());
        }
    }
}
