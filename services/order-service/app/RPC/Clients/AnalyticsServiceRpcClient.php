<?php

namespace App\RPC\Clients;

use Shared\RPC\BaseRpcClient;

/**
 * Analytics Service RPC Client for Order Service
 *
 * Handles RPC communication with the Analytics Service for order-related
 * event tracking, metrics collection, performance monitoring, and
 * business intelligence operations.
 *
 * This client provides comprehensive analytics operations needed for
 * order processing workflows including event tracking, metrics collection,
 * and performance analysis.
 */
class AnalyticsServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('analytics-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }

    /**
     * Track order event
     *
     * @param int $orderId Order ID
     * @param string $event Event name
     * @param array $eventData Event data
     * @return array Event tracking result
     */
    public function trackOrderEvent(int $orderId, string $event, array $eventData = []): array
    {
        return $this->call('analytics.track_order_event', [
            'order_id' => $orderId,
            'event' => $event,
            'event_data' => $eventData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track user order activity
     *
     * @param int $userId User ID
     * @param int $orderId Order ID
     * @param string $activity Activity type
     * @param array $activityData Activity details
     * @return array Activity tracking result
     */
    public function trackUserOrderActivity(int $userId, int $orderId, string $activity, array $activityData = []): array
    {
        return $this->call('analytics.track_user_order_activity', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'activity' => $activity,
            'activity_data' => $activityData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record order performance metrics
     *
     * @param int $orderId Order ID
     * @param array $metrics Performance metrics
     * @return array Metrics recording result
     */
    public function recordOrderPerformanceMetrics(int $orderId, array $metrics): array
    {
        return $this->call('analytics.record_order_performance_metrics', [
            'order_id' => $orderId,
            'metrics' => $metrics,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get order analytics data
     *
     * @param int $orderId Order ID
     * @param array $filters Analytics filters
     * @return array Order analytics data
     */
    public function getOrderAnalytics(int $orderId, array $filters = []): array
    {
        return $this->call('analytics.get_order_analytics', [
            'order_id' => $orderId,
            'filters' => $filters,
        ]);
    }

    /**
     * Get order conversion metrics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Conversion metrics
     */
    public function getOrderConversionMetrics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_order_conversion_metrics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Track order funnel step
     *
     * @param int $orderId Order ID
     * @param string $step Funnel step name
     * @param array $stepData Step data
     * @return array Funnel tracking result
     */
    public function trackOrderFunnelStep(int $orderId, string $step, array $stepData = []): array
    {
        return $this->call('analytics.track_order_funnel_step', [
            'order_id' => $orderId,
            'step' => $step,
            'step_data' => $stepData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get order funnel analytics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Funnel analytics data
     */
    public function getOrderFunnelAnalytics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_order_funnel_analytics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Record order processing time
     *
     * @param int $orderId Order ID
     * @param string $stage Processing stage
     * @param float $duration Duration in seconds
     * @return array Processing time recording result
     */
    public function recordOrderProcessingTime(int $orderId, string $stage, float $duration): array
    {
        return $this->call('analytics.record_order_processing_time', [
            'order_id' => $orderId,
            'stage' => $stage,
            'duration' => $duration,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get order processing time analytics
     *
     * @param array $filters Analytics filters
     * @return array Processing time analytics
     */
    public function getOrderProcessingTimeAnalytics(array $filters = []): array
    {
        return $this->call('analytics.get_order_processing_time_analytics', [
            'filters' => $filters,
        ]);
    }

    /**
     * Track order error
     *
     * @param int $orderId Order ID
     * @param string $errorType Error type
     * @param array $errorData Error details
     * @return array Error tracking result
     */
    public function trackOrderError(int $orderId, string $errorType, array $errorData): array
    {
        return $this->call('analytics.track_order_error', [
            'order_id' => $orderId,
            'error_type' => $errorType,
            'error_data' => $errorData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get order error analytics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Error analytics data
     */
    public function getOrderErrorAnalytics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_order_error_analytics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Record order revenue metrics
     *
     * @param int $orderId Order ID
     * @param array $revenueData Revenue information
     * @return array Revenue recording result
     */
    public function recordOrderRevenue(int $orderId, array $revenueData): array
    {
        return $this->call('analytics.record_order_revenue', [
            'order_id' => $orderId,
            'revenue_data' => $revenueData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get order revenue analytics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Revenue analytics data
     */
    public function getOrderRevenueAnalytics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_order_revenue_analytics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Track order status change
     *
     * @param int $orderId Order ID
     * @param string $fromStatus Previous status
     * @param string $toStatus New status
     * @param array $changeData Status change details
     * @return array Status change tracking result
     */
    public function trackOrderStatusChange(int $orderId, string $fromStatus, string $toStatus, array $changeData = []): array
    {
        return $this->call('analytics.track_order_status_change', [
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'change_data' => $changeData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get order status analytics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Status analytics data
     */
    public function getOrderStatusAnalytics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_order_status_analytics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Generate order analytics report
     *
     * @param string $reportType Report type
     * @param array $parameters Report parameters
     * @return array Report generation result
     */
    public function generateOrderAnalyticsReport(string $reportType, array $parameters = []): array
    {
        return $this->call('analytics.generate_order_analytics_report', [
            'report_type' => $reportType,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Track order customer satisfaction
     *
     * @param int $orderId Order ID
     * @param int $rating Customer rating (1-5)
     * @param array $feedbackData Feedback details
     * @return array Satisfaction tracking result
     */
    public function trackOrderCustomerSatisfaction(int $orderId, int $rating, array $feedbackData = []): array
    {
        return $this->call('analytics.track_order_customer_satisfaction', [
            'order_id' => $orderId,
            'rating' => $rating,
            'feedback_data' => $feedbackData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get customer satisfaction analytics
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Satisfaction analytics data
     */
    public function getCustomerSatisfactionAnalytics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('analytics.get_customer_satisfaction_analytics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }

    /**
     * Track order A/B test event
     *
     * @param int $orderId Order ID
     * @param string $testName Test name
     * @param string $variant Test variant
     * @param array $testData Test data
     * @return array A/B test tracking result
     */
    public function trackOrderABTestEvent(int $orderId, string $testName, string $variant, array $testData = []): array
    {
        return $this->call('analytics.track_order_ab_test_event', [
            'order_id' => $orderId,
            'test_name' => $testName,
            'variant' => $variant,
            'test_data' => $testData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get A/B test analytics for orders
     *
     * @param string $testName Test name
     * @param array $filters Additional filters
     * @return array A/B test analytics data
     */
    public function getOrderABTestAnalytics(string $testName, array $filters = []): array
    {
        return $this->call('analytics.get_order_ab_test_analytics', [
            'test_name' => $testName,
            'filters' => $filters,
        ]);
    }

    /**
     * Track batch order events (batch operation)
     *
     * @param array $orderEvents Array of order events
     * @return array Batch event tracking results
     */
    public function trackBatchOrderEvents(array $orderEvents): array
    {
        $calls = [];
        foreach ($orderEvents as $index => $event) {
            $calls[] = [
                'method' => 'analytics.track_order_event',
                'params' => $event,
                'id' => "track_order_event_{$index}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Get order analytics dashboard data
     *
     * @param array $dateRange Date range filter
     * @param array $widgets Dashboard widgets to include
     * @return array Dashboard data
     */
    public function getOrderAnalyticsDashboard(array $dateRange = [], array $widgets = []): array
    {
        return $this->call('analytics.get_order_analytics_dashboard', [
            'date_range' => $dateRange,
            'widgets' => $widgets,
        ]);
    }
}

