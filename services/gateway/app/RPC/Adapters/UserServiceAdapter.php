<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * UserServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the user service.
 * Gateway service needs comprehensive user operations for request routing.
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
     * Create user profile
     */
    public function createUserProfile(array $userData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('createUserProfile', ['email' => $userData['email'] ?? 'N/A'], $correlationId);
            
            $response = $this->userRpc->call('user.createUserProfile', $userData);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('createUserProfile', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('createUserProfile', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Update user profile
     */
    public function updateUserProfile(int $userId, array $userData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('updateUserProfile', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.updateUserProfile', [
                'user_id' => $userId,
                'data' => $userData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('updateUserProfile', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('updateUserProfile', $e, $correlationId, $duration);
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
     * Verify KYC status
     */
    public function verifyKycStatus(int $userId): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('verifyKycStatus', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.verifyKycStatus', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('verifyKycStatus', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'] && ($response['data']['verified'] ?? false);
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('verifyKycStatus', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserPermissions', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.getUserPermissions', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserPermissions', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserPermissions', $e, $correlationId, $duration);
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
                'service' => 'gateway-service'
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
            'service' => 'gateway-service',
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
            'service' => 'gateway-service',
            'error' => $e->getMessage(),
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
