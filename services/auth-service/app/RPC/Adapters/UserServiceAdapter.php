<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * UserServiceAdapter for Auth Service
 * 
 * Provides HTTP-like interface for RPC calls to the user service.
 * Auth service needs user operations for authentication and user management.
 */
class UserServiceAdapter
{
    private $userRpc;

    public function __construct()
    {
        $this->userRpc = app('UserRpc');
    }

    /**
     * Get user by ID
     */
    public function getUser(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUser', ['user_id' => $userId], $correlationId);
            
            $response = $this->userRpc->call('user.getUser', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUser', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUser', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Create user activity
     */
    public function createActivity(array $activityData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('createActivity', ['activity_data' => $activityData], $correlationId);
            
            $response = $this->userRpc->call('user.createActivity', [
                'activity_data' => $activityData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('createActivity', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('createActivity', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get user activities
     */
    public function getUserActivities(int $userId, array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $params = array_merge(['user_id' => $userId], $filters);
            $this->logRpcCall('getUserActivities', $params, $correlationId);
            
            $response = $this->userRpc->call('user.getUserActivities', $params);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserActivities', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserActivities', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Auth UserService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'user-service',
            'caller' => 'auth-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Auth UserService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'user-service',
            'caller' => 'auth-service'
        ]);
    }
}
