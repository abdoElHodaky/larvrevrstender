<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AnalyticsServiceAdapter for Order Service
 * 
 * Provides HTTP-like interface for RPC calls to the analytics service.
 * Order service needs analytics operations for event tracking and metrics collection.
 */
class AnalyticsServiceAdapter
{
    private $analyticsRpc;

    public function __construct()
    {
        $this->analyticsRpc = app('AnalyticsRpc');
    }

    /**
     * Track event
     */
    public function trackEvent(array $eventData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('trackEvent', ['event_data' => $eventData], $correlationId);
            
            $response = $this->analyticsRpc->call('analytics.trackEvent', [
                'event_data' => $eventData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('trackEvent', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('trackEvent', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Send metric
     */
    public function sendMetric(array $metricData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendMetric', ['metric_data' => $metricData], $correlationId);
            
            $response = $this->analyticsRpc->call('analytics.sendMetric', [
                'metric_data' => $metricData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendMetric', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendMetric', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get analytics data
     */
    public function getAnalytics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getAnalytics', ['filters' => $filters], $correlationId);
            
            $response = $this->analyticsRpc->call('analytics.getAnalytics', [
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getAnalytics', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getAnalytics', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get order analytics
     */
    public function getOrderAnalytics(array $filters = []): ?array
    {
        return $this->getAnalytics(array_merge($filters, ['type' => 'orders']));
    }

    /**
     * Get user behavior analytics
     */
    public function getUserBehaviorAnalytics(int $userId, array $dateRange = []): ?array
    {
        $params = array_merge(['user_id' => $userId, 'type' => 'user_behavior'], $dateRange);
        return $this->getAnalytics($params);
    }

    /**
     * Get business metrics
     */
    public function getBusinessMetrics(array $metrics, array $dateRange = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $params = [
                'metrics' => $metrics,
                'date_range' => $dateRange,
            ];
            
            $this->logRpcCall('getBusinessMetrics', $params, $correlationId);
            
            $response = $this->analyticsRpc->call('analytics.getBusinessMetrics', $params);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getBusinessMetrics', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getBusinessMetrics', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Generate report
     */
    public function generateReport(string $reportType, array $parameters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('generateReport', ['report_type' => $reportType, 'parameters' => $parameters], $correlationId);
            
            $response = $this->analyticsRpc->call('analytics.generateReport', [
                'report_type' => $reportType,
                'parameters' => $parameters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('generateReport', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('generateReport', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Order AnalyticsService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'analytics-service',
            'caller' => 'order-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Order AnalyticsService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'analytics-service',
            'caller' => 'order-service'
        ]);
    }
}
