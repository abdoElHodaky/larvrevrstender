<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AnalyticsServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the analytics service.
 * Gateway service needs analytics operations for request routing and data collection.
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
     * Get dashboard metrics
     */
    public function getDashboardMetrics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getDashboardMetrics', ['filters' => $filters], $correlationId);
            
            $response = $this->analyticsRpc->call('analytics.getDashboardMetrics', [
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getDashboardMetrics', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getDashboardMetrics', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Gateway AnalyticsService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'analytics-service',
            'caller' => 'gateway-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Gateway AnalyticsService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'analytics-service',
            'caller' => 'gateway-service'
        ]);
    }
}
