<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Sajya\Client\Client;
use Exception;

/**
 * User Service RPC Adapter for Auth Service
 * 
 * Provides semantic methods for interacting with user-service via RPC.
 * Used by auth procedures and services to maintain user data consistency.
 */
class UserServiceAdapter
{
    private Client $userRpc;
    private string $correlationId;

    public function __construct()
    {
        $this->userRpc = app('UserRpc');
        $this->correlationId = uniqid('auth-user-', true);
    }

    /**
     * Get user by ID
     *
     * @param int $userId User ID to retrieve
     * @return array|null User data or null on failure
     */
    public function getUser(int $userId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'user_id' => $userId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'auth-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('UserServiceAdapter: Getting user by ID', [
                'user_id' => $userId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->userRpc->call('user.getById', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('UserServiceAdapter: User retrieved successfully', [
                    'user_id' => $userId,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('UserServiceAdapter: User retrieval failed', [
                'user_id' => $userId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('UserServiceAdapter: User retrieval error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Create activity log entry
     *
     * @param array $activityData Activity data to log
     * @return array|null Activity creation result or null on failure
     */
    public function createActivity(array $activityData): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($activityData, [
                'correlation_id' => $this->correlationId,
                'created_by' => 'auth-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('UserServiceAdapter: Creating activity', [
                'activity_type' => $activityData['type'] ?? 'unknown',
                'user_id' => $activityData['user_id'] ?? null,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->userRpc->call('user.createActivity', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('UserServiceAdapter: Activity created successfully', [
                    'activity_id' => $response['data']['id'] ?? null,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('UserServiceAdapter: Activity creation failed', [
                'activity_data' => $activityData,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('UserServiceAdapter: Activity creation error', [
                'activity_data' => $activityData,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get user activities with filtering
     *
     * @param int $userId User ID to get activities for
     * @param array $filters Optional filters for activities
     * @return array|null Activities data or null on failure
     */
    public function getUserActivities(int $userId, array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'user_id' => $userId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'auth-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('UserServiceAdapter: Getting user activities', [
                'user_id' => $userId,
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->userRpc->call('user.getUserActivities', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('UserServiceAdapter: Activities retrieved successfully', [
                    'user_id' => $userId,
                    'activity_count' => count($response['data']['activities'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('UserServiceAdapter: Activities retrieval failed', [
                'user_id' => $userId,
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('UserServiceAdapter: Activities retrieval error', [
                'user_id' => $userId,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Check user service health
     *
     * @return array|null Service health status or null on failure
     */
    public function getServiceInfo(): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'auth-service',
                'timestamp' => now()->toISOString()
            ];

            $response = $this->userRpc->call('user.getServiceInfo', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response) {
                Log::info('UserServiceAdapter: Service info retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response;
            }

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('UserServiceAdapter: Service info error', [
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }
}
