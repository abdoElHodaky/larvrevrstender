<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate Limiter Service - Controls request rates to prevent abuse
 */
class RateLimiterService
{
    private const CACHE_KEY_PREFIX = 'rate_limiter:';

    /**
     * Check if request is within rate limits
     */
    public function checkRateLimit(string $serviceName, array $context = []): bool
    {
        if (!config('gateway.rate_limit.enabled', true)) {
            return true;
        }

        $checks = [
            $this->checkGlobalRateLimit(),
            $this->checkServiceRateLimit($serviceName),
            $this->checkUserRateLimit($context),
            $this->checkIpRateLimit($context),
        ];

        return !in_array(false, $checks, true);
    }

    /**
     * Check global rate limit
     */
    private function checkGlobalRateLimit(): bool
    {
        if (!config('gateway.rate_limit.global.enabled', true)) {
            return true;
        }

        $key = 'global';
        $maxRequests = config('gateway.rate_limit.global.requests', 10000);
        $window = config('gateway.rate_limit.global.window', 60);

        return $this->checkLimit($key, $maxRequests, $window);
    }

    /**
     * Check service-specific rate limit
     */
    private function checkServiceRateLimit(string $serviceName): bool
    {
        if (!config('gateway.rate_limit.per_service.enabled', true)) {
            return true;
        }

        $key = "service:{$serviceName}";
        $maxRequests = config("gateway.rate_limit.services.{$serviceName}.requests") 
            ?? config('gateway.rate_limit.per_service.requests', 5000);
        $window = config("gateway.rate_limit.services.{$serviceName}.window")
            ?? config('gateway.rate_limit.per_service.window', 60);

        return $this->checkLimit($key, $maxRequests, $window);
    }

    /**
     * Check user-specific rate limit
     */
    private function checkUserRateLimit(array $context): bool
    {
        if (!config('gateway.rate_limit.per_user.enabled', true)) {
            return true;
        }

        $userId = $context['user_id'] ?? $context['authenticated_user_id'] ?? null;
        if (!$userId) {
            return true; // No user context, skip user rate limiting
        }

        $key = "user:{$userId}";
        $maxRequests = config('gateway.rate_limit.per_user.requests', 1000);
        $window = config('gateway.rate_limit.per_user.window', 60);

        return $this->checkLimit($key, $maxRequests, $window);
    }

    /**
     * Check IP-based rate limit
     */
    private function checkIpRateLimit(array $context): bool
    {
        if (!config('gateway.rate_limit.per_ip.enabled', false)) {
            return true;
        }

        $ip = $context['ip_address'] ?? $context['client_ip'] ?? null;
        if (!$ip) {
            return true; // No IP context, skip IP rate limiting
        }

        $key = "ip:{$ip}";
        $maxRequests = config('gateway.rate_limit.per_ip.requests', 500);
        $window = config('gateway.rate_limit.per_ip.window', 60);

        return $this->checkLimit($key, $maxRequests, $window);
    }

    /**
     * Check rate limit using sliding window algorithm
     */
    private function checkLimit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $key;
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Get current request timestamps
        $requests = Cache::get($cacheKey, []);
        
        // Remove old requests outside the window
        $requests = array_filter($requests, function ($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        // Check if we're at the limit
        if (count($requests) >= $maxRequests) {
            Log::warning("Rate limit exceeded", [
                'key' => $key,
                'current_requests' => count($requests),
                'max_requests' => $maxRequests,
                'window_seconds' => $windowSeconds,
            ]);
            return false;
        }

        // Add current request timestamp
        $requests[] = $now;

        // Store updated requests (expire after window + buffer)
        Cache::put($cacheKey, $requests, $windowSeconds + 60);

        return true;
    }

    /**
     * Get current rate limit status for all limits
     */
    public function getCurrentLimits(): array
    {
        $limits = [];

        // Global limits
        if (config('gateway.rate_limit.global.enabled', true)) {
            $limits['global'] = $this->getLimitStatus('global');
        }

        // Service limits
        if (config('gateway.rate_limit.per_service.enabled', true)) {
            $services = ['auth', 'user', 'analytics', 'order', 'payment', 'bidding', 'notification', 'vin-ocr', 'shared'];
            foreach ($services as $service) {
                $limits["service:{$service}"] = $this->getLimitStatus("service:{$service}");
            }
        }

        return $limits;
    }

    /**
     * Get rate limit status for a specific key
     */
    private function getLimitStatus(string $key): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $key;
        $requests = Cache::get($cacheKey, []);
        $now = time();
        
        // Determine window and max requests based on key type
        [$maxRequests, $windowSeconds] = $this->getLimitConfig($key);
        $windowStart = $now - $windowSeconds;
        
        // Count current requests in window
        $currentRequests = count(array_filter($requests, function ($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        }));

        return [
            'key' => $key,
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'window_seconds' => $windowSeconds,
            'remaining_requests' => max(0, $maxRequests - $currentRequests),
            'reset_time' => $windowStart + $windowSeconds,
            'percentage_used' => $maxRequests > 0 ? round(($currentRequests / $maxRequests) * 100, 2) : 0,
        ];
    }

