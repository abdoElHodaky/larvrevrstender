<?php

namespace App\Http\Middleware;

use App\Services\CorrelationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to handle correlation ID extraction and injection for distributed tracing
 */
class CorrelationMiddleware
{
    protected CorrelationService $correlationService;
    protected float $startTime;

    public function __construct(CorrelationService $correlationService)
    {
        $this->correlationService = $correlationService;
        $this->startTime = microtime(true);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Extract correlation context from request headers
        $extractedCorrelation = $this->correlationService->extractCorrelationFromRequest($request);
        
        if ($extractedCorrelation) {
            // Continue existing correlation
            $correlationId = $extractedCorrelation['correlation_id'];
            
            Log::info('Continuing existing correlation', [
                'correlation_id' => $correlationId,
                'trace_id' => $extractedCorrelation['trace_id'],
                'source_service' => $extractedCorrelation['service_name'],
                'endpoint' => $request->getPathInfo(),
                'method' => $request->getMethod(),
            ]);
            
            // Store extracted correlation in app context
            app()->instance('correlation.context', $extractedCorrelation);
        } else {
            // Start new correlation
            $correlationId = $this->correlationService->generateCorrelationId();
            
            $correlationData = $this->correlationService->startCorrelation(
                $correlationId,
                'order-service',
                $this->getOperationName($request),
                [
                    'endpoint' => $request->getPathInfo(),
                    'method' => $request->getMethod(),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                ]
            );
            
            Log::info('Started new correlation', [
                'correlation_id' => $correlationId,
                'trace_id' => $correlationData['trace_id'],
                'endpoint' => $request->getPathInfo(),
                'method' => $request->getMethod(),
            ]);
        }

        // Add correlation ID to request for use in controllers
        $request->attributes->set('correlation_id', $correlationId);

        // Process the request
        $response = $next($request);

        // Add correlation headers to response
        $this->addCorrelationHeadersToResponse($response, $correlationId);

        // Complete correlation if it was started in this request
        if (!$extractedCorrelation) {
            $this->correlationService->completeCorrelation(
                $correlationId,
                $response->getStatusCode() < 400,
                [
                    'status_code' => $response->getStatusCode(),
                    'response_size' => strlen($response->getContent()),
                ],
                $response->getStatusCode() >= 400 ? 'HTTP Error: ' . $response->getStatusCode() : null
            );
        }

        return $response;
    }

    /**
     * Get operation name from request
     */
    private function getOperationName(Request $request): string
    {
        $route = $request->route();
        
        if ($route && $route->getName()) {
            return $route->getName();
        }
        
        if ($route && $route->getActionName()) {
            $action = $route->getActionName();
            if (str_contains($action, '@')) {
                return substr($action, strrpos($action, '@') + 1);
            }
            return $action;
        }
        
        return $request->getMethod() . ' ' . $request->getPathInfo();
    }

    /**
     * Add correlation headers to response
     */
    private function addCorrelationHeadersToResponse(\Symfony\Component\HttpFoundation\Response $response, string $correlationId): void
    {
        $correlationData = $this->correlationService->getCorrelationHeaders($correlationId);
        
        foreach ($correlationData as $header => $value) {
            $response->headers->set($header, $value);
        }
        
        // Add service identification
        $response->headers->set('X-Service-Name', 'order-service');
        $response->headers->set('X-Service-Version', config('app.version', '1.0.0'));
        
        // Calculate response time (handle case where LARAVEL_START is not defined, e.g., during testing)
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $this->startTime;
        $response->headers->set('X-Response-Time', microtime(true) - $startTime);
    }
}
