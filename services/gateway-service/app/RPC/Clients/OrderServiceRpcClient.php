<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Order Service
 * 
 * Provides RPC-based communication with the order service for
 * order management, processing, and fulfillment operations.
 */
class OrderServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('order-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Create new order
     *
     * @param array $orderData Order creation data
     * @return array RPC response with created order
     */
    public function createOrder(array $orderData): array
    {
        return $this->call('order.create', $orderData);
    }
    
    /**
     * Get order details by ID
     *
     * @param int $orderId Order ID
     * @return array RPC response with order details
     */
    public function getOrder(int $orderId): array
    {
        return $this->call('order.get', [
            'order_id' => $orderId,
        ]);
    }
    
    /**
     * Update order details
     *
     * @param int $orderId Order ID
     * @param array $updateData Data to update
     * @return array RPC response
     */
    public function updateOrder(int $orderId, array $updateData): array
    {
        return $this->call('order.update', [
            'order_id' => $orderId,
            'data' => $updateData,
        ]);
    }
    
    /**
     * Cancel order
     *
     * @param int $orderId Order ID
     * @param string|null $reason Cancellation reason
     * @return array RPC response
     */
    public function cancelOrder(int $orderId, ?string $reason = null): array
    {
        $params = ['order_id' => $orderId];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('order.cancel', $params);
    }
    
    /**
     * Get orders with filtering and pagination
     *
     * @param array $filters Filters (status, user_id, date_range, etc.)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param string $orderBy Field to order by
     * @param string $orderDirection Order direction (asc/desc)
     * @return array RPC response with paginated orders
     */
    public function getOrders(
        array $filters = [],
        int $limit = 20,
        int $offset = 0,
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array {
        return $this->call('order.list', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => $orderBy,
            'order_direction' => $orderDirection,
        ]);
    }
    
    /**
     * Get user orders
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with user orders
     */
    public function getUserOrders(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('order.getUserOrders', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Update order status
     *
     * @param int $orderId Order ID
     * @param string $status New status
     * @param string|null $reason Reason for status change
     * @return array RPC response
     */
    public function updateOrderStatus(int $orderId, string $status, ?string $reason = null): array
    {
        $params = [
            'order_id' => $orderId,
            'status' => $status,
        ];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('order.updateStatus', $params);
    }
    
    /**
     * Process order payment
     *
     * @param int $orderId Order ID
     * @param array $paymentData Payment processing data
     * @return array RPC response
     */
    public function processOrderPayment(int $orderId, array $paymentData): array
    {
        return $this->call('order.processPayment', [
            'order_id' => $orderId,
            'payment_data' => $paymentData,
        ]);
    }
    
    /**
     * Fulfill order
     *
     * @param int $orderId Order ID
     * @param array $fulfillmentData Fulfillment data
     * @return array RPC response
     */
    public function fulfillOrder(int $orderId, array $fulfillmentData = []): array
    {
        return $this->call('order.fulfill', [
            'order_id' => $orderId,
            'fulfillment_data' => $fulfillmentData,
        ]);
    }
    
    /**
     * Get order items
     *
     * @param int $orderId Order ID
     * @return array RPC response with order items
     */
    public function getOrderItems(int $orderId): array
    {
        return $this->call('order.getItems', [
            'order_id' => $orderId,
        ]);
    }
    
    /**
     * Add item to order
     *
     * @param int $orderId Order ID
     * @param array $itemData Item data
     * @return array RPC response
     */
    public function addOrderItem(int $orderId, array $itemData): array
    {
        return $this->call('order.addItem', [
            'order_id' => $orderId,
            'item_data' => $itemData,
        ]);
    }
    
    /**
     * Remove item from order
     *
     * @param int $orderId Order ID
     * @param int $itemId Item ID
     * @return array RPC response
     */
    public function removeOrderItem(int $orderId, int $itemId): array
    {
        return $this->call('order.removeItem', [
            'order_id' => $orderId,
            'item_id' => $itemId,
        ]);
    }
    
    /**
     * Update order item
     *
     * @param int $orderId Order ID
     * @param int $itemId Item ID
     * @param array $updateData Update data
     * @return array RPC response
     */
    public function updateOrderItem(int $orderId, int $itemId, array $updateData): array
    {
        return $this->call('order.updateItem', [
            'order_id' => $orderId,
            'item_id' => $itemId,
            'data' => $updateData,
        ]);
    }
    
    /**
     * Calculate order total
     *
     * @param int $orderId Order ID
     * @return array RPC response with order total
     */
    public function calculateOrderTotal(int $orderId): array
    {
        return $this->call('order.calculateTotal', [
            'order_id' => $orderId,
        ]);
    }
    
    /**
     * Apply discount to order
     *
     * @param int $orderId Order ID
     * @param array $discountData Discount data
     * @return array RPC response
     */
    public function applyDiscount(int $orderId, array $discountData): array
    {
        return $this->call('order.applyDiscount', [
            'order_id' => $orderId,
            'discount_data' => $discountData,
        ]);
    }
    
    /**
     * Get order history
     *
     * @param int $orderId Order ID
     * @return array RPC response with order history
     */
    public function getOrderHistory(int $orderId): array
    {
        return $this->call('order.getHistory', [
            'order_id' => $orderId,
        ]);
    }
    
    /**
     * Search orders by criteria
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @param int $limit Number of results
     * @param int $offset Pagination offset
     * @return array RPC response with search results
     */
    public function searchOrders(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('order.search', [
            'query' => $query,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get order statistics
     *
     * @param array $filters Optional filters
     * @return array RPC response with order statistics
     */
    public function getOrderStatistics(array $filters = []): array
    {
        return $this->call('order.getStatistics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Batch operation: Get multiple orders
     *
     * @param array $orderIds Array of order IDs
     * @return array Array of RPC responses
     */
    public function getMultipleOrders(array $orderIds): array
    {
        $calls = [];
        foreach ($orderIds as $orderId) {
            $calls[] = [
                'method' => 'order.get',
                'params' => ['order_id' => $orderId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

