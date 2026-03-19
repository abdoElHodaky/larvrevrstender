<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * UserServiceAdapter for Payment Service
 * 
 * Provides HTTP-like interface for RPC calls to the user service.
 * Payment service needs user billing information for payment processing.
 */
class UserServiceAdapter
{
    private $userRpc;

    public function __construct()
    {
        $this->userRpc = app('UserRpc');
    }

    /**
     * Get user profile by ID
     */
    public function getUserProfile(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserProfile', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.getUserProfile', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserProfile', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserProfile', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get user billing information for payment processing
     */
    public function getUserBillingInfo(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserBillingInfo', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.getUserBillingInfo', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserBillingInfo', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserBillingInfo', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get user payment methods
     */
    public function getUserPaymentMethods(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserPaymentMethods', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.getUserPaymentMethods', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserPaymentMethods', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserPaymentMethods', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Verify user exists
     */
    public function verifyUserExists(int $userId): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('verifyUserExists', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.verifyUserExists', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('verifyUserExists', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'] && ($response['data']['exists'] ?? false);
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('verifyUserExists', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Update user billing information
     */
    public function updateUserBillingInfo(int $userId, array $billingData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('updateUserBillingInfo', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.updateUserBillingInfo', [
                'user_id' => $userId,
                'billing_data' => $billingData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('updateUserBillingInfo', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('updateUserBillingInfo', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserByEmail', ['email' => $email], $correlationId);
            
            $response = $this->userRpc->call('user.getUserByEmail', [
                'email' => $email
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserByEmail', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserByEmail', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get service health status
     */
    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->userRpc->call('user.getServiceInfo');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            Log::warning('Failed to get UserService info', [
                'error' => $e->getMessage(),
                'service' => 'payment-service'
            ]);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $context, string $correlationId, string $status = 'start'): void
    {
        Log::info("RPC Call: user.{$method} ({$status})", [
            'method' => "user.{$method}",
            'correlation_id' => $correlationId,
            'service' => 'payment-service',
            'status' => $status,
            'context' => $context
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("RPC Error: user.{$method}", [
            'method' => "user.{$method}",
            'correlation_id' => $correlationId,
            'service' => 'payment-service',
            'error' => $e->getMessage(),
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
