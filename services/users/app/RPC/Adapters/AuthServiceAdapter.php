<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AuthServiceAdapter for User Service
 * 
 * Provides HTTP-like interface for RPC calls to the auth service.
 * Maintains compatibility with existing AuthServiceClient usage.
 */
class AuthServiceAdapter
{
    private $authRpc;

    public function __construct()
    {
        $this->authRpc = app(\Shared\RPC\Clients\AuthServiceClient::class);
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
            
            $response = $this->authRpc->validateToken($token);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('validateToken', ['duration_ms' => $duration], $correlationId, 'success');
            
            if ($response->isSuccess()) {
                return $response->getData();
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('validateToken', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Check if user has specific permission
     */
    public function hasUserPermission(int $userId, string $permission): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('hasUserPermission', ['user_id' => $userId, 'permission' => $permission], $correlationId);
            
            $response = $this->authRpc->call('auth.hasUserPermission', [
                'user_id' => $userId,
                'permission' => $permission
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('hasUserPermission', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data']['has_permission'] ?? false;
            }
            
            return false;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('hasUserPermission', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Validate user authentication with full user context
     */
    public function validateUserAuth(int $userId, array $userData): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('validateUserAuth', ['user_id' => $userId], $correlationId);
            
            $response = $this->authRpc->call('auth.validateUserAuth', [
                'user_id' => $userId,
                'user_data' => $userData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('validateUserAuth', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data']['is_valid'] ?? false;
            }
            
            return false;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('validateUserAuth', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Log user activity for audit purposes
     */
    public function logUserActivity(int $userId, string $action, array $data): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('logUserActivity', ['user_id' => $userId, 'action' => $action], $correlationId);
            
            $response = $this->authRpc->call('auth.logUserActivity', [
                'user_id' => $userId,
                'action' => $action,
                'data' => $data,
                'timestamp' => now()->toISOString(),
                'service' => 'user-service'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('logUserActivity', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('logUserActivity', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Get user-specific limits and quotas
     */
    public function getUserLimits(int $userId): array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserLimits', ['user_id' => $userId], $correlationId);
            
            $response = $this->authRpc->call('auth.getUserLimits', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserLimits', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? [];
            }
            
            return [];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserLimits', $e, $correlationId, $duration);
            return [];
        }
    }

    /**
     * Health check for auth service
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->authRpc->call('auth.healthCheck');
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            Log::warning('AuthService health check failed', [
                'error' => $e->getMessage(),
                'service' => 'user-service'
            ]);
            return false;
        }
    }

    /**
     * Get service information
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
                'service' => 'user-service'
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
            'service' => 'user-service',
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
            'service' => 'user-service',
            'error' => $e->getMessage(),
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
