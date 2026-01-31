<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Bid;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\OrderCompleted;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderService
{
    /**
     * Get order by ID.
     */
    public function getOrder(int $orderId): Order
    {
        return Order::with(['partRequest', 'winningBid', 'customer', 'merchant'])->findOrFail($orderId);
    }

    /**
     * Get order by order number.
     */
    public function getOrderByNumber(string $orderNumber): Order
    {
        return Order::with(['partRequest', 'winningBid', 'customer', 'merchant'])
                   ->where('order_number', $orderNumber)
                   ->firstOrFail();
    }

    /**
     * Get customer orders.
     */
    public function getCustomerOrders(int $customerId, array $filters = []): Collection
    {
        $query = Order::with(['partRequest', 'winningBid', 'merchant'])
                     ->byCustomer($customerId)
                     ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        return $query->get();
    }

    /**
     * Get merchant orders.
     */
    public function getMerchantOrders(int $merchantId, array $filters = []): Collection
    {
        $query = Order::with(['partRequest', 'winningBid', 'customer'])
                     ->byMerchant($merchantId)
                     ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        return $query->get();
    }

    /**
     * Create order from accepted bid.
     */
    public function createOrderFromBid(Bid $bid): Order
    {
        if (!$bid->isAccepted()) {
            throw new \Exception('Can only create order from accepted bid');
        }
        
        $partRequest = $bid->partRequest;
        
        // Calculate order amounts
        $partCost = $bid->amount;
        $deliveryCost = $bid->delivery_cost ?? 0;
        $platformFee = round($partCost * 0.05, 2); // 5% platform fee
        $taxAmount = round(($partCost + $deliveryCost) * 0.15, 2); // 15% VAT
        $totalAmount = $partCost + $deliveryCost + $platformFee + $taxAmount;
        
        $orderData = [
            'part_request_id' => $partRequest->id,
            'winning_bid_id' => $bid->id,
            'customer_id' => $partRequest->customer_id,
            'merchant_id' => $bid->merchant_id,
            'part_cost' => $partCost,
            'delivery_cost' => $deliveryCost,
            'platform_fee' => $platformFee,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'currency' => $bid->currency,
            'delivery_address' => $partRequest->location_preferences[0] ?? [], // Use first preferred location
            'delivery_method' => $bid->delivery_options[0] ?? 'delivery',
            'payment_due_at' => now()->addDays(3), // 3 days to pay
            'estimated_delivery' => $bid->delivery_days ? now()->addDays($bid->delivery_days) : null,
        ];
        
        $order = Order::create($orderData);
        
        event(new OrderCreated($order));
        
        return $order;
    }

    /**
     * Update order status.
     */
    public function updateOrderStatus(int $orderId, string $newStatus, string $note = null): Order
    {
        $order = $this->getOrder($orderId);
        $oldStatus = $order->status;
        
        $order->updateStatus($newStatus, $note);
        
        event(new OrderStatusChanged($order, $oldStatus, $newStatus));
        
        // Trigger completion event if order is completed
        if ($newStatus === Order::STATUS_COMPLETED) {
            event(new OrderCompleted($order));
        }
        
        return $order->fresh();
    }

    /**
     * Confirm payment.
     */
    public function confirmPayment(int $orderId, string $paymentMethod, string $paymentReference = null): Order
    {
        $order = $this->getOrder($orderId);
        
        if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
            throw new \Exception('Order is not pending payment');
        }
        
        $order->confirmPayment($paymentMethod, $paymentReference);
        
        event(new OrderStatusChanged($order, Order::STATUS_PENDING_PAYMENT, Order::STATUS_PAYMENT_CONFIRMED));
        
        return $order->fresh();
    }

    /**
     * Mark order as processing.
     */
    public function markAsProcessing(int $orderId, int $merchantId): Order
    {
        $order = $this->getOrder($orderId);
        
        // Verify merchant owns the order
        if ($order->merchant_id !== $merchantId) {
            throw new \Exception('Cannot update another merchant\'s order');
        }
        
        if ($order->status !== Order::STATUS_PAYMENT_CONFIRMED) {
            throw new \Exception('Order must be payment confirmed to mark as processing');
        }
        
        return $this->updateOrderStatus($orderId, Order::STATUS_PROCESSING, 'Order is being processed');
    }

    /**
     * Mark order as shipped.
     */
    public function markAsShipped(int $orderId, int $merchantId, array $shippingData = []): Order
    {
        $order = $this->getOrder($orderId);
        
        // Verify merchant owns the order
        if ($order->merchant_id !== $merchantId) {
            throw new \Exception('Cannot update another merchant\'s order');
        }
        
        if ($order->status !== Order::STATUS_PROCESSING) {
            throw new \Exception('Order must be processing to mark as shipped');
        }
        
        $trackingNumber = $shippingData['tracking_number'] ?? null;
        $estimatedDelivery = isset($shippingData['estimated_delivery']) 
            ? new \DateTime($shippingData['estimated_delivery']) 
            : null;
        
        $order->markAsShipped($trackingNumber, $estimatedDelivery);
        
        event(new OrderStatusChanged($order, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED));
        
        return $order->fresh();
    }

    /**
     * Mark order as delivered.
     */
    public function markAsDelivered(int $orderId): Order
    {
        $order = $this->getOrder($orderId);
        
        if ($order->status !== Order::STATUS_SHIPPED) {
            throw new \Exception('Order must be shipped to mark as delivered');
        }
        
        $order->markAsDelivered();
        
        event(new OrderStatusChanged($order, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED));
        
        return $order->fresh();
    }

    /**
     * Complete order.
     */
    public function completeOrder(int $orderId): Order
    {
        $order = $this->getOrder($orderId);
        
        if ($order->status !== Order::STATUS_DELIVERED) {
            throw new \Exception('Order must be delivered to complete');
        }
        
        $order->complete();
        
        event(new OrderCompleted($order));
        
        return $order->fresh();
    }

    /**
     * Cancel order.
     */
    public function cancelOrder(int $orderId, string $reason = null, int $userId = null): Order
    {
        $order = $this->getOrder($orderId);
        
        // Verify user can cancel the order
        if ($userId && !in_array($userId, [$order->customer_id, $order->merchant_id])) {
            throw new \Exception('Cannot cancel another user\'s order');
        }
        
        $order->cancel($reason);
        
        event(new OrderStatusChanged($order, $order->status, Order::STATUS_CANCELLED));
        
        return $order->fresh();
    }

    /**
     * Add customer rating.
     */
    public function addCustomerRating(int $orderId, int $customerId, int $rating, string $feedback = null): Order
    {
        $order = $this->getOrder($orderId);
        
        // Verify customer owns the order
        if ($order->customer_id !== $customerId) {
            throw new \Exception('Cannot rate another customer\'s order');
        }
        
        $order->addCustomerRating($rating, $feedback);
        
        return $order->fresh();
    }

    /**
     * Add merchant rating.
     */
    public function addMerchantRating(int $orderId, int $merchantId, int $rating, string $feedback = null): Order
    {
        $order = $this->getOrder($orderId);
        
        // Verify merchant owns the order
        if ($order->merchant_id !== $merchantId) {
            throw new \Exception('Cannot rate another merchant\'s order');
        }
        
        $order->addMerchantRating($rating, $feedback);
        
        return $order->fresh();
    }

    /**
     * Add note to order.
     */
    public function addOrderNote(int $orderId, string $note, string $author = 'system'): Order
    {
        $order = $this->getOrder($orderId);
        $order->addNote($note, $author);
        
        return $order->fresh();
    }

    /**
     * Get order statistics.
     */
    public function getOrderStats(int $orderId): array
    {
        $order = $this->getOrder($orderId);
        
        return [
            'order_number' => $order->order_number,
            'status_display' => $order->status_display,
            'status_color' => $order->status_color,
            'is_paid' => $order->isPaid(),
            'is_payment_overdue' => $order->isPaymentOverdue(),
            'can_be_cancelled' => $order->canBeCancelled(),
            'can_be_rated' => $order->canBeRated(),
            'days_since_created' => $order->created_at->diffInDays(),
            'estimated_delivery_in_days' => $order->estimated_delivery ? $order->estimated_delivery->diffInDays() : null,
            'total_notes' => count($order->notes ?? []),
            'status_changes' => count($order->status_history ?? []),
        ];
    }

    /**
     * Get customer order statistics.
     */
    public function getCustomerOrderStats(int $customerId): array
    {
        $orders = Order::byCustomer($customerId);
        
        return [
            'total_orders' => $orders->count(),
            'pending_payment' => $orders->byStatus(Order::STATUS_PENDING_PAYMENT)->count(),
            'active_orders' => $orders->whereIn('status', [
                Order::STATUS_PAYMENT_CONFIRMED,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED
            ])->count(),
            'completed_orders' => $orders->byStatus(Order::STATUS_COMPLETED)->count(),
            'cancelled_orders' => $orders->byStatus(Order::STATUS_CANCELLED)->count(),
            'total_spent' => $orders->paid()->sum('total_amount'),
            'average_order_value' => $orders->paid()->avg('total_amount'),
            'average_rating_given' => $orders->whereNotNull('customer_rating')->avg('customer_rating'),
        ];
    }

    /**
     * Get merchant order statistics.
     */
    public function getMerchantOrderStats(int $merchantId): array
    {
        $orders = Order::byMerchant($merchantId);
        
        return [
            'total_orders' => $orders->count(),
            'pending_orders' => $orders->whereIn('status', [
                Order::STATUS_PENDING_PAYMENT,
                Order::STATUS_PAYMENT_CONFIRMED,
                Order::STATUS_PROCESSING
            ])->count(),
            'completed_orders' => $orders->byStatus(Order::STATUS_COMPLETED)->count(),
            'cancelled_orders' => $orders->byStatus(Order::STATUS_CANCELLED)->count(),
            'total_revenue' => $orders->paid()->sum('part_cost'),
            'average_order_value' => $orders->paid()->avg('part_cost'),
            'average_rating_received' => $orders->whereNotNull('merchant_rating')->avg('merchant_rating'),
            'on_time_delivery_rate' => $this->calculateOnTimeDeliveryRate($merchantId),
        ];
    }

    /**
     * Calculate on-time delivery rate for merchant.
     */
    private function calculateOnTimeDeliveryRate(int $merchantId): float
    {
        $deliveredOrders = Order::byMerchant($merchantId)
                               ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED])
                               ->whereNotNull('estimated_delivery')
                               ->whereNotNull('actual_delivery')
                               ->get();
        
        if ($deliveredOrders->isEmpty()) {
            return 0.0;
        }
        
        $onTimeDeliveries = $deliveredOrders->filter(function ($order) {
            return $order->actual_delivery <= $order->estimated_delivery;
        })->count();
        
        return round(($onTimeDeliveries / $deliveredOrders->count()) * 100, 2);
    }

    /**
     * Get overdue payment orders.
     */
    public function getOverduePaymentOrders(): Collection
    {
        return Order::overduePayment()
                   ->with(['customer', 'merchant', 'partRequest'])
                   ->orderBy('payment_due_at', 'asc')
                   ->get();
    }

    /**
     * Get orders requiring delivery update.
     */
    public function getOrdersRequiringDeliveryUpdate(): Collection
    {
        return Order::requiringDelivery()
                   ->with(['customer', 'merchant', 'partRequest'])
                   ->orderBy('estimated_delivery', 'asc')
                   ->get();
    }

    /**
     * Process automatic order status updates.
     */
    public function processAutomaticStatusUpdates(): array
    {
        $results = [
            'overdue_payments_cancelled' => 0,
            'auto_completed_orders' => 0,
        ];
        
        // Cancel orders with overdue payments (after 7 days)
        $overdueOrders = Order::overduePayment()
                             ->where('payment_due_at', '<', now()->subDays(7))
                             ->get();
        
        foreach ($overdueOrders as $order) {
            $order->cancel('Payment overdue - automatically cancelled');
            $results['overdue_payments_cancelled']++;
        }
        
        // Auto-complete delivered orders (after 3 days)
        $autoCompleteOrders = Order::byStatus(Order::STATUS_DELIVERED)
                                  ->where('actual_delivery', '<', now()->subDays(3))
                                  ->get();
        
        foreach ($autoCompleteOrders as $order) {
            $order->complete();
            $results['auto_completed_orders']++;
        }
        
        return $results;
    }

    /**
     * Generate order invoice data.
     */
    public function generateInvoiceData(int $orderId): array
    {
        $order = $this->getOrder($orderId);
        
        return [
            'order_number' => $order->order_number,
            'invoice_date' => now()->toDateString(),
            'customer' => [
                'id' => $order->customer_id,
                'name' => $order->customer->name ?? 'N/A',
                'address' => $order->delivery_address,
            ],
            'merchant' => [
                'id' => $order->merchant_id,
                'name' => $order->merchant->business_name ?? 'N/A',
                'tax_number' => $order->merchant->tax_number ?? 'N/A',
            ],
            'items' => [
                [
                    'description' => $order->partRequest->title,
                    'quantity' => 1,
                    'unit_price' => $order->part_cost,
                    'total' => $order->part_cost,
                ]
            ],
            'delivery_cost' => $order->delivery_cost,
            'platform_fee' => $order->platform_fee,
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency,
            'zatca_hash' => $order->zatca_invoice_hash,
        ];
    }

    /**
     * Search orders.
     */
    public function searchOrders(array $filters): Collection
    {
        $query = Order::with(['partRequest', 'customer', 'merchant']);
        
        if (isset($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
        }
        
        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }
        
        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }
        
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (isset($filters['amount_min'])) {
            $query->where('total_amount', '>=', $filters['amount_min']);
        }
        
        if (isset($filters['amount_max'])) {
            $query->where('total_amount', '<=', $filters['amount_max']);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }
}

