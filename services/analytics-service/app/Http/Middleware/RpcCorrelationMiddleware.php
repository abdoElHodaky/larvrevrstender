<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * RPC Correlation Middleware (Laravel 12 + PHP 8.3)
 * 
 * Handles correlation ID propagation for distributed tracing
 */
class RpcCorrelationMiddleware
{
    private const CORRELATION_HEADER = 'X-Correlation-ID';
    private const FALLBACK_HEADERS = ['x-correlation-id', 'correlation-id'];

    /**
     * Handle an incoming request with correlation ID management
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->extractOrGenerateCorrelationId($request);
        
        // Set correlation ID in request context
        $request->headers->set(self::CORRELATION_HEADER, $correlationId);
        
        // Add to logging context for this request
        Log::withContext(['correlation_id' => $correlationId]);

        $response = $next($request);

        // Ensure correlation ID is in response headers
        return $this->addCorrelationToResponse($response, $correlationId);
    }

    /**
     * Extract correlation ID from request or generate new one
     */
    private function extractOrGenerateCorrelationId(Request $request): string
    {
        // Try primary header first
        $correlationId = $request->header(self::CORRELATION_HEADER);
        
        if ($correlationId) {
            return $correlationId;
        }

        // Try fallback headers using match expression (PHP 8.3)
        foreach (self::FALLBACK_HEADERS as $header) {
            $value = $request->header($header);
            if ($value) {
                return $value;
            }
        }

        // Generate new correlation ID with service prefix
        return 'analytics-' . Str::uuid()->toString();
    }

    /**
     * Add correlation ID to response headers
     */
    private function addCorrelationToResponse(mixed $response, string $correlationId): Response
    {
        if (method_exists($response, 'header')) {
            $response->header(self::CORRELATION_HEADER, $correlationId);
            $response->header('X-Service-Name', 'analytics-service');
        }

        return $response;
    }
}
