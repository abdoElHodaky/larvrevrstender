<?php

namespace App\Http\Clients;

use App\RPC\Clients\UserServiceRpcClient;
use Illuminate\Support\Facades\Log;

/**
 * RPC-based Auth Service Client
 * 
 * Maintains the same interface as AuthServiceClient but uses RPC calls
 * instead of HTTP requests for improved performance and reliability.
 */
class RpcAuthServiceClient
{
    private UserServiceRpcClient $userRpcClient;

    public function __construct()
    {
        $this->userRpcClient = app(UserServiceRpcClient::class);
    }

    /**
     * Validate analytics access token.
     */
    public function validateToken(string $token): ?array
    {
        try {
            $result = $this->userRpcClient->validateUserToken($token);
            
            return $result['success'] ?? false ? $result['data'] ?? null : null;
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
            $result = $this->userRpcClient->checkUserPermission($userId, $permission);
            
            return $result['success'] ?? false && ($result['data']['has_permission'] ?? false);
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
     */
    public function getAuditLogs(array $filters = []): array
    {
        try {
            $result = $this->userRpcClient->getUserAuditLogs($filters);
            
            return $result['success'] ?? false ? ($result['data']['logs'] ?? []) : [];
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
