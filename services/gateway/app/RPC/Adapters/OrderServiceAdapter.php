<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * OrderServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the order service.
 * Gateway service needs order operations for request routing and data aggregation.
 */
class OrderServiceAdapter
{
    private $orderRpc;

    public function __construct()
    {
        $this->orderRpc = app('OrderRpc');
    }

    /**
     * Get order by ID
     */
    public function getOrder(int $orderId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getOrder', ['order_id' => $orderId], $correlationId);
            
            $response = $this->orderRpc->call('order.getOrder', [
                'order_id' => $orderId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getOrder', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getOrder', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get orders by customer ID
     */
    public function getCustomerOrders(int $customerId, array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getCustomerOrders', ['customer_id' => $customerId, 'filters' => $filters], $correlationId);
            
            $response = $this->orderRpc->call('order.getCustomerOrders', [
                'customer_id' => $customerId,
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getCustomerOrders', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getCustomerOrders', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Create a new order
     */
    public function createOrder(array $orderData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('createOrder', ['order_data' => $orderData], $correlationId);
            
            $response = $this->orderRpc->call('order.createOrder', [
                'order_data' => $orderData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('createOrder', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('createOrder', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(int $orderId, string $status): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('updateOrderStatus', ['order_id' => $orderId, 'status' => $status], $correlationId);
            
            $response = $this->orderRpc->call('order.updateOrderStatus', [
                'order_id' => $orderId,
                'status' => $status
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('updateOrderStatus', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('updateOrderStatus', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get order statistics
     */
    public function getOrderStats(array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getOrderStats', ['filters' => $filters], $correlationId);
            
            $response = $this->orderRpc->call('order.getOrderStats', [
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getOrderStats', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getOrderStats', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Gateway OrderService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'order-service',
            'caller' => 'gateway-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Gateway OrderService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'order-service',
            'caller' => 'gateway-service'
        ]);
    }
}
