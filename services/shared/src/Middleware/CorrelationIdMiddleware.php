<?php

declare(strict_types=1);

namespace Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Correlation ID Middleware - PHP 8.3 & Laravel 12 Implementation
 * 
 * Ensures every request has a correlation ID for distributed tracing
 * across microservices. Generates new ID if not present.
 */
class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get or generate correlation ID
        $correlationId = $request->header('X-Correlation-ID') 
            ?? $this->generateCorrelationId();

        // Add to request headers for downstream services
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Process request
        $response = $next($request);

        // Add correlation ID to response headers
        if ($response instanceof Response) {
            $response->headers->set('X-Correlation-ID', $correlationId);
        }

        return $response;
    }

    /**
     * Generate a unique correlation ID
     */
    private function generateCorrelationId(): string
    {
        return sprintf(
            '%s-%s-%s',
            config('app.name', 'service'),
            Str::random(8),
            substr(md5(microtime()), 0, 8)
        );
    }
}
