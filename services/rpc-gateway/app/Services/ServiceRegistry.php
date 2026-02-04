<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service Registry - Manages discovery and health checking of RPC services
 */
class ServiceRegistry
{
    private const CACHE_KEY_PREFIX = 'service_registry:';
    private const HEALTH_CHECK_CACHE_TTL = 30; // seconds

    private array $services = [];
    private array $serviceInstances = [];

    public function __construct()
    {
        $this->loadServiceConfiguration();
    }

    /**
     * Load service configuration from environment
     */
    private function loadServiceConfiguration(): void
    {
        $this->services = [
            'auth' => [
                'name' => 'auth-service',
                'url' => config('services.auth.url', env('AUTH_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.auth.timeout', 5),
                'retries' => config('services.auth.retries', 3),
                'weight' => config('services.auth.weight', 1),
            ],
            'user' => [
                'name' => 'user-service',
                'url' => config('services.user.url', env('USER_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.user.timeout', 5),
                'retries' => config('services.user.retries', 3),
                'weight' => config('services.user.weight', 1),
            ],
            'analytics' => [
                'name' => 'analytics-service',
                'url' => config('services.analytics.url', env('ANALYTICS_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.analytics.timeout', 10),
                'retries' => config('services.analytics.retries', 2),
                'weight' => config('services.analytics.weight', 1),
            ],
            'order' => [
                'name' => 'order-service',
                'url' => config('services.order.url', env('ORDER_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.order.timeout', 8),
                'retries' => config('services.order.retries', 3),
                'weight' => config('services.order.weight', 2),
            ],
            'payment' => [
                'name' => 'payment-service',
                'url' => config('services.payment.url', env('PAYMENT_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.payment.timeout', 15),
                'retries' => config('services.payment.retries', 3),
                'weight' => config('services.payment.weight', 2),
            ],
            'bidding' => [
                'name' => 'bidding-service',
                'url' => config('services.bidding.url', env('BIDDING_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.bidding.timeout', 10),
                'retries' => config('services.bidding.retries', 3),
                'weight' => config('services.bidding.weight', 2),
            ],
            'notification' => [
                'name' => 'notification-service',
                'url' => config('services.notification.url', env('NOTIFICATION_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.notification.timeout', 5),
                'retries' => config('services.notification.retries', 2),
                'weight' => config('services.notification.weight', 1),
            ],
            'vin-ocr' => [
                'name' => 'vin-ocr-service',
                'url' => config('services.vin_ocr.url', env('VIN_OCR_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.vin_ocr.timeout', 30),
                'retries' => config('services.vin_ocr.retries', 2),
                'weight' => config('services.vin_ocr.weight', 1),
            ],
            'shared' => [
                'name' => 'shared-service',
                'url' => config('services.shared.url', env('SHARED_SERVICE_URL')),
                'health_endpoint' => '/health',
                'timeout' => config('services.shared.timeout', 5),
                'retries' => config('services.shared.retries', 3),
                'weight' => config('services.shared.weight', 1),
            ],
        ];
    }

    /**
     * Get all registered services
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Get service configuration by name
     */
    public function getService(string $serviceName): ?array
    {
        return $this->services[$serviceName] ?? null;
    }

    /**
     * Get healthy service instance for load balancing
     */
    public function getHealthyServiceInstance(string $serviceName): ?array
    {
        $service = $this->getService($serviceName);
        if (!$service) {
            return null;
        }

        // For now, return the single instance if healthy
        // In the future, this can be extended for multiple instances
        if ($this->isServiceHealthy($serviceName)) {
            return $service;
        }

        return null;
    }

    /**
     * Check if a service is healthy
     */
    public function isServiceHealthy(string $serviceName): bool
    {
        $cacheKey = self::CACHE_KEY_PREFIX . "health:{$serviceName}";
        
        return Cache::remember($cacheKey, self::HEALTH_CHECK_CACHE_TTL, function () use ($serviceName) {
            return $this->performHealthCheck($serviceName);
        });
    }

    /**
     * Perform actual health check
     */
    private function performHealthCheck(string $serviceName): bool
    {
        $service = $this->getService($serviceName);
        if (!$service) {
            return false;
        }

        try {
            $response = Http::timeout($service['timeout'])
                ->get($service['url'] . $service['health_endpoint']);

            $isHealthy = $response->successful();
            
            if (!$isHealthy) {
                Log::warning("Health check failed for service: {$serviceName}", [
                    'service' => $serviceName,
                    'url' => $service['url'],
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

            return $isHealthy;
        } catch (\Exception $e) {
            Log::error("Health check exception for service: {$serviceName}", [
                'service' => $serviceName,
                'url' => $service['url'],
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all services health status
     */
    public function getServicesHealthStatus(): array
    {
        $healthStatus = [];
        
        foreach (array_keys($this->services) as $serviceName) {
            $healthStatus[$serviceName] = [
                'healthy' => $this->isServiceHealthy($serviceName),
                'last_check' => now(),
                'service_info' => $this->getService($serviceName),
            ];
        }

        return $healthStatus;
    }

    /**
     * Force refresh health check cache
     */
    public function refreshHealthChecks(): void
    {
        foreach (array_keys($this->services) as $serviceName) {
            $cacheKey = self::CACHE_KEY_PREFIX . "health:{$serviceName}";
            Cache::forget($cacheKey);
        }
    }

    /**
     * Get service by RPC method pattern
     * Maps RPC method names to appropriate services
     */
    public function getServiceByRpcMethod(string $method): ?string
    {
        // Method pattern matching for service routing
        $methodPatterns = [
            // Auth service patterns
            '/^auth\./i' => 'auth',
            '/^login/i' => 'auth',
            '/^logout/i' => 'auth',
            '/^register/i' => 'auth',
            '/^verify/i' => 'auth',
            '/^token/i' => 'auth',
            
            // User service patterns
            '/^user\./i' => 'user',
            '/^profile/i' => 'user',
            '/^account/i' => 'user',
            
            // Analytics service patterns
            '/^analytics\./i' => 'analytics',
            '/^report/i' => 'analytics',
            '/^stats/i' => 'analytics',
            '/^metrics/i' => 'analytics',
            
            // Order service patterns
            '/^order\./i' => 'order',
            '/^orders/i' => 'order',
            '/^purchase/i' => 'order',
            
            // Payment service patterns
            '/^payment\./i' => 'payment',
            '/^pay/i' => 'payment',
            '/^transaction/i' => 'payment',
            '/^billing/i' => 'payment',
            
            // Bidding service patterns
            '/^bid/i' => 'bidding',
            '/^auction/i' => 'bidding',
            '/^tender/i' => 'bidding',
            
            // Notification service patterns
            '/^notification/i' => 'notification',
            '/^notify/i' => 'notification',
            '/^message/i' => 'notification',
            '/^email/i' => 'notification',
            '/^sms/i' => 'notification',
            
            // VIN OCR service patterns
            '/^vin/i' => 'vin-ocr',
            '/^ocr/i' => 'vin-ocr',
            '/^scan/i' => 'vin-ocr',
            
            // Shared service patterns (fallback)
            '/^shared\./i' => 'shared',
            '/^common/i' => 'shared',
            '/^utility/i' => 'shared',
        ];

        foreach ($methodPatterns as $pattern => $serviceName) {
            if (preg_match($pattern, $method)) {
                return $serviceName;
            }
        }

        // Default to shared service if no pattern matches
        return 'shared';
    }
}
