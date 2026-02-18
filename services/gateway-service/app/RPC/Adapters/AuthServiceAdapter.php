<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AuthServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the auth service.
 * Gateway service needs comprehensive auth operations for request routing.
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
     * Verify user permissions for gateway operations
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
     * Authenticate user with credentials
     */
    public function authenticate(string $email, string $password): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('authenticate', ['email' => $email, 'password' => '[REDACTED]'], $correlationId);
            
            $response = $this->authRpc->call('auth.authenticate', [
                'email' => $email,
                'password' => $password
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('authenticate', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('authenticate', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Register new user
     */
    public function register(array $userData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('register', ['email' => $userData['email'] ?? 'N/A'], $correlationId);
            
            $response = $this->authRpc->call('auth.register', $userData);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('register', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('register', $e, $correlationId, $duration);
            return null;
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
        Log::info("RPC Call: auth.{$method} ({$status})", [
            'method' => "auth.{$method}",
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
        Log::error("RPC Error: auth.{$method}", [
            'method' => "auth.{$method}",
            'correlation_id' => $correlationId,
            'service' => 'gateway-service',
            'error' => $e->getMessage(),
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
