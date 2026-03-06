<?php

namespace App\Services\Contracts;

/**
 * Enhanced Order Service Contract
 * 
 * Extends the base order service with enhanced functionality for RPC operations
 * Provides array-based responses suitable for JSON-RPC communication
 */
interface EnhancedOrderServiceInterface extends OrderServiceInterface
{
    /**
     * Create order from winning bid with enhanced validation and data
     * Returns array response suitable for RPC
     */
    public function createOrderFromBidWithData(int $bidId, array $orderData = []): array;

    /**
     * Update order status with enhanced data and validation
     * Returns array response suitable for RPC
     */
    public function updateOrderStatusWithData(int $orderId, string $newStatus, array $statusData = []): array;

    /**
     * Cancel order with enhanced data and validation
     * Returns array response suitable for RPC
     */
    public function cancelOrderWithData(int $orderId, string $reason, int $cancelledBy): array;

    /**
     * Get order with enhanced data for RPC response
     */
    public function getOrderWithData(int $orderId): array;

    /**
     * Get orders with enhanced filtering and pagination
     */
    public function getOrdersWithData(array $filters = [], array $pagination = []): array;

    /**
     * Validate bid for order creation
     */
    public function validateBidForOrder($bid): array;

    /**
     * Calculate order totals with enhanced breakdown
     */
    public function calculateOrderTotals($bid, array $orderData = []): array;
}
