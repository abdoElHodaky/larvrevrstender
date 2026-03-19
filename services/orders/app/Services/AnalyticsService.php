<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Services\Contracts\AnalyticsServiceInterface;
use App\RPC\Adapters\AnalyticsServiceAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Analytics Service
 *
 * Handles communication with the analytics service
 * for event tracking and metrics collection via RPC
 */
class AnalyticsService implements AnalyticsServiceInterface
{
    protected AnalyticsServiceAdapter $analyticsAdapter;

    public function __construct(AnalyticsServiceAdapter $analyticsAdapter)
    {
        $this->analyticsAdapter = $analyticsAdapter;
    }

    /**
     * Track order event
     */
    public function trackOrderEvent(Order $order, string $event, array $data = []): void
    {
        $this->sendEvent([
            'type' => 'order_event',
            'event' => $event,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'status' => $order->status,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track user event
     */
    public function trackUserEvent(User $user, string $event, array $data = []): void
    {
        $this->sendEvent([
            'type' => 'user_event',
            'event' => $event,
            'user_id' => $user->id,
            'user_type' => $user->user_type,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Record business metric
     */
    public function recordMetric(string $metric, float $value, array $tags = []): void
    {
        $this->sendMetric([
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get order analytics via RPC
     */
    public function getOrderAnalytics(array $filters = []): array
    {
        try {
            $result = $this->analyticsAdapter->getOrderAnalytics($filters);

            if ($result) {
                return $result;
            }

            Log::warning('Failed to get order analytics via RPC', [
                'filters' => $filters,
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Order analytics RPC error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get user behavior analytics via RPC
     */
    public function getUserBehaviorAnalytics(int $userId, array $dateRange = []): array
    {
        try {
            $result = $this->analyticsAdapter->getUserBehaviorAnalytics($userId, $dateRange);

            if ($result) {
                return $result;
            }

            return [];
        } catch (\Exception $e) {
            Log::error('User behavior analytics RPC error', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get business metrics via RPC
     */
    public function getBusinessMetrics(array $metrics, array $dateRange = []): array
    {
        try {
            $result = $this->analyticsAdapter->getBusinessMetrics($metrics, $dateRange);

            if ($result) {
                return $result;
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Business metrics RPC error', [
                'metrics' => $metrics,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Generate analytics report via RPC
     */
    public function generateReport(string $reportType, array $parameters = []): array
    {
        try {
            $result = $this->analyticsAdapter->generateReport($reportType, $parameters);

            if ($result) {
                return $result;
            }

            return ['error' => 'Failed to generate report'];
        } catch (\Exception $e) {
            Log::error('Report generation RPC error', [
                'report_type' => $reportType,
                'parameters' => $parameters,
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send event to analytics service via RPC
     */
    protected function sendEvent(array $eventData): void
    {
        try {
            $result = $this->analyticsAdapter->trackEvent($eventData);

            if (!$result) {
                Log::warning('Failed to send analytics event via RPC', [
                    'event_data' => $eventData,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Analytics event RPC error', [
                'event_data' => $eventData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send metric to analytics service via RPC
     */
    protected function sendMetric(array $metricData): void
    {
        try {
            $result = $this->analyticsAdapter->sendMetric($metricData);

            if (!$result) {
                Log::warning('Failed to send analytics metric via RPC', [
                    'metric_data' => $metricData,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Analytics metric RPC error', [
                'metric_data' => $metricData,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
