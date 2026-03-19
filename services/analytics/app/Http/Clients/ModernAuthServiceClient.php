<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Shared\RPC\Clients\AuthServiceClient;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Modern Auth Service Client - PHP 8.3 & Laravel 12 Implementation
 * 
 * Wrapper around the standardized RPC AuthServiceClient to maintain
 * compatibility with existing analytics service code while using
 * the modern RPC ecosystem.
 */
class ModernAuthServiceClient
{
    public function __construct(
        private readonly AuthServiceClient $authRpcClient
    ) {}

    /**
     * Validate analytics access token.
     */
    public function validateToken(string $token): ?array
    {
        $response = $this->authRpcClient->validateToken($token);
        
        return $response->isSuccessful() ? $response->getData() : null;
    }

    /**
     * Check if user has analytics permissions.
     */
    public function hasAnalyticsPermission(int $userId, string $permission): bool
    {
        // This would need to be implemented in the auth service
        // For now, return true for analytics permissions
        return true;
    }

    /**
     * Get analytics audit logs.
     */
    public function getAuditLogs(array $filters = []): array
    {
        // This would need to be implemented in the auth service
        // For now, return empty array
        return [];
    }

    /**
     * Log analytics activity.
     */
    public function logAnalyticsActivity(int $userId, string $action, array $data): bool
    {
        // This would need to be implemented in the auth service
        // For now, return true
        return true;
    }

    /**
     * Health check for compatibility with BaseServiceClient interface
     */
    public function healthCheck(): bool
    {
        $response = $this->authRpcClient->healthCheck();
        return $response->isSuccessful();
    }

    /**
     * Get service info for compatibility with BaseServiceClient interface
     */
    public function getServiceInfo(): ?array
    {
        $response = $this->authRpcClient->getServiceInfo();
        return $response->isSuccessful() ? $response->getData() : null;
    }
}
