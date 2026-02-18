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
     * Validate user token for bidding operations.
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
     * Check if user has bidding permissions.
     */
    public function hasBiddingPermission(int $userId, string $permission): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.checkPermission', [
                'user_id' => $userId,
                'permission' => $permission
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('hasBiddingPermission', compact('userId', 'permission'), $response, $duration);

            return $response['success'] ?? false && $response['data']['has_permission'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('hasBiddingPermission', compact('userId', 'permission'), $e);
            return false;
        }
    }

    /**
     * Validate bid authorization.
     */
    public function validateBidAuth(int $userId, float $amount, int $auctionId): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.validateBidAuth', [
                'user_id' => $userId,
                'amount' => $amount,
                'auction_id' => $auctionId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('validateBidAuth', compact('userId', 'amount', 'auctionId'), $response, $duration);

            return $response['success'] ?? false && $response['data']['authorized'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('validateBidAuth', compact('userId', 'amount', 'auctionId'), $e);
            return false;
        }
    }

    /**
     * Log bidding activity for audit.
     */
    public function logBiddingActivity(int $userId, string $action, array $data): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.logBiddingActivity', [
                'user_id' => $userId,
                'action' => $action,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('logBiddingActivity', compact('userId', 'action', 'data'), $response, $duration);

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('logBiddingActivity', compact('userId', 'action', 'data'), $e);
            return false;
        }
    }

    /**
     * Get user bidding limits.
     */
    public function getUserBiddingLimits(int $userId): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->authRpcClient->call('auth.getUserBiddingLimits', [
                'user_id' => $userId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getUserBiddingLimits', compact('userId'), $response, $duration);

            return $response['success'] ?? false ? $response['data']['limits'] ?? [] : [];
        } catch (\Exception $e) {
            $this->logRpcError('getUserBiddingLimits', compact('userId'), $e);
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
