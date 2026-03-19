<?php

namespace App\Http\Clients;

use Shared\RPC\Clients\AuthServiceClient;
use Shared\RPC\Clients\UserServiceClient;
use Illuminate\Support\Facades\Log;

/**
 * RPC-based Auth Service Client
 * 
 * Maintains the same interface as AuthServiceClient but uses RPC calls
 * instead of HTTP requests for improved performance and reliability.
 */
class RpcAuthServiceClient
{
    private AuthServiceClient $authRpcClient;
    private UserServiceClient $userRpcClient;

    public function __construct()
    {
        $this->authRpcClient = app(AuthServiceClient::class);
        $this->userRpcClient = app(UserServiceClient::class);
    }

    /**
     * Validate analytics access token.
     */
    public function validateToken(string $token): ?array
    {
        try {
            $response = $this->authRpcClient->validateToken($token);
            
            return $response->isSuccess() ? $response->getData() : null;
        } catch (\Exception $e) {
            Log::error('RPC Auth token validation failed', [
                'error' => $e->getMessage(),
                'token_prefix' => substr($token, 0, 10) . '...'
            ]);
            return null;
        }
    }

    /**
     * Check if user has analytics permissions.
     */
    public function hasAnalyticsPermission(int $userId, string $permission): bool
    {
        try {
            $response = $this->userRpcClient->getUserProfile($userId);
            
            if (!$response->isSuccess()) {
                return false;
            }
            
            $userData = $response->getData();
            $permissions = $userData['permissions'] ?? [];
            
            return in_array($permission, $permissions) || in_array('analytics.*', $permissions);
        } catch (\Exception $e) {
            Log::error('RPC Auth permission check failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'permission' => $permission
            ]);
            return false;
        }
    }

    /**
     * Get analytics audit logs.
     * Note: This is a placeholder implementation until audit logging is implemented in the modern RPC clients
     */
    public function getAuditLogs(array $filters = []): array
    {
        try {
            // TODO: Implement audit logging in the modern RPC ecosystem
            Log::info('Audit logs requested but not yet implemented in modern RPC clients', [
                'filters' => $filters
            ]);
            
            return [];
        } catch (\Exception $e) {
            Log::error('RPC Auth audit logs retrieval failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return [];
        }
    }

    /**
     * Log analytics activity.
     */
    public function logAnalyticsActivity(int $userId, string $action, array $data): bool
    {
        try {
            $result = $this->userRpcClient->logUserActivity($userId, [
                'action' => $action,
                'data' => $data,
                'timestamp' => now()->toISOString(),
                'service' => 'analytics-service'
            ]);
            
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            Log::error('RPC Auth activity logging failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'action' => $action
            ]);
            return false;
        }
    }

    /**
     * Health check for compatibility with BaseServiceClient interface
     */
    public function healthCheck(): bool
    {
        try {
            $result = $this->userRpcClient->healthCheck();
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service info for compatibility with BaseServiceClient interface
     */
    public function getServiceInfo(): ?array
    {
        try {
            $result = $this->userRpcClient->getServiceInfo();
            return $result['success'] ?? false ? ($result['data'] ?? null) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
