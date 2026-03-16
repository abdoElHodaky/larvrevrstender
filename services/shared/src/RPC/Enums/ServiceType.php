<?php

declare(strict_types=1);

namespace Shared\RPC\Enums;

/**
 * Service Type Enum - PHP 8.3 Modern Implementation
 * 
 * Defines all available microservices in the ecosystem
 * with their default ports and health check endpoints.
 */
enum ServiceType: string
{
    case AUTH = 'auth-service';
    case USER = 'user-service';
    case AUCTION = 'auction-service';
    case BIDDING = 'bidding-service';
    case PAYMENT = 'payment-service';
    case ORDER = 'order-service';
    case NOTIFICATION = 'notification-service';
    case ANALYTICS = 'analytics-service';
    case VIN_OCR = 'vin-ocr-service';
    case GATEWAY = 'gateway-service';

    /**
     * Get the default port for this service
     */
    public function getDefaultPort(): int
    {
        return match ($this) {
            self::AUTH => 8000,
            self::USER => 8001,
            self::AUCTION => 8002,
            self::BIDDING => 8003,
            self::PAYMENT => 8004,
            self::ORDER => 8005,
            self::NOTIFICATION => 8006,
            self::ANALYTICS => 8007,
            self::VIN_OCR => 8008,
            self::GATEWAY => 8080,
        };
    }

    /**
     * Get the service display name
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::AUTH => 'Authentication Service',
            self::USER => 'User Management Service',
            self::AUCTION => 'Auction Service',
            self::BIDDING => 'Bidding Service',
            self::PAYMENT => 'Payment Processing Service',
            self::ORDER => 'Order Management Service',
            self::NOTIFICATION => 'Notification Service',
            self::ANALYTICS => 'Analytics Service',
            self::VIN_OCR => 'VIN OCR Service',
            self::GATEWAY => 'API Gateway Service',
        };
    }

    /**
     * Get the health check endpoint for this service
     */
    public function getHealthEndpoint(): string
    {
        return '/health';
    }

    /**
     * Get the service info endpoint
     */
    public function getInfoEndpoint(): string
    {
        return '/info';
    }

    /**
     * Get the base URL for this service in the given environment
     */
    public function getBaseUrl(string $environment = 'local'): string
    {
        $host = match ($environment) {
            'local' => 'localhost',
            'docker' => $this->value,
            'kubernetes' => "{$this->value}.default.svc.cluster.local",
            default => $this->value,
        };

        return "http://{$host}:{$this->getDefaultPort()}";
    }

    /**
     * Check if this service requires authentication
     */
    public function requiresAuth(): bool
    {
        return match ($this) {
            self::GATEWAY => false, // Gateway handles auth
            default => true,
        };
    }

    /**
     * Get all service types as array
     */
    public static function getAllServices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get critical services that must be healthy for system operation
     */
    public static function getCriticalServices(): array
    {
        return [
            self::AUTH,
            self::GATEWAY,
            self::USER,
        ];
    }
}
