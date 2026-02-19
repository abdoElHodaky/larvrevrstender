<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Sajya\Client\Client;
use Exception;

/**
 * Order Service RPC Adapter for Analytics Service
 * 
 * Provides semantic methods for interacting with order-service via RPC.
 * Used by analytics service to collect order data and transaction metrics.
 */
class OrderServiceAdapter
{
    private Client $orderRpc;
    private string $correlationId;

    public function __construct()
    {
        $this->orderRpc = app('OrderRpc');
        $this->correlationId = uniqid('analytics-order-', true);
    }

    /**
     * Get order by ID for analytics
     *
     * @param int $orderId Order ID to retrieve
     * @return array|null Order data or null on failure
     */
    public function getOrderById(int $orderId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'order_id' => $orderId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('OrderServiceAdapter: Getting order for analytics', [
                'order_id' => $orderId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.getById', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Order data retrieved for analytics', [
                    'order_id' => $orderId,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Order data retrieval failed', [
                'order_id' => $orderId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Order data retrieval error', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get orders by date range for analytics
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param array $filters Optional filters
     * @return array|null Orders data or null on failure
     */
    public function getOrdersByDateRange(string $startDate, string $endDate, array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('OrderServiceAdapter: Getting orders by date range for analytics', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.getByDateRange', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Orders retrieved for analytics', [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'order_count' => count($response['data']['orders'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Orders retrieval failed', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Orders retrieval error', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get order metrics for analytics
     *
     * @param array $filters Filters for metrics calculation
     * @return array|null Order metrics data or null on failure
     */
    public function getOrderMetrics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('OrderServiceAdapter: Getting order metrics for analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.getMetrics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Order metrics retrieved for analytics', [
                    'metrics_count' => count($response['data'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Order metrics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Order metrics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get order status history for analytics
     *
     * @param int $orderId Order ID to get status history for
     * @return array|null Order status history or null on failure
     */
    public function getOrderStatusHistory(int $orderId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'order_id' => $orderId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('OrderServiceAdapter: Getting order status history for analytics', [
                'order_id' => $orderId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.getStatusHistory', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Order status history retrieved for analytics', [
                    'order_id' => $orderId,
                    'history_count' => count($response['data']['history'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Order status history retrieval failed', [
                'order_id' => $orderId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Order status history retrieval error', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Check order service health
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

            $response = $this->orderRpc->call('order.getServiceInfo', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response) {
                Log::info('OrderServiceAdapter: Service info retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response;
            }

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Service info error', [
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }
}
