<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RpcCorrelationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Generate or extract correlation ID
        $correlationId = $request->header('X-Correlation-ID')
            ?? $request->header('x-correlation-id')
            ?? Str::uuid()->toString();

        // Add correlation ID to request headers
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Add to response headers
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Correlation-ID', $correlationId);
        }

        return $response;
    }
}
