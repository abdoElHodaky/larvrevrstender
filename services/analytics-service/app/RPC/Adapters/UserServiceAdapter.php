<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Sajya\Client\Client;
use Exception;

/**
 * User Service RPC Adapter for Analytics Service
 * 
 * Provides semantic methods for interacting with user-service via RPC.
 * Used by analytics service to collect user data and activity metrics.
 */
class UserServiceAdapter
{
    private Client $userRpc;
    private string $correlationId;

    public function __construct()
    {
        $this->userRpc = app('UserRpc');
        $this->correlationId = 'analytics-user-' . bin2hex(random_bytes(16));
    }

    /**
     * Get user by ID for analytics
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
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('UserServiceAdapter: Getting user for analytics', [
                'user_id' => $userId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->userRpc->call('user.getById', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('UserServiceAdapter: User data retrieved for analytics', [
                    'user_id' => $userId,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('UserServiceAdapter: User data retrieval failed', [
                'user_id' => $userId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('UserServiceAdapter: User data retrieval error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get user activities for analytics
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
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('UserServiceAdapter: Getting user activities for analytics', [
                'user_id' => $userId,
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->userRpc->call('user.getUserActivities', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('UserServiceAdapter: User activities retrieved for analytics', [
                    'user_id' => $userId,
                    'activity_count' => count($response['data']['activities'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('UserServiceAdapter: User activities retrieval failed', [
                'user_id' => $userId,
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('UserServiceAdapter: User activities retrieval error', [
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
     * Get user metrics for analytics
     *
     * @param array $userIds Array of user IDs to get metrics for
     * @param array $metrics Array of metric types to retrieve
     * @return array|null User metrics data or null on failure
     */
    public function getUserMetrics(array $userIds, array $metrics = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'user_ids' => $userIds,
                'metrics' => $metrics,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('UserServiceAdapter: Getting user metrics for analytics', [
                'user_count' => count($userIds),
                'metrics' => $metrics,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->userRpc->call('user.getUserMetrics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('UserServiceAdapter: User metrics retrieved for analytics', [
                    'user_count' => count($userIds),
                    'metrics_count' => count($response['data'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('UserServiceAdapter: User metrics retrieval failed', [
                'user_ids' => $userIds,
                'metrics' => $metrics,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('UserServiceAdapter: User metrics retrieval error', [
                'user_ids' => $userIds,
                'metrics' => $metrics,
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
                'requested_by' => 'analytics-service',
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
