<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Notification Service (Analytics Context)
 * 
 * Provides RPC communication with the notification service for analytics data collection
 */
class NotificationServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('notification-service', [
            'timeout' => config('rpc.timeout', 30),
            'retries' => config('rpc.retry_attempts', 3),
            'retry_delay' => config('rpc.retry_delay', 1000),
        ]);
    }

    /**
     * Get notification analytics data
     */
    public function getNotificationAnalyticsData(): array
    {
        return $this->call('notification.getAnalyticsData', [], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get notification delivery statistics
     */
    public function getDeliveryStatistics(array $filters = []): array
    {
        return $this->call('notification.getDeliveryStats', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get notification channel performance metrics
     */
    public function getChannelPerformanceMetrics(array $filters = []): array
    {
        return $this->call('notification.getChannelMetrics', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get notification failure analysis
     */
    public function getFailureAnalysis(array $filters = []): array
    {
        return $this->call('notification.getFailureAnalysis', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get MENA region notification statistics
     */
    public function getMenaRegionStats(array $filters = []): array
    {
        return $this->call('notification.getMenaStats', [
            'filters' => $filters
        ], [
            'context' => 'analytics_collection',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Health check for notification service
     */
    public function healthCheck(): array
    {
        return $this->call('notification.healthCheck', [], [
            'context' => 'health_check',
            'service' => 'analytics-service'
        ]);
    }

    /**
     * Get service information
     */
    public function getServiceInfo(): array
    {
        return $this->call('notification.getServiceInfo', [], [
            'context' => 'service_info',
            'service' => 'analytics-service'
        ]);
    }
}
