<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Services\Contracts\AnalyticsServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analytics Service
 * 
 * Handles communication with the analytics service
 * for event tracking and metrics collection
 */
class AnalyticsService implements AnalyticsServiceInterface
{
    protected string $analyticsServiceUrl;

    public function __construct(string $analyticsServiceUrl)
    {
        $this->analyticsServiceUrl = $analyticsServiceUrl;
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
            'timestamp' => now()->toISOString()
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
            'timestamp' => now()->toISOString()
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
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get order analytics
     */
    public function getOrderAnalytics(array $filters = []): array
    {
        try {
            $response = Http::timeout(15)
                ->get("{$this->analyticsServiceUrl}/api/analytics/orders", $filters);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Failed to get order analytics', [
                'filters' => $filters,
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Order analytics error', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get user behavior analytics
     */
    public function getUserBehaviorAnalytics(int $userId, array $dateRange = []): array
    {
        try {
            $params = array_merge(['user_id' => $userId], $dateRange);
            $response = Http::timeout(15)
                ->get("{$this->analyticsServiceUrl}/api/analytics/users/behavior", $params);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error('User behavior analytics error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get business metrics
     */
    public function getBusinessMetrics(array $metrics, array $dateRange = []): array
    {
        try {
            $params = [
                'metrics' => $metrics,
                'date_range' => $dateRange
            ];

            $response = Http::timeout(15)
                ->post("{$this->analyticsServiceUrl}/api/analytics/metrics", $params);

            if ($response->successful()) {
                return $response->json();
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Business metrics error', [
                'metrics' => $metrics,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Generate analytics report
     */
    public function generateReport(string $reportType, array $parameters = []): array
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->analyticsServiceUrl}/api/analytics/reports/{$reportType}", $parameters);

            if ($response->successful()) {
                return $response->json();
            }

            return ['error' => 'Failed to generate report'];
        } catch (\Exception $e) {
            Log::error('Report generation error', [
                'report_type' => $reportType,
                'parameters' => $parameters,
                'error' => $e->getMessage()
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send event to analytics service
     */
    protected function sendEvent(array $eventData): void
    {
        try {
            $response = Http::timeout(5)
                ->post("{$this->analyticsServiceUrl}/api/events", $eventData);

            if (!$response->successful()) {
                Log::warning('Failed to send analytics event', [
                    'event_data' => $eventData,
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Analytics event error', [
                'event_data' => $eventData,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send metric to analytics service
     */
    protected function sendMetric(array $metricData): void
    {
        try {
            $response = Http::timeout(5)
                ->post("{$this->analyticsServiceUrl}/api/metrics", $metricData);

            if (!$response->successful()) {
                Log::warning('Failed to send analytics metric', [
                    'metric_data' => $metricData,
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Analytics metric error', [
                'metric_data' => $metricData,
                'error' => $e->getMessage()
            ]);
        }
    }
}
