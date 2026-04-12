<?php

declare(strict_types=1);

namespace Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Custom CORS Middleware for Laravel 12 & PHP 8.3
 * 
 * Extends Laravel's built-in CORS functionality with:
 * - Service-specific CORS policies
 * - Dynamic origin validation
 * - Enhanced security headers
 * - Microservices-aware configuration
 * - Development vs Production policies
 */
class CustomCorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $policy = null): Response
    {
        // Handle preflight OPTIONS requests
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request, $policy);
        }

        $response = $next($request);

        // Add CORS headers to actual requests
        return $this->addCorsHeaders($request, $response, $policy);
    }

    /**
     * Handle preflight OPTIONS requests
     */
    private function handlePreflightRequest(Request $request, ?string $policy): Response
    {
        $corsConfig = $this->getCorsConfig($policy);
        $origin = $request->header('Origin');

        // Validate origin
        if (!$this->isOriginAllowed($origin, $corsConfig)) {
            return new Response('', 403, [
                'Content-Type' => 'text/plain',
            ]);
        }

        $headers = [
            'Access-Control-Allow-Origin' => $this->getAllowedOrigin($origin, $corsConfig),
            'Access-Control-Allow-Methods' => implode(', ', $corsConfig['allowed_methods']),
            'Access-Control-Allow-Headers' => implode(', ', $corsConfig['allowed_headers']),
            'Access-Control-Max-Age' => (string) $corsConfig['max_age'],
            'Vary' => 'Origin',
        ];

        // Add credentials header if allowed
        if ($corsConfig['supports_credentials']) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        // Add exposed headers
        if (!empty($corsConfig['exposed_headers'])) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $corsConfig['exposed_headers']);
        }

        return new Response('', 200, $headers);
    }

    /**
     * Add CORS headers to actual requests
     */
    private function addCorsHeaders(Request $request, Response $response, ?string $policy): Response
    {
        $corsConfig = $this->getCorsConfig($policy);
        $origin = $request->header('Origin');

        // Only add headers if origin is allowed
        if (!$this->isOriginAllowed($origin, $corsConfig)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $this->getAllowedOrigin($origin, $corsConfig));
        $response->headers->set('Vary', 'Origin');

        // Add credentials header if allowed
        if ($corsConfig['supports_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // Add exposed headers
        if (!empty($corsConfig['exposed_headers'])) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $corsConfig['exposed_headers']));
        }

        // Add security headers for enhanced protection
        $this->addSecurityHeaders($response, $corsConfig);

        return $response;
    }

    /**
     * Get CORS configuration for the specified policy
     */
    private function getCorsConfig(?string $policy): array
    {
        $defaultConfig = [
            'allowed_origins' => $this->getDefaultAllowedOrigins(),
            'allowed_origins_patterns' => [],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'X-Service-Auth',
                'X-Service-Token',
                'X-Correlation-ID',
                'X-Request-ID',
            ],
            'exposed_headers' => [
                'X-RateLimit-Limit',
                'X-RateLimit-Remaining',
                'X-RateLimit-Reset',
                'X-Correlation-ID',
                'X-Request-ID',
            ],
            'max_age' => 86400, // 24 hours
            'supports_credentials' => true,
        ];

        // Get service-specific configuration
        $serviceConfig = config('cors.policies.' . ($policy ?: 'default'), []);
        
        return array_merge($defaultConfig, $serviceConfig);
    }

    /**
     * Get default allowed origins based on environment
     */
    private function getDefaultAllowedOrigins(): array
    {
        $environment = app()->environment();

        return match ($environment) {
            'local', 'development' => [
                'http://localhost:3000',
                'http://localhost:3001',
                'http://localhost:8080',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:3001',
                'http://127.0.0.1:8080',
            ],
            'staging' => [
                'https://staging.reversetender.com',
                'https://admin-staging.reversetender.com',
                'https://api-staging.reversetender.com',
            ],
            'production' => [
                'https://reversetender.com',
                'https://www.reversetender.com',
                'https://admin.reversetender.com',
                'https://api.reversetender.com',
            ],
            default => []
        };
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(?string $origin, array $corsConfig): bool
    {
        if (!$origin) {
            return false;
        }

        // Check exact matches
        if (in_array($origin, $corsConfig['allowed_origins'], true)) {
            return true;
        }

        // Check wildcard (allow all)
        if (in_array('*', $corsConfig['allowed_origins'], true)) {
            return true;
        }

        // Check pattern matches
        foreach ($corsConfig['allowed_origins_patterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the allowed origin header value
     */
    private function getAllowedOrigin(?string $origin, array $corsConfig): string
    {
        // If wildcard is allowed and credentials are not supported, return *
        if (in_array('*', $corsConfig['allowed_origins'], true) && !$corsConfig['supports_credentials']) {
            return '*';
        }

        // Otherwise return the specific origin
        return $origin ?: '';
    }

    /**
     * Add additional security headers
     */
    private function addSecurityHeaders(Response $response, array $corsConfig): void
    {
        // Content Security Policy for enhanced security
        if (!$response->headers->has('Content-Security-Policy')) {
            $csp = $this->buildContentSecurityPolicy($corsConfig);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // X-Frame-Options to prevent clickjacking
        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // X-Content-Type-Options to prevent MIME sniffing
        if (!$response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        // Referrer Policy
        if (!$response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // Permissions Policy (formerly Feature Policy)
        if (!$response->headers->has('Permissions-Policy')) {
            $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        }
    }

    /**
     * Build Content Security Policy header
     */
    private function buildContentSecurityPolicy(array $corsConfig): string
    {
        $environment = app()->environment();
        
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
        ];

        // Add allowed origins to connect-src for API calls
        $allowedOrigins = array_filter($corsConfig['allowed_origins'], fn($origin) => $origin !== '*');
        if (!empty($allowedOrigins)) {
            $policies[] = "connect-src 'self' " . implode(' ', $allowedOrigins);
        }

        // Relax policies for development
        if ($environment === 'local' || $environment === 'development') {
            $policies = array_map(function ($policy) {
                if (str_starts_with($policy, 'connect-src')) {
                    return $policy . ' ws: wss:'; // Allow WebSocket connections
                }
                return $policy;
            }, $policies);
        }

        return implode('; ', $policies);
    }
}
