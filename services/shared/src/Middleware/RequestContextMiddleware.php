<?php

namespace Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shared\Facades\SharedLog;
use Ramsey\Uuid\Uuid;

/**
 * Request Context Middleware
 * 
 * Handles request context propagation across microservices for logging correlation.
 * Generates or extracts request IDs, adds service identification, and ensures
 * proper context tracking for cross-service requests.
 */
class RequestContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Extract or generate request ID
        $requestId = $this->extractOrGenerateRequestId($request);
        
        // Get service name
        $serviceName = $this->getServiceName();
        
        // Add request ID to request headers for downstream services
        $request->headers->set('X-Request-ID', $requestId);
        $request->headers->set('X-Service-Name', $serviceName);
        
        // Set up logging context
        SharedLog::addContext([
            'request_id' => $requestId,
            'service_name' => $serviceName,
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        // Log request start
        SharedLog::requestCorrelation('request_started', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        $startTime = microtime(true);
        
        try {
            $response = $next($request);
            
            // Calculate request duration
            $duration = microtime(true) - $startTime;
            
            // Add response context
            $responseContext = [
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round($duration * 1000, 2),
            ];
            
            // Log request completion
            SharedLog::requestCorrelation('request_completed', $responseContext);
            
            // Log performance if request took longer than threshold
            $performanceThreshold = config('logging.correlation.performance_threshold', 1.0); // 1 second
            if ($duration > $performanceThreshold) {
                SharedLog::performance('http_request', $duration, [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'status_code' => $response->getStatusCode(),
                ]);
            }
            
            // Add correlation headers to response
            $this->addCorrelationHeaders($response, $requestId, $serviceName);
            
            return $response;
            
        } catch (\Throwable $exception) {
            // Calculate request duration even for exceptions
            $duration = microtime(true) - $startTime;
            
            // Log exception with correlation context
            SharedLog::exception($exception, [
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
                'duration_ms' => round($duration * 1000, 2),
            ]);
            
            // Log request failure
            SharedLog::requestCorrelation('request_failed', [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
            ]);
            
            throw $exception;
        }
    }
    
    /**
     * Extract request ID from headers or generate a new one.
     */
    private function extractOrGenerateRequestId(Request $request): string
    {
        // Check for existing request ID in headers
        $requestId = $request->header('X-Request-ID') 
            ?? $request->header('X-Correlation-ID')
            ?? $request->header('X-Trace-ID');
        
        if ($requestId) {
            return $requestId;
        }
        
        // Generate new request ID
        return Uuid::uuid4()->toString();
    }
    
    /**
     * Get the current service name.
     */
    private function getServiceName(): string
    {
        return config('app.service_name') 
            ?? env('SERVICE_NAME') 
            ?? env('APP_NAME', 'unknown-service');
    }
    
    /**
     * Add correlation headers to the response.
     */
    private function addCorrelationHeaders($response, string $requestId, string $serviceName): void
    {
        if ($response instanceof Response) {
            $response->headers->set('X-Request-ID', $requestId);
            $response->headers->set('X-Service-Name', $serviceName);
            $response->headers->set('X-Response-Time', microtime(true));
        }
    }
}
