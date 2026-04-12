<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AuthServiceAdapter for Notification Service
 * 
 * Provides HTTP-like interface for RPC calls to the auth service.
 * Maintains compatibility with existing AuthServiceClient usage.
 */
class AuthServiceAdapter
{
    private $authRpc;

    public function __construct()
    {
        $this->authRpc = app('AuthRpc');
    }

    /**
     * Validate a token and return user information
     */
    public function validateToken(string $token): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('validateToken', ['token' => '[REDACTED]'], $correlationId);
            
            $response = $this->authRpc->call('auth.validateToken', [
                'token' => $token
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('validateToken', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('validateToken', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserById', ['user_id' => $userId], $correlationId);
            
            $response = $this->authRpc->call('auth.getUserById', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserById', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserById', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Verify user permissions
     */
    public function verifyPermissions(int $userId, array $permissions): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('verifyPermissions', ['user_id' => $userId, 'permissions' => $permissions], $correlationId);
            
            $response = $this->authRpc->call('auth.verifyPermissions', [
                'user_id' => $userId,
                'permissions' => $permissions
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('verifyPermissions', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'] && ($response['data']['authorized'] ?? false);
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('verifyPermissions', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Get service health status
     */
    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->authRpc->call('auth.getServiceInfo');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            Log::warning('Failed to get AuthService info', [
                'error' => $e->getMessage(),
                'service' => 'notification-service'
            ]);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $context, string $correlationId, string $status = 'start'): void
    {
        Log::info("RPC Call: auth.{$method} ({$status})", [
            'method' => "auth.{$method}",
            'correlation_id' => $correlationId,
            'service' => 'notification-service',
            'status' => $status,
            'context' => $context
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("RPC Error: auth.{$method}", [
            'method' => "auth.{$method}",
            'correlation_id' => $correlationId,
            'service' => 'notification-service',
            'error' => $e->getMessage(),
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
