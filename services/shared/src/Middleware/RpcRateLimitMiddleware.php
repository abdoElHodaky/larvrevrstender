<?php

declare(strict_types=1);

namespace Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * RPC Rate Limiting Middleware
 * 
 * Provides rate limiting for RPC endpoints with circuit breaker integration.
 * Works alongside existing circuit breaker rate limiting for comprehensive protection.
 * 
 * Features:
 * - Per-service rate limiting
 * - Per-method rate limiting  
 * - Per-client IP rate limiting
 * - Sliding window algorithm
 * - Circuit breaker integration
 * - Configurable limits and windows
 */
class RpcRateLimitMiddleware
{
    /**
     * Default rate limits (requests per minute)
     */
    private const DEFAULT_LIMITS = [
        'global' => 1000,        // Global service limit
        'per_ip' => 100,         // Per IP limit
        'per_method' => 200,     // Per RPC method limit
        'authenticated' => 500,   // Authenticated users get higher limits
        'admin' => 2000,         // Admin users get highest limits
    ];

    /**
     * Rate limit windows (in seconds)
     */
    private const WINDOWS = [
        'short' => 60,    // 1 minute
        'medium' => 300,  // 5 minutes
        'long' => 3600,   // 1 hour
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limit = 'default'): Response
    {
        // Skip rate limiting for health checks and internal calls
        if ($this->shouldSkipRateLimit($request)) {
            return $next($request);
        }

        $clientIp = $this->getClientIp($request);
        $rpcMethod = $this->extractRpcMethod($request);
        $userType = $this->getUserType($request);

        // Check multiple rate limit dimensions
        $rateLimitChecks = [
            'global' => $this->checkGlobalRateLimit(),
            'per_ip' => $this->checkPerIpRateLimit($clientIp, $userType),
            'per_method' => $this->checkPerMethodRateLimit($rpcMethod, $userType),
            'circuit_breaker' => $this->checkCircuitBreakerStatus($rpcMethod),
        ];

        // Find the most restrictive limit that's been exceeded
        foreach ($rateLimitChecks as $checkType => $result) {
            if (!$result['allowed']) {
                return $this->createRateLimitResponse($checkType, $result, $request);
            }
        }

        // Record the request for rate limiting
        $this->recordRequest($clientIp, $rpcMethod, $userType);

        $response = $next($request);

        // Add rate limit headers to response
        $this->addRateLimitHeaders($response, $rateLimitChecks);

        return $response;
    }

    /**
     * Check if request should skip rate limiting
     */
    private function shouldSkipRateLimit(Request $request): bool
    {
        $skipPaths = [
            '/health',
            '/up',
            '/info',
        ];

        $path = $request->getPathInfo();
        
        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        // Skip for internal service-to-service calls with valid service tokens
        if ($request->hasHeader('X-Service-Token')) {
            return $this->isValidServiceToken($request->header('X-Service-Token'));
        }

        return false;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): string
    {
        // Check for forwarded IP from load balancer/proxy
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            return explode(',', $forwardedFor)[0];
        }

