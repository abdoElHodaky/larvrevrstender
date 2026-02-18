<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for User Service (Analytics Context)
 * 
 * Provides RPC-based communication with the user service for
 * collecting user data and behavior metrics for analytics purposes.
 */
class UserServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('user-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Get user details for analytics
     *
     * @param int $userId User ID
     * @return array RPC response with user details
     */
    public function getUserForAnalytics(int $userId): array
    {
        return $this->call('user.get', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get users for analytics with filtering
     *
     * @param array $filters Filters for analytics data collection
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with user data
     */
    public function getUsersForAnalytics(array $filters = [], int $limit = 500, int $offset = 0): array
    {
        return $this->call('user.list', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => 'created_at',
            'order_direction' => 'desc',
        ]);
    }
    
    /**
     * Get user activity for analytics
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with user activity data
     */
    public function getUserActivityForAnalytics(int $userId, array $filters = [], int $limit = 1000, int $offset = 0): array
    {
        return $this->call('user.getActivity', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get user registration analytics
     *
     * @param array $filters Optional filters (date_range, source, etc.)
     * @return array RPC response with registration analytics
     */
    public function getUserRegistrationAnalytics(array $filters = []): array
    {
        return $this->call('user.getRegistrationAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get user engagement metrics
     *
     * @param int $userId User ID
     * @param array $dateRange Date range for analysis
     * @return array RPC response with engagement metrics
     */
    public function getUserEngagementMetrics(int $userId, array $dateRange = []): array
    {
        return $this->call('user.getEngagementMetrics', [
            'user_id' => $userId,
            'date_range' => $dateRange,
        ]);
    }
    
    /**
     * Get user retention analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with retention analytics
     */
    public function getUserRetentionAnalytics(array $filters = []): array
    {
        return $this->call('user.getRetentionAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get user demographics analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with demographics analytics
     */
    public function getUserDemographicsAnalytics(array $filters = []): array
    {
        return $this->call('user.getDemographicsAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get user behavior patterns
     *
     * @param int $userId User ID
     * @param array $analysisOptions Analysis options
     * @return array RPC response with behavior patterns
     */
    public function getUserBehaviorPatterns(int $userId, array $analysisOptions = []): array
    {
        return $this->call('user.getBehaviorPatterns', [
            'user_id' => $userId,
            'analysis_options' => $analysisOptions,
        ]);
    }
    
    /**
     * Get user segmentation data
     *
     * @param array $segmentationCriteria Segmentation criteria
     * @return array RPC response with segmentation data
     */
    public function getUserSegmentationData(array $segmentationCriteria = []): array
    {
        return $this->call('user.getSegmentationData', [
            'segmentation_criteria' => $segmentationCriteria,
        ]);
    }
    
    /**
     * Get user lifetime value metrics
     *
     * @param int $userId User ID
     * @return array RPC response with lifetime value metrics
     */
    public function getUserLifetimeValueMetrics(int $userId): array
    {
        return $this->call('user.getLifetimeValueMetrics', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get user acquisition analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with acquisition analytics
     */
    public function getUserAcquisitionAnalytics(array $filters = []): array
    {
        return $this->call('user.getAcquisitionAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get user churn analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with churn analytics
     */
    public function getUserChurnAnalytics(array $filters = []): array
    {
        return $this->call('user.getChurnAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get users by date range for analytics
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param array $additionalFilters Additional filters
     * @return array RPC response with user data
     */
    public function getUsersByDateRange(string $startDate, string $endDate, array $additionalFilters = []): array
    {
        $filters = array_merge([
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ], $additionalFilters);
        
        return $this->call('user.list', [
            'filters' => $filters,
            'limit' => 1000, // Large limit for analytics
            'offset' => 0,
        ]);
    }
    
    /**
     * Get user preferences analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with preferences analytics
     */
    public function getUserPreferencesAnalytics(array $filters = []): array
    {
        return $this->call('user.getPreferencesAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get user session analytics
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return array RPC response with session analytics
     */
    public function getUserSessionAnalytics(int $userId, array $filters = []): array
    {
        return $this->call('user.getSessionAnalytics', [
            'user_id' => $userId,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Batch operation: Get multiple user details
     *
     * @param array $userIds Array of user IDs
     * @return array Array of RPC responses
     */
    public function getBatchUsersForAnalytics(array $userIds): array
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = [
                'method' => 'user.get',
                'params' => ['user_id' => $userId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get multiple user engagement metrics
     *
     * @param array $userIds Array of user IDs
     * @param array $dateRange Date range for analysis
     * @return array Array of RPC responses
     */
    public function getBatchUserEngagementMetrics(array $userIds, array $dateRange = []): array
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = [
                'method' => 'user.getEngagementMetrics',
                'params' => [
                    'user_id' => $userId,
                    'date_range' => $dateRange,
                ],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

