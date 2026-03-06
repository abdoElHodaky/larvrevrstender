<?php

namespace App\Services;

use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Models\Bid;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shared\Core\BaseService;
use App\Services\Contracts\OrderServiceInterface;

class OrderService extends BaseService implements OrderServiceInterface
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
        if (! $bid->isAccepted()) {
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
    public function updateOrderStatus(int $orderId, string $newStatus, ?string $note = null): Order
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
    public function confirmPayment(int $orderId, string $paymentMethod, ?string $paymentReference = null): Order
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
    public function cancelOrder(int $orderId, ?string $reason = null, ?int $userId = null): Order
    {
        $order = $this->getOrder($orderId);

        // Verify user can cancel the order
        if ($userId && ! in_array($userId, [$order->customer_id, $order->merchant_id])) {
            throw new \Exception('Cannot cancel another user\'s order');
        }

        $order->cancel($reason);

        event(new OrderStatusChanged($order, $order->status, Order::STATUS_CANCELLED));

        return $order->fresh();
    }

    /**
     * Add customer rating.
     */
    public function addCustomerRating(int $orderId, int $customerId, int $rating, ?string $feedback = null): Order
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
    public function addMerchantRating(int $orderId, int $merchantId, int $rating, ?string $feedback = null): Order
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
                Order::STATUS_SHIPPED,
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
                Order::STATUS_PROCESSING,
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
                ],
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
            $query->where('order_number', 'like', '%'.$filters['order_number'].'%');
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

    // ========================================
    // ENHANCED ORDER MANAGEMENT METHODS
    // ========================================

    /**
     * Create order from winning bid with enhanced validation and data
     */
    public function createOrderFromBidWithData(int $bidId, array $orderData = []): array
    {
        try {
            DB::beginTransaction();

            $bid = Bid::with(['partRequest', 'merchant'])->findOrFail($bidId);

            // Validate bid can be converted to order
            $validation = $this->validateBidForOrder($bid);
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                ];
            }

            // Calculate order totals
            $totals = $this->calculateOrderTotals($bid, $orderData);

            // Create order
            $order = Order::create([
                'part_request_id' => $bid->part_request_id,
                'winning_bid_id' => $bidId,
                'customer_id' => $bid->partRequest->customer_id,
                'merchant_id' => $bid->merchant_id,
                'total_amount' => $totals['total_amount'],
                'part_cost' => $totals['part_cost'],
                'delivery_cost' => $totals['delivery_cost'],
                'tax_amount' => $totals['tax_amount'],
                'platform_fee' => $totals['platform_fee'],
                'currency' => $orderData['currency'] ?? 'SAR',
                'status' => Order::STATUS_PENDING_PAYMENT,
                'delivery_address' => $orderData['delivery_address'] ?? null,
                'delivery_method' => $orderData['delivery_method'] ?? 'standard',
                'estimated_delivery' => $this->calculateEstimatedDelivery($orderData['delivery_method'] ?? 'standard'),
                'payment_method' => $orderData['payment_method'] ?? null,
                'payment_due_at' => now()->addHours(24), // 24 hour payment window
                'notes' => $orderData['notes'] ?? [],
                'status_history' => [
                    [
                        'status' => Order::STATUS_PENDING_PAYMENT,
                        'timestamp' => now()->toISOString(),
                        'note' => 'Order created from winning bid',
                        'updated_by' => 'system',
                    ],
                ],
                'metadata' => array_merge([
                    'created_from' => 'bid',
                    'bid_amount' => $bid->amount,
                    'bid_delivery_time' => $bid->delivery_time,
                    'part_condition' => $bid->part_condition,
                ], $orderData['metadata'] ?? []),
            ]);

            // Create order items from bid details
            $this->createOrderItems($order, $bid);

            // Update bid status
            $bid->update(['status' => 'accepted']);

            // Update part request status
            $bid->partRequest->update(['status' => 'order_created']);

            DB::commit();

            // Clear relevant caches
            $this->clearOrderCaches($order);

            // Dispatch events
            event(new OrderCreated($order));

            Log::info('Order created from bid', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'bid_id' => $bidId,
                'customer_id' => $order->customer_id,
                'merchant_id' => $order->merchant_id,
                'total_amount' => $order->total_amount,
            ]);

            return [
                'success' => true,
                'order' => $order->load(['partRequest', 'winningBid']),
                'message' => 'Order created successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order creation failed', [
                'bid_id' => $bidId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create order: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get order with comprehensive details
     */
    public function getOrderDetails(int $orderId): array
    {
        try {
            $cacheKey = "order_details:{$orderId}";

            $order = Cache::remember($cacheKey, 1800, function () use ($orderId) {
                return Order::with([
                    'partRequest.vehicle',
                    'winningBid.merchant',
                    'orderItems',
                ])->find($orderId);
            });

            if (! $order) {
                return [
                    'success' => false,
                    'message' => 'Order not found',
                ];
            }

            // Add calculated fields
            $order->days_since_created = $order->created_at->diffInDays(now());
            $order->is_payment_overdue = $order->payment_due_at && now()->gt($order->payment_due_at) && ! $order->paid_at;
            $order->estimated_delivery_days = $order->estimated_delivery ? $order->estimated_delivery->diffInDays(now()) : null;
            $order->can_be_cancelled = $this->canOrderBeCancelled($order);
            $order->can_be_refunded = $this->canOrderBeRefunded($order);

            return [
                'success' => true,
                'order' => $order,
                'payment_status' => $this->getPaymentStatus($order),
                'delivery_status' => $this->getDeliveryStatus($order),
                'next_actions' => $this->getNextActions($order),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get order details', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve order details',
            ];
        }
    }

    /**
     * Update order status with enhanced validation and data
     */
    public function updateOrderStatusWithData(int $orderId, string $newStatus, array $statusData = []): array
    {
        try {
            DB::beginTransaction();

            $order = Order::findOrFail($orderId);
            $previousStatus = $order->status;

            // Validate status transition
            $validation = $this->validateStatusTransition($order, $newStatus);
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                ];
            }

            // Update order status
            $statusHistory = $order->status_history ?? [];
            $statusHistory[] = [
                'status' => $newStatus,
                'previous_status' => $previousStatus,
                'timestamp' => now()->toISOString(),
                'note' => $statusData['note'] ?? null,
                'updated_by' => $statusData['updated_by'] ?? 'system',
                'metadata' => $statusData['metadata'] ?? [],
            ];

            $updateData = [
                'status' => $newStatus,
                'status_history' => $statusHistory,
            ];

            // Handle status-specific updates
            switch ($newStatus) {
                case Order::STATUS_PAYMENT_CONFIRMED:
                    $updateData['paid_at'] = now();
                    $updateData['payment_reference'] = $statusData['payment_reference'] ?? null;
                    break;

                case Order::STATUS_SHIPPED:
                    $updateData['tracking_number'] = $statusData['tracking_number'] ?? null;
                    break;

                case Order::STATUS_DELIVERED:
                    $updateData['actual_delivery'] = $statusData['delivery_date'] ?? now();
                    break;

                case Order::STATUS_COMPLETED:
                    $updateData['completed_at'] = now();
                    break;

                case Order::STATUS_CANCELLED:
                    $updateData['cancelled_at'] = now();
                    $updateData['cancellation_reason'] = $statusData['cancellation_reason'] ?? null;
                    break;
            }

            $order->update($updateData);

            DB::commit();

            // Clear caches
            $this->clearOrderCaches($order);

            // Dispatch events
            event(new OrderStatusChanged($order, $previousStatus, $newStatus));

            if ($newStatus === Order::STATUS_COMPLETED) {
                event(new OrderCompleted($order));
            } elseif ($newStatus === Order::STATUS_CANCELLED) {
                event(new OrderCancelled($order));
            }

            Log::info('Order status updated', [
                'order_id' => $orderId,
                'order_number' => $order->order_number,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'updated_by' => $statusData['updated_by'] ?? 'system',
            ]);

            return [
                'success' => true,
                'order' => $order->fresh(),
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'message' => 'Order status updated successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order status update failed', [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update order status: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Search orders with advanced filtering
     */
    public function searchOrdersAdvanced(array $criteria): array
    {
        try {
            $query = Order::with(['partRequest', 'winningBid']);

            // Apply filters
            if (isset($criteria['customer_id'])) {
                $query->where('customer_id', $criteria['customer_id']);
            }

            if (isset($criteria['merchant_id'])) {
                $query->where('merchant_id', $criteria['merchant_id']);
            }

            if (isset($criteria['status'])) {
                if (is_array($criteria['status'])) {
                    $query->whereIn('status', $criteria['status']);
                } else {
                    $query->where('status', $criteria['status']);
                }
            }

            if (isset($criteria['date_from'])) {
                $query->where('created_at', '>=', $criteria['date_from']);
            }

            if (isset($criteria['date_to'])) {
                $query->where('created_at', '<=', $criteria['date_to']);
            }

            if (isset($criteria['amount_min'])) {
                $query->where('total_amount', '>=', $criteria['amount_min']);
            }

            if (isset($criteria['amount_max'])) {
                $query->where('total_amount', '<=', $criteria['amount_max']);
            }

            if (isset($criteria['payment_status'])) {
                switch ($criteria['payment_status']) {
                    case 'paid':
                        $query->whereNotNull('paid_at');
                        break;
                    case 'unpaid':
                        $query->whereNull('paid_at');
                        break;
                    case 'overdue':
                        $query->whereNull('paid_at')
                            ->where('payment_due_at', '<', now());
                        break;
                }
            }

            if (isset($criteria['delivery_method'])) {
                $query->where('delivery_method', $criteria['delivery_method']);
            }

            if (isset($criteria['order_number'])) {
                $query->where('order_number', 'like', '%'.$criteria['order_number'].'%');
            }

            // Sorting
            $sortBy = $criteria['sort_by'] ?? 'created_at';
            $sortDirection = $criteria['sort_direction'] ?? 'desc';
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $page = $criteria['page'] ?? 1;
            $perPage = min($criteria['per_page'] ?? 20, 100);

            $results = $query->paginate($perPage, ['*'], 'page', $page);

            // Add calculated fields to results
            $results->getCollection()->transform(function ($order) {
                $order->days_since_created = $order->created_at->diffInDays(now());
                $order->is_payment_overdue = $order->payment_due_at && now()->gt($order->payment_due_at) && ! $order->paid_at;

                return $order;
            });

            return [
                'success' => true,
                'orders' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
                'summary' => $this->getOrdersSummary($criteria),
            ];

        } catch (\Exception $e) {
            Log::error('Order search failed', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Search failed',
            ];
        }
    }

    /**
     * Get order analytics and statistics
     */
    public function getOrderAnalytics(array $filters = []): array
    {
        try {
            $cacheKey = 'order_analytics:'.md5(serialize($filters));

            $analytics = Cache::remember($cacheKey, 900, function () use ($filters) {
                $query = Order::query();

                // Apply date filters
                if (isset($filters['date_from'])) {
                    $query->where('created_at', '>=', $filters['date_from']);
                }

                if (isset($filters['date_to'])) {
                    $query->where('created_at', '<=', $filters['date_to']);
                }

                if (isset($filters['customer_id'])) {
                    $query->where('customer_id', $filters['customer_id']);
                }

                if (isset($filters['merchant_id'])) {
                    $query->where('merchant_id', $filters['merchant_id']);
                }

                return [
                    'total_orders' => $query->count(),
                    'total_revenue' => $query->sum('total_amount'),
                    'average_order_value' => $query->avg('total_amount'),
                    'orders_by_status' => $query->groupBy('status')
                        ->selectRaw('status, count(*) as count')
                        ->pluck('count', 'status')
                        ->toArray(),
                    'orders_by_month' => $query->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, count(*) as count')
                        ->groupBy('month')
                        ->orderBy('month')
                        ->pluck('count', 'month')
                        ->toArray(),
                    'payment_methods' => $query->whereNotNull('payment_method')
                        ->groupBy('payment_method')
                        ->selectRaw('payment_method, count(*) as count')
                        ->pluck('count', 'payment_method')
                        ->toArray(),
                    'delivery_methods' => $query->groupBy('delivery_method')
                        ->selectRaw('delivery_method, count(*) as count')
                        ->pluck('count', 'delivery_method')
                        ->toArray(),
                ];
            });

            return [
                'success' => true,
                'analytics' => $analytics,
                'generated_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Order analytics failed', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate analytics',
            ];
        }
    }

    /**
     * Cancel order with enhanced validation and data
     */
    public function cancelOrderWithData(int $orderId, string $reason, int $cancelledBy): array
    {
        try {
            DB::beginTransaction();

            $order = Order::findOrFail($orderId);

            if (! $this->canOrderBeCancelled($order)) {
                return [
                    'success' => false,
                    'message' => 'Order cannot be cancelled in current status',
                ];
            }

            $result = $this->updateOrderStatusWithData($orderId, Order::STATUS_CANCELLED, [
                'cancellation_reason' => $reason,
                'updated_by' => $cancelledBy,
                'note' => "Order cancelled: {$reason}",
            ]);

            if ($result['success']) {
                // Handle refund if payment was made
                if ($order->paid_at) {
                    $this->initiateRefund($order, 'order_cancelled');
                }

                // Update related records
                if ($order->partRequest) {
                    $order->partRequest->update(['status' => 'cancelled']);
                }
            }

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order cancellation failed', [
                'order_id' => $orderId,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to cancel order',
            ];
        }
    }

    // ========================================
    // PRIVATE HELPER METHODS
    // ========================================

    /**
     * Validate bid can be converted to order
     */
    private function validateBidForOrder(Bid $bid): array
    {
        if ($bid->status !== 'accepted') {
            return [
                'valid' => false,
                'message' => 'Bid must be accepted to create order',
            ];
        }

        if ($bid->partRequest->status !== 'bidding_closed') {
            return [
                'valid' => false,
                'message' => 'Part request bidding must be closed',
            ];
        }

        // Check if order already exists for this bid
        if (Order::where('winning_bid_id', $bid->id)->exists()) {
            return [
                'valid' => false,
                'message' => 'Order already exists for this bid',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Calculate order totals
     */
    private function calculateOrderTotals(Bid $bid, array $orderData): array
    {
        $partCost = $bid->amount;
        $deliveryCost = $bid->delivery_cost ?? 0;
        $taxRate = 0.15; // 15% VAT for Saudi Arabia

        $subtotal = $partCost + $deliveryCost;
        $taxAmount = $subtotal * $taxRate;
        $platformFeeRate = 0.05; // 5% platform fee
        $platformFee = $subtotal * $platformFeeRate;

        $totalAmount = $subtotal + $taxAmount + $platformFee;

        return [
            'part_cost' => $partCost,
            'delivery_cost' => $deliveryCost,
            'tax_amount' => $taxAmount,
            'platform_fee' => $platformFee,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Calculate estimated delivery date
     */
    private function calculateEstimatedDelivery(string $deliveryMethod): Carbon
    {
        $days = match ($deliveryMethod) {
            'express' => 1,
            'fast' => 2,
            'standard' => 5,
            'economy' => 10,
            default => 5
        };

        return now()->addDays($days);
    }

    /**
     * Create order items from bid
     */
    private function createOrderItems(Order $order, Bid $bid): void
    {
        OrderItem::create([
            'order_id' => $order->id,
            'part_name' => $bid->partRequest->part_name,
            'part_number' => $bid->partRequest->part_number,
            'quantity' => $bid->partRequest->quantity ?? 1,
            'unit_price' => $bid->amount,
            'total_price' => $bid->amount,
            'part_condition' => $bid->part_condition,
            'warranty_period' => $bid->warranty_period,
            'metadata' => [
                'part_request_id' => $bid->part_request_id,
                'bid_id' => $bid->id,
                'vehicle_make' => $bid->partRequest->vehicle->make ?? null,
                'vehicle_model' => $bid->partRequest->vehicle->model ?? null,
                'vehicle_year' => $bid->partRequest->vehicle->year ?? null,
            ],
        ]);
    }

    /**
     * Validate status transition
     */
    private function validateStatusTransition(Order $order, string $newStatus): array
    {
        $currentStatus = $order->status;

        $validTransitions = [
            Order::STATUS_PENDING_PAYMENT => [
                Order::STATUS_PAYMENT_CONFIRMED,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_PAYMENT_CONFIRMED => [
                Order::STATUS_PROCESSING,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_PROCESSING => [
                Order::STATUS_SHIPPED,
                Order::STATUS_CANCELLED,
            ],
            Order::STATUS_SHIPPED => [
                Order::STATUS_DELIVERED,
                Order::STATUS_DISPUTED,
            ],
            Order::STATUS_DELIVERED => [
                Order::STATUS_COMPLETED,
                Order::STATUS_DISPUTED,
            ],
            Order::STATUS_COMPLETED => [],
            Order::STATUS_CANCELLED => [Order::STATUS_REFUNDED],
            Order::STATUS_REFUNDED => [],
            Order::STATUS_DISPUTED => [
                Order::STATUS_COMPLETED,
                Order::STATUS_REFUNDED,
            ],
        ];

        if (! isset($validTransitions[$currentStatus]) ||
            ! in_array($newStatus, $validTransitions[$currentStatus])) {
            return [
                'valid' => false,
                'message' => "Cannot transition from {$currentStatus} to {$newStatus}",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check if order can be cancelled
     */
    private function canOrderBeCancelled(Order $order): bool
    {
        $cancellableStatuses = [
            Order::STATUS_PENDING_PAYMENT,
            Order::STATUS_PAYMENT_CONFIRMED,
            Order::STATUS_PROCESSING,
        ];

        return in_array($order->status, $cancellableStatuses);
    }

    /**
     * Check if order can be refunded
     */
    private function canOrderBeRefunded(Order $order): bool
    {
        return $order->paid_at &&
               in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_DISPUTED]);
    }

    /**
     * Get payment status
     */
    private function getPaymentStatus(Order $order): array
    {
        if ($order->paid_at) {
            return [
                'status' => 'paid',
                'paid_at' => $order->paid_at,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
            ];
        }

        if ($order->payment_due_at && now()->gt($order->payment_due_at)) {
            return [
                'status' => 'overdue',
                'due_at' => $order->payment_due_at,
                'overdue_days' => now()->diffInDays($order->payment_due_at),
            ];
        }

        return [
            'status' => 'pending',
            'due_at' => $order->payment_due_at,
            'hours_remaining' => $order->payment_due_at ? now()->diffInHours($order->payment_due_at) : null,
        ];
    }

    /**
     * Get delivery status
     */
    private function getDeliveryStatus(Order $order): array
    {
        if ($order->actual_delivery) {
            return [
                'status' => 'delivered',
                'delivered_at' => $order->actual_delivery,
                'delivery_method' => $order->delivery_method,
                'tracking_number' => $order->tracking_number,
            ];
        }

        if ($order->status === Order::STATUS_SHIPPED) {
            return [
                'status' => 'in_transit',
                'shipped_at' => $order->updated_at,
                'estimated_delivery' => $order->estimated_delivery,
                'tracking_number' => $order->tracking_number,
            ];
        }

        return [
            'status' => 'pending',
            'estimated_delivery' => $order->estimated_delivery,
            'delivery_method' => $order->delivery_method,
        ];
    }

    /**
     * Get next available actions for order
     */
    private function getNextActions(Order $order): array
    {
        $actions = [];

        switch ($order->status) {
            case Order::STATUS_PENDING_PAYMENT:
                $actions[] = 'make_payment';
                $actions[] = 'cancel_order';
                break;

            case Order::STATUS_PAYMENT_CONFIRMED:
                $actions[] = 'start_processing';
                $actions[] = 'cancel_order';
                break;

            case Order::STATUS_PROCESSING:
                $actions[] = 'mark_shipped';
                $actions[] = 'cancel_order';
                break;

            case Order::STATUS_SHIPPED:
                $actions[] = 'mark_delivered';
                $actions[] = 'track_shipment';
                break;

            case Order::STATUS_DELIVERED:
                $actions[] = 'mark_completed';
                $actions[] = 'dispute_order';
                $actions[] = 'rate_merchant';
                break;

            case Order::STATUS_COMPLETED:
                $actions[] = 'view_receipt';
                break;
        }

        return $actions;
    }

    /**
     * Get orders summary for search results
     */
    private function getOrdersSummary(array $criteria): array
    {
        $query = Order::query();

        // Apply same filters as search
        if (isset($criteria['customer_id'])) {
            $query->where('customer_id', $criteria['customer_id']);
        }

        if (isset($criteria['merchant_id'])) {
            $query->where('merchant_id', $criteria['merchant_id']);
        }

        if (isset($criteria['date_from'])) {
            $query->where('created_at', '>=', $criteria['date_from']);
        }

        if (isset($criteria['date_to'])) {
            $query->where('created_at', '<=', $criteria['date_to']);
        }

        return [
            'total_orders' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'average_amount' => $query->avg('total_amount'),
            'status_counts' => $query->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    /**
     * Initiate refund process
     */
    private function initiateRefund(Order $order, string $reason): void
    {
        // This would integrate with payment service
        Log::info('Refund initiated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $order->total_amount,
            'reason' => $reason,
        ]);
    }

    /**
     * Clear order-related caches
     */
    private function clearOrderCaches(Order $order): void
    {
        Cache::forget("order_details:{$order->id}");
        Cache::forget("customer_orders:{$order->customer_id}");
        Cache::forget("merchant_orders:{$order->merchant_id}");

        // Clear analytics cache
        Cache::tags(['order_analytics'])->flush();
    }
}
