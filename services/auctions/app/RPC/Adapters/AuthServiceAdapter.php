<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

/**
 * AuthServiceAdapter - Compatibility layer between HTTP client interface and RPC
 * 
 * Provides the same interface as AuthServiceClient but routes calls through RPC
 * for seamless migration from HTTP to RPC communication.
 */
class AuthServiceAdapter
{
    private $authRpcClient;
    private string $correlationId;

    public function __construct()
    {
        $this->authRpcClient = App::make('AuthRpc');
        $this->correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
    }

    /**
     * Validate user token for auction operations.
     */
    public function validateToken(string $token): ?array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.validateToken', [
                'token' => $token
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('validateToken', ['token' => '[REDACTED]'], $response, $duration);

            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            $this->logRpcError('validateToken', ['token' => '[REDACTED]'], $e);
            return null;
        }
    }

    /**
     * Validate JWT token and return user data.
     * Alias for validateToken to maintain compatibility with HTTP client interface.
     */
    public function validateJwtToken(string $token): ?array
    {
        return $this->validateToken($token);
    }

    /**
     * Check if user has auction permissions.
     */
    public function hasAuctionPermission(int $userId, string $permission): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.checkPermission', [
                'user_id' => $userId,
                'permission' => $permission
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('hasAuctionPermission', compact('userId', 'permission'), $response, $duration);

            return $response['success'] ?? false && $response['data']['has_permission'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('hasAuctionPermission', compact('userId', 'permission'), $e);
            return false;
        }
    }

    /**
     * Check if user has permission.
     * Alias for hasAuctionPermission to maintain compatibility with HTTP client interface.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        return $this->hasAuctionPermission($userId, $permission);
    }

    /**
     * Check if user has any of the specified roles.
     */
    public function hasRole(int $userId, array $roles): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.checkRole', [
                'user_id' => $userId,
                'roles' => $roles
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('hasRole', compact('userId', 'roles'), $response, $duration);

            return $response['success'] ?? false && $response['data']['has_role'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('hasRole', compact('userId', 'roles'), $e);
            return false;
        }
    }

    /**
     * Check if user can access auction for specified action.
     */
    public function canAccessAuction(int $userId, int $auctionId, string $action): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.canAccessAuction', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'action' => $action
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('canAccessAuction', compact('userId', 'auctionId', 'action'), $response, $duration);

            return $response['success'] ?? false && $response['data']['can_access'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('canAccessAuction', compact('userId', 'auctionId', 'action'), $e);
            return false;
        }
    }

    /**
     * Validate auction creation authorization.
     */
    public function validateAuctionAuth(int $userId, array $auctionData): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.validateAuctionAuth', [
                'user_id' => $userId,
                'auction_data' => $auctionData
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('validateAuctionAuth', compact('userId', 'auctionData'), $response, $duration);

            return $response['success'] ?? false && $response['data']['authorized'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('validateAuctionAuth', compact('userId', 'auctionData'), $e);
            return false;
        }
    }

    /**
     * Log auction activity for audit.
     */
    public function logAuctionActivity(int $userId, string $action, array $data): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.logAuctionActivity', [
                'user_id' => $userId,
                'action' => $action,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('logAuctionActivity', compact('userId', 'action', 'data'), $response, $duration);

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('logAuctionActivity', compact('userId', 'action', 'data'), $e);
            return false;
        }
    }

    /**
     * Get user auction limits.
     */
    public function getUserAuctionLimits(int $userId): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.getUserAuctionLimits', [
                'user_id' => $userId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getUserAuctionLimits', compact('userId'), $response, $duration);

            return $response['success'] ?? false ? $response['data']['limits'] ?? [] : [];
        } catch (\Exception $e) {
            $this->logRpcError('getUserAuctionLimits', compact('userId'), $e);
            return [];
        }
    }

    /**
     * Health check - compatibility method
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->authRpcClient->call('system.health');
            return $response['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service info - compatibility method
     */
    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->authRpcClient->call('system.info');
            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log successful RPC call
     */
    private function logRpcCall(string $method, array $params, $response, float $duration): void
    {
        Log::info('RPC call completed', [
            'adapter' => 'AuthServiceAdapter',
            'method' => $method,
            'duration' => round($duration * 1000, 2) . 'ms',
            'correlation_id' => $this->correlationId,
            'success' => $response['success'] ?? false,
            'service' => 'auth-service'
        ]);
    }

    /**
     * Log RPC call error
     */
    private function logRpcError(string $method, array $params, \Exception $e): void
    {
        Log::error('RPC call failed', [
            'adapter' => 'AuthServiceAdapter',
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $this->correlationId,
            'service' => 'auth-service'
        ]);
    }
}