    /**
     * Get limit configuration for a key
     */
    private function getLimitConfig(string $key): array
    {
        if ($key === 'global') {
            return [
                config('gateway.rate_limit.global.requests', 10000),
                config('gateway.rate_limit.global.window', 60)
            ];
        }

        if (str_starts_with($key, 'service:')) {
            $serviceName = substr($key, 8);
            return [
                config("gateway.rate_limit.services.{$serviceName}.requests") 
                    ?? config('gateway.rate_limit.per_service.requests', 5000),
                config("gateway.rate_limit.services.{$serviceName}.window")
                    ?? config('gateway.rate_limit.per_service.window', 60)
            ];
        }

        if (str_starts_with($key, 'user:')) {
            return [
                config('gateway.rate_limit.per_user.requests', 1000),
                config('gateway.rate_limit.per_user.window', 60)
            ];
        }

        if (str_starts_with($key, 'ip:')) {
            return [
                config('gateway.rate_limit.per_ip.requests', 500),
                config('gateway.rate_limit.per_ip.window', 60)
            ];
        }

        // Default
        return [1000, 60];
    }

    /**
     * Reset rate limit for a specific key
     */
    public function resetLimit(string $key): void
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $key;
        Cache::forget($cacheKey);
        
        Log::info("Rate limit reset", ['key' => $key]);
    }

    /**
     * Reset all rate limits
     */
    public function resetAllLimits(): void
    {
        $pattern = self::CACHE_KEY_PREFIX . '*';
        
        // This is a simplified approach - in production, you might want to use Redis SCAN
        $keys = [
            'global',
            'service:auth', 'service:user', 'service:analytics', 'service:order',
            'service:payment', 'service:bidding', 'service:notification', 
            'service:vin-ocr', 'service:shared'
        ];

        foreach ($keys as $key) {
            $this->resetLimit($key);
        }
        
        Log::info("All rate limits reset");
    }

    /**
     * Get rate limiting statistics
     */
    public function getStatistics(): array
    {
        $limits = $this->getCurrentLimits();
        $stats = [
            'total_limits' => count($limits),
            'active_limits' => 0,
            'highest_usage_percentage' => 0,
            'limits_near_threshold' => 0, // > 80% usage
            'limits_at_threshold' => 0,   // 100% usage
        ];

        foreach ($limits as $limit) {
            if ($limit['current_requests'] > 0) {
                $stats['active_limits']++;
            }
            
            $stats['highest_usage_percentage'] = max(
                $stats['highest_usage_percentage'], 
                $limit['percentage_used']
            );
            
            if ($limit['percentage_used'] >= 100) {
                $stats['limits_at_threshold']++;
            } elseif ($limit['percentage_used'] >= 80) {
                $stats['limits_near_threshold']++;
            }
        }

        return $stats;
    }

    /**
     * Check if rate limiting is healthy (not too many limits at threshold)
     */
    public function isHealthy(): bool
    {
        $stats = $this->getStatistics();
        
        // Consider unhealthy if more than 25% of limits are at threshold
        $thresholdPercentage = $stats['total_limits'] > 0 
            ? ($stats['limits_at_threshold'] / $stats['total_limits']) * 100 
            : 0;
            
        return $thresholdPercentage <= 25;
    }
}
