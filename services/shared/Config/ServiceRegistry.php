<?php

namespace Shared\Config;

class ServiceRegistry
{
    /**
     * Get all service configurations.
     */
    public static function getServices(): array
    {
        return [
            'auth' => [
                'name' => 'Auth Service',
                'url' => env('AUTH_SERVICE_URL', 'http://auth-service:8000'),
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'user' => [
                'name' => 'User Service',
                'url' => env('USER_SERVICE_URL', 'http://user-service:8001'),
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'payment' => [
                'name' => 'Payment Service',
                'url' => env('PAYMENT_SERVICE_URL', 'http://payment-service:8002'),
                'timeout' => 45, // Longer timeout for payment operations
                'retry_attempts' => 2, // Fewer retries for payment operations
                'retry_delay' => 2000,
            ],
            'order' => [
                'name' => 'Order Service',
                'url' => env('ORDER_SERVICE_URL', 'http://order-service:8003'),
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'bidding' => [
                'name' => 'Bidding Service',
                'url' => env('BIDDING_SERVICE_URL', 'http://bidding-service:8004'),
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'analytics' => [
                'name' => 'Analytics Service',
                'url' => env('ANALYTICS_SERVICE_URL', 'http://analytics-service:8005'),
                'timeout' => 60, // Longer timeout for analytics operations
                'retry_attempts' => 2,
                'retry_delay' => 1500,
            ],
            'notification' => [
                'name' => 'Notification Service',
                'url' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8006'),
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'vin-ocr' => [
                'name' => 'VIN OCR Service',
                'url' => env('VIN_OCR_SERVICE_URL', 'http://vin-ocr-service:8007'),
                'timeout' => 120, // Longer timeout for OCR processing
                'retry_attempts' => 2,
                'retry_delay' => 3000,
            ],
        ];
    }

    /**
     * Get configuration for a specific service.
     */
    public static function getService(string $serviceName): ?array
    {
        $services = self::getServices();
        return $services[$serviceName] ?? null;
    }

    /**
     * Get service URL by name.
     */
    public static function getServiceUrl(string $serviceName): ?string
    {
        $service = self::getService($serviceName);
        return $service['url'] ?? null;
    }

    /**
     * Get all allowed service names for authentication.
     */
    public static function getAllowedServiceNames(): array
    {
        return array_column(self::getServices(), 'name');
    }

    /**
     * Check if a service name is allowed.
     */
    public static function isServiceAllowed(string $serviceName): bool
    {
        return in_array($serviceName, self::getAllowedServiceNames());
    }

    /**
     * Get service configuration with defaults.
     */
    public static function getServiceConfig(string $serviceName): array
    {
        $service = self::getService($serviceName);
        
        if (!$service) {
            throw new \InvalidArgumentException("Service '{$serviceName}' not found in registry");
        }

        return array_merge([
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000,
        ], $service);
    }
}
