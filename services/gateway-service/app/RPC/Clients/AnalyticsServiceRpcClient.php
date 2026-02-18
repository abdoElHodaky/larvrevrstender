<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Analytics Service
 * 
 * Provides RPC-based communication with the analytics service for
 * data collection, reporting, and analytics operations.
 */
class AnalyticsServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('analytics-service', [
            'timeout' => 45, // Longer timeout for analytics operations
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Track event
     *
     * @param array $eventData Event tracking data
     * @return array RPC response
     */
    public function trackEvent(array $eventData): array
    {
        return $this->call('analytics.trackEvent', $eventData);
    }
    
    /**
     * Track multiple events in batch
     *
     * @param array $events Array of event data
     * @return array RPC response
     */
    public function trackBatchEvents(array $events): array
    {
        return $this->call('analytics.trackBatchEvents', [
            'events' => $events,
        ]);
    }
    
    /**
     * Get auction analytics
     *
     * @param int $auctionId Auction ID
     * @param array $metrics Metrics to retrieve
     * @param array $filters Optional filters
     * @return array RPC response with auction analytics
     */
    public function getAuctionAnalytics(int $auctionId, array $metrics = [], array $filters = []): array
    {
        return $this->call('analytics.getAuctionAnalytics', [
            'auction_id' => $auctionId,
            'metrics' => $metrics,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get user analytics
     *
     * @param int $userId User ID
     * @param array $metrics Metrics to retrieve
     * @param array $filters Optional filters
     * @return array RPC response with user analytics
     */
    public function getUserAnalytics(int $userId, array $metrics = [], array $filters = []): array
    {
        return $this->call('analytics.getUserAnalytics', [
            'user_id' => $userId,
            'metrics' => $metrics,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get bidding analytics
     *
     * @param array $filters Filters (auction_id, user_id, date_range, etc.)
     * @param array $metrics Metrics to retrieve
     * @return array RPC response with bidding analytics
     */
    public function getBiddingAnalytics(array $filters = [], array $metrics = []): array
    {
        return $this->call('analytics.getBiddingAnalytics', [
            'filters' => $filters,
            'metrics' => $metrics,
        ]);
    }
    
    /**
     * Get payment analytics
     *
     * @param array $filters Filters (date_range, status, etc.)
     * @param array $metrics Metrics to retrieve
     * @return array RPC response with payment analytics
     */
    public function getPaymentAnalytics(array $filters = [], array $metrics = []): array
    {
        return $this->call('analytics.getPaymentAnalytics', [
            'filters' => $filters,
            'metrics' => $metrics,
        ]);
    }
    
    /**
     * Get platform analytics
     *
     * @param array $filters Optional filters
     * @param array $metrics Metrics to retrieve
     * @return array RPC response with platform analytics
     */
    public function getPlatformAnalytics(array $filters = [], array $metrics = []): array
    {
        return $this->call('analytics.getPlatformAnalytics', [
            'filters' => $filters,
            'metrics' => $metrics,
        ]);
    }
    
    /**
     * Generate analytics report
     *
     * @param string $reportType Report type
     * @param array $parameters Report parameters
     * @return array RPC response with report data
     */
    public function generateReport(string $reportType, array $parameters = []): array
    {
        return $this->call('analytics.generateReport', [
            'report_type' => $reportType,
            'parameters' => $parameters,
        ]);
    }
    
    /**
     * Get dashboard data
     *
     * @param string $dashboardType Dashboard type
     * @param array $filters Optional filters
     * @return array RPC response with dashboard data
     */
    public function getDashboardData(string $dashboardType, array $filters = []): array
    {
        return $this->call('analytics.getDashboardData', [
            'dashboard_type' => $dashboardType,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get real-time metrics
     *
     * @param array $metrics Metrics to retrieve
     * @return array RPC response with real-time metrics
     */
    public function getRealTimeMetrics(array $metrics = []): array
    {
        return $this->call('analytics.getRealTimeMetrics', [
            'metrics' => $metrics,
        ]);
    }
    
    /**
     * Get conversion funnel data
     *
     * @param string $funnelType Funnel type
     * @param array $filters Optional filters
     * @return array RPC response with funnel data
     */
    public function getConversionFunnel(string $funnelType, array $filters = []): array
    {
        return $this->call('analytics.getConversionFunnel', [
            'funnel_type' => $funnelType,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get cohort analysis
     *
     * @param string $cohortType Cohort type
     * @param array $parameters Analysis parameters
     * @return array RPC response with cohort analysis
     */
    public function getCohortAnalysis(string $cohortType, array $parameters = []): array
    {
        return $this->call('analytics.getCohortAnalysis', [
            'cohort_type' => $cohortType,
            'parameters' => $parameters,
        ]);
    }
    
    /**
     * Get retention analysis
     *
     * @param array $parameters Analysis parameters
     * @return array RPC response with retention analysis
     */
    public function getRetentionAnalysis(array $parameters = []): array
    {
        return $this->call('analytics.getRetentionAnalysis', [
            'parameters' => $parameters,
        ]);
    }
    
    /**
     * Get segmentation data
     *
     * @param string $segmentType Segment type
     * @param array $criteria Segmentation criteria
     * @return array RPC response with segmentation data
     */
    public function getSegmentationData(string $segmentType, array $criteria = []): array
    {
        return $this->call('analytics.getSegmentationData', [
            'segment_type' => $segmentType,
            'criteria' => $criteria,
        ]);
    }
    
    /**
     * Get A/B test results
     *
     * @param string $testId Test ID
     * @param array $metrics Metrics to analyze
     * @return array RPC response with A/B test results
     */
    public function getABTestResults(string $testId, array $metrics = []): array
    {
        return $this->call('analytics.getABTestResults', [
            'test_id' => $testId,
            'metrics' => $metrics,
        ]);
    }
    
    /**
     * Get custom analytics query
     *
     * @param array $queryData Custom query data
     * @return array RPC response with query results
     */
    public function executeCustomQuery(array $queryData): array
    {
        return $this->call('analytics.executeCustomQuery', $queryData);
    }
    
    /**
     * Export analytics data
     *
     * @param string $exportType Export type (csv, json, excel)
     * @param array $exportData Export parameters
     * @return array RPC response with export result
     */
    public function exportData(string $exportType, array $exportData): array
    {
        return $this->call('analytics.exportData', [
            'export_type' => $exportType,
            'export_data' => $exportData,
        ]);
    }
    
    /**
     * Get available metrics
     *
     * @param string|null $category Optional metric category
     * @return array RPC response with available metrics
     */
    public function getAvailableMetrics(?string $category = null): array
    {
        $params = [];
        if ($category) {
            $params['category'] = $category;
        }
        
        return $this->call('analytics.getAvailableMetrics', $params);
    }
    
    /**
     * Batch operation: Track multiple events
     *
     * @param array $events Array of event data
     * @return array Array of RPC responses
     */
    public function batchTrackEvents(array $events): array
    {
        $calls = [];
        foreach ($events as $event) {
            $calls[] = [
                'method' => 'analytics.trackEvent',
                'params' => $event,
            ];
        }
        
        return $this->batchCall($calls);
    }
}

