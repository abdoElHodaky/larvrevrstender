<?php

namespace App\Services\Contracts;

use App\Models\Bid;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Order Service Contract
 * 
 * Defines the interface for order management services
 */
interface OrderServiceInterface
{
    /**
     * Get order by ID
     */
    public function getOrder(int $orderId): Order;

    /**
     * Get order by order number
     */
    public function getOrderByNumber(string $orderNumber): Order;

    /**
     * Get orders for customer
     */
    public function getCustomerOrders(int $customerId, array $filters = []): Collection;

    /**
     * Get orders for merchant
     */
    public function getMerchantOrders(int $merchantId, array $filters = []): Collection;

    /**
     * Create order from accepted bid
     */
    public function createOrderFromBid(Bid $bid): Order;

    /**
     * Update order status
     */
    public function updateOrderStatus(int $orderId, string $newStatus, ?string $note = null): Order;

    /**
     * Cancel order
     */
    public function cancelOrder(int $orderId, ?string $reason = null, ?int $userId = null): Order;

    /**
     * Mark order as paid
     */
    public function markAsPaid(int $orderId, array $paymentData = []): Order;

    /**
     * Mark order as shipped
     */
    public function markAsShipped(int $orderId, array $shippingData = []): Order;

    /**
     * Mark order as delivered
     */
    public function markAsDelivered(int $orderId): Order;

    /**
     * Complete order
     */
    public function completeOrder(int $orderId): Order;

    /**
     * Add customer rating
     */
    public function addCustomerRating(int $orderId, int $customerId, int $rating, ?string $feedback = null): Order;

    /**
     * Add merchant rating
     */
    public function addMerchantRating(int $orderId, int $merchantId, int $rating, ?string $feedback = null): Order;
}
