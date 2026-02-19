<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

/**
 * UserServiceAdapter - Compatibility layer between HTTP client interface and RPC
 * 
 * Provides the same interface as UserServiceClient but routes calls through RPC
 * for seamless migration from HTTP to RPC communication.
 */
class UserServiceAdapter
{
    private $userRpcClient;
    private string $correlationId;

    public function __construct()
    {
        $this->userRpcClient = App::make('UserRpc');
        $this->correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
    }

    /**
     * Get user profile for auction verification.
     */
    public function getUserProfile(int $userId): ?array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->userRpcClient->call('user.getProfile', [
                'user_id' => $userId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getUserProfile', compact('userId'), $response, $duration);

            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            $this->logRpcError('getUserProfile', compact('userId'), $e);
            return null;
        }
    }

    /**
     * Verify user KYC status for auction eligibility.
     */
    public function verifyKycStatus(int $userId): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->userRpcClient->call('user.verifyKycStatus', [
                'user_id' => $userId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('verifyKycStatus', compact('userId'), $response, $duration);

            return $response['success'] ?? false && $response['data']['kyc_verified'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('verifyKycStatus', compact('userId'), $e);
            return false;
        }
    }

    /**
     * Get user wallet balance for auction validation.
     */
    public function getUserWalletBalance(int $userId): float
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->userRpcClient->call('user.getWalletBalance', [
                'user_id' => $userId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getUserWalletBalance', compact('userId'), $response, $duration);

            return $response['success'] ?? false ? (float) ($response['data']['balance'] ?? 0) : 0.0;
        } catch (\Exception $e) {
            $this->logRpcError('getUserWalletBalance', compact('userId'), $e);
            return 0.0;
        }
    }

    /**
     * Get user auction preferences.
     */
    public function getUserAuctionPreferences(int $userId): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->userRpcClient->call('user.getAuctionPreferences', [
                'user_id' => $userId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getUserAuctionPreferences', compact('userId'), $response, $duration);

            return $response['success'] ?? false ? $response['data']['preferences'] ?? [] : [];
        } catch (\Exception $e) {
            $this->logRpcError('getUserAuctionPreferences', compact('userId'), $e);
            return [];
        }
    }

    /**
     * Health check - compatibility method
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->userRpcClient->call('system.health');
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
            $response = $this->userRpcClient->call('system.info');
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
            'adapter' => 'UserServiceAdapter',
            'method' => $method,
            'duration' => round($duration * 1000, 2) . 'ms',
            'correlation_id' => $this->correlationId,
            'success' => $response['success'] ?? false,
            'service' => 'user-service'
        ]);
    }

    /**
     * Log RPC call error
     */
    private function logRpcError(string $method, array $params, \Exception $e): void
    {
        Log::error('RPC call failed', [
            'adapter' => 'UserServiceAdapter',
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $this->correlationId,
            'service' => 'user-service'
        ]);
    }
}