        return $request->ip();
    }

    /**
     * Extract RPC method from request
     */
    private function extractRpcMethod(Request $request): string
    {
        // For JSON-RPC requests
        if ($request->isJson()) {
            $payload = $request->json()->all();
            return $payload['method'] ?? 'unknown';
        }

        // For REST-style RPC endpoints
        $path = $request->getPathInfo();
        $segments = explode('/', trim($path, '/'));
        
        return end($segments) ?: 'unknown';
    }

    /**
     * Determine user type for rate limiting
     */
    private function getUserType(Request $request): string
    {
        // Check for authenticated user
        if ($request->hasHeader('Authorization')) {
            $token = $request->bearerToken();
            if ($token) {
                $userType = $this->getUserTypeFromToken($token);
                return $userType ?: 'authenticated';
            }
        }

        // Check for service authentication
        if ($request->hasHeader('X-Service-Auth')) {
            return 'service';
        }

        return 'anonymous';
    }

    /**
     * Check global service rate limit
     */
    private function checkGlobalRateLimit(): array
    {
        $key = 'rate_limit:global:' . config('app.name');
        $limit = config('rpc.rate_limits.global', self::DEFAULT_LIMITS['global']);
        $window = self::WINDOWS['short'];

        return $this->checkRateLimit($key, $limit, $window);
    }

    /**
     * Check per-IP rate limit
     */
    private function checkPerIpRateLimit(string $clientIp, string $userType): array
    {
        $key = "rate_limit:ip:{$clientIp}";
        $limit = $this->getLimitForUserType($userType);
        $window = self::WINDOWS['short'];

        return $this->checkRateLimit($key, $limit, $window);
    }

    /**
     * Check per-method rate limit
     */
    private function checkPerMethodRateLimit(string $method, string $userType): array
    {
        $key = "rate_limit:method:{$method}";
        $limit = config("rpc.rate_limits.methods.{$method}", self::DEFAULT_LIMITS['per_method']);
        
        // Adjust limit based on user type
        $limit = $this->adjustLimitForUserType($limit, $userType);
        $window = self::WINDOWS['short'];

        return $this->checkRateLimit($key, $limit, $window);
    }

    /**
     * Check circuit breaker status
     */
    private function checkCircuitBreakerStatus(string $method): array
    {
        $key = "circuit_breaker:{$method}";
        $status = Cache::get($key, 'closed');

        if ($status === 'open') {
            return [
                'allowed' => false,
                'limit' => 0,
                'remaining' => 0,
                'reset_time' => Cache::get("{$key}:reset_time", time() + 300),
                'reason' => 'Circuit breaker is open'
            ];
        }

        return [
            'allowed' => true,
            'limit' => PHP_INT_MAX,
            'remaining' => PHP_INT_MAX,
            'reset_time' => null,
            'reason' => null
        ];
    }

    /**
     * Core rate limiting logic using sliding window
     */
    private function checkRateLimit(string $key, int $limit, int $window): array
    {
        $now = time();
        $windowStart = $now - $window;

        // Get current request count in window
        $requests = Cache::get($key, []);
        
        // Remove old requests outside the window
        $requests = array_filter($requests, fn($timestamp) => $timestamp > $windowStart);

        $currentCount = count($requests);
        $remaining = max(0, $limit - $currentCount);
        $resetTime = $windowStart + $window;

        return [
            'allowed' => $currentCount < $limit,
            'limit' => $limit,
            'remaining' => $remaining,
            'reset_time' => $resetTime,
            'current_count' => $currentCount,
            'reason' => $currentCount >= $limit ? 'Rate limit exceeded' : null
        ];
    }

    /**
     * Record request for rate limiting
     */
    private function recordRequest(string $clientIp, string $method, string $userType): void
    {
        $now = time();
        $window = self::WINDOWS['short'];

        $keys = [
            'rate_limit:global:' . config('app.name'),
            "rate_limit:ip:{$clientIp}",
            "rate_limit:method:{$method}",
        ];

        foreach ($keys as $key) {
            $requests = Cache::get($key, []);
            $requests[] = $now;
            
            // Keep only requests within the window
            $windowStart = $now - $window;
            $requests = array_filter($requests, fn($timestamp) => $timestamp > $windowStart);
            
            Cache::put($key, $requests, $window + 60); // Extra 60 seconds buffer
        }

        // Log rate limit activity for monitoring
        Log::info('RPC rate limit check', [
            'client_ip' => $clientIp,
            'method' => $method,
            'user_type' => $userType,
            'timestamp' => $now
        ]);
    }

    /**
     * Get rate limit based on user type
     */
    private function getLimitForUserType(string $userType): int
    {
        return match ($userType) {
            'admin' => self::DEFAULT_LIMITS['admin'],
            'authenticated' => self::DEFAULT_LIMITS['authenticated'],
            'service' => self::DEFAULT_LIMITS['admin'], // Service-to-service gets high limits
            default => self::DEFAULT_LIMITS['per_ip']
        };
    }

    /**
     * Adjust limit based on user type
     */
    private function adjustLimitForUserType(int $baseLimit, string $userType): int
    {
        $multiplier = match ($userType) {
            'admin' => 5.0,
            'authenticated' => 2.0,
            'service' => 10.0,
            default => 1.0
        };

        return (int) ($baseLimit * $multiplier);
    }

    /**
     * Create rate limit exceeded response
     */
    private function createRateLimitResponse(string $checkType, array $result, Request $request): Response
    {
        $message = $result['reason'] ?? 'Rate limit exceeded';
        
        $responseData = [
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => $message,
                'type' => $checkType,
                'limit' => $result['limit'],
                'remaining' => $result['remaining'],
                'reset_time' => $result['reset_time'],
                'retry_after' => max(1, $result['reset_time'] - time()),
            ]
        ];

        // Log rate limit violation
        Log::warning('RPC rate limit exceeded', [
            'check_type' => $checkType,
            'client_ip' => $this->getClientIp($request),
            'method' => $this->extractRpcMethod($request),
            'user_type' => $this->getUserType($request),
            'limit' => $result['limit'],
            'current_count' => $result['current_count'] ?? 0,
        ]);

        $response = new Response(
            json_encode($responseData),
            SymfonyResponse::HTTP_TOO_MANY_REQUESTS,
            [
                'Content-Type' => 'application/json',
                'X-RateLimit-Limit' => $result['limit'],
                'X-RateLimit-Remaining' => $result['remaining'],
                'X-RateLimit-Reset' => $result['reset_time'],
                'Retry-After' => max(1, $result['reset_time'] - time()),
            ]
        );

        return $response;
    }

    /**
     * Add rate limit headers to successful responses
     */
    private function addRateLimitHeaders(Response $response, array $rateLimitChecks): void
    {
        // Use the most restrictive limits for headers
        $mostRestrictive = $this->getMostRestrictiveLimit($rateLimitChecks);

        $response->headers->set('X-RateLimit-Limit', $mostRestrictive['limit']);
        $response->headers->set('X-RateLimit-Remaining', $mostRestrictive['remaining']);
        $response->headers->set('X-RateLimit-Reset', $mostRestrictive['reset_time']);
    }

    /**
     * Get the most restrictive rate limit from all checks
     */
    private function getMostRestrictiveLimit(array $rateLimitChecks): array
    {
        $mostRestrictive = [
            'limit' => PHP_INT_MAX,
            'remaining' => PHP_INT_MAX,
            'reset_time' => time() + 3600
        ];

        foreach ($rateLimitChecks as $check) {
            if ($check['allowed'] && $check['remaining'] < $mostRestrictive['remaining']) {
                $mostRestrictive = $check;
            }
        }

        return $mostRestrictive;
    }

    /**
     * Validate service token for internal calls
     */
    private function isValidServiceToken(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        $validTokens = config('rpc.service_tokens', []);
        return in_array($token, $validTokens, true);
    }

    /**
     * Get user type from JWT token
     */
    private function getUserTypeFromToken(string $token): ?string
    {
        try {
            // This would integrate with your JWT implementation
            // For now, return null to fall back to 'authenticated'
            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to decode JWT token for rate limiting', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
