<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\OrderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class OrderProcedure extends BaseProcedure
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Create a runtime exception conditionally based on Sajya availability
     */
    private function createRuntimeException(string $message, int $code = -32603, array $data = []): \Exception
    {
        if (class_exists('Sajya\Server\Exceptions\RuntimeException')) {
            return new \Sajya\Server\Exceptions\RuntimeException($message, $code, $data);
        }

        return new \Exception($message, $code);
    }

    /**
     * Create new order
     */
    public function create(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'shipping_address' => 'required|array',
            'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer',
            'notes' => 'sometimes|string|max:1000',
        ]);

        return $this->executeWithLogging('Order@create', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for order creation
            $key = 'order_create:'.$params['user_id'];
            if (RateLimiter::tooManyAttempts($key, 20)) {
                throw new RuntimeException(
                    'Too many order creation attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            DB::beginTransaction();
            try {
                $order = $this->orderService->createOrder([
                    'user_id' => $params['user_id'],
                    'items' => $params['items'],
                    'shipping_address' => $params['shipping_address'],
                    'payment_method' => $params['payment_method'],
                    'notes' => $params['notes'] ?? null,
                ]);

                DB::commit();

                // Clear rate limiting on successful creation
                RateLimiter::clear($key);

                return [
                    'success' => true,
                    'order' => $order,
                    'created_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                DB::rollBack();

                // Increment rate limiting on failed creation
                RateLimiter::hit($key, 300); // 5 minutes

                throw new RuntimeException(
                    'Order creation failed: '.$e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get order by ID
     */
    public function getById(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer|min:1',
            'include_items' => 'sometimes|boolean',
            'include_history' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Order@getById', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'order:'.$params['order_id'].':'.
                       ($params['include_items'] ?? false ? 'with_items' : 'no_items').':'.
                       ($params['include_history'] ?? false ? 'with_history' : 'no_history');
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            try {
                $order = $this->orderService->getOrderById(
                    $params['order_id'],
                    $params['include_items'] ?? true,
                    $params['include_history'] ?? false
                );

                if (! $order) {
                    throw new RuntimeException(
                        'Order not found',
                        -32001,
                        ['order_id' => $params['order_id']]
                    );
                }

                $result = [
                    'success' => true,
                    'order' => $order,
                    'retrieved_at' => now()->toISOString(),
                ];

                // Cache for 10 minutes
                Cache::put($cacheKey, $result, 600);

                return $result;

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve order: '.$e->getMessage(),
                    -32001,
                    ['order_id' => $params['order_id']]
                );
            }
        });
    }

    /**
     * Update order status
     */
    public function updateStatus(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer|min:1',
            'status' => 'required|string|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'notes' => 'sometimes|string|max:500',
            'notify_customer' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Order@updateStatus', $params, function () use ($params) {
            DB::beginTransaction();
            try {
                $order = $this->orderService->updateOrderStatus(
                    $params['order_id'],
                    $params['status'],
                    $params['notes'] ?? null,
                    $params['notify_customer'] ?? true
                );

                DB::commit();

                // Clear cache
                Cache::forget('order:'.$params['order_id'].':*');

                return [
                    'success' => true,
                    'order' => $order,
                    'updated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                DB::rollBack();

                throw new RuntimeException(
                    'Order status update failed: '.$e->getMessage(),
                    -32002,
                    ['order_id' => $params['order_id'], 'status' => $params['status']]
                );
            }
        });
    }

    /**
     * Get user orders
     */
    public function getUserOrders(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'status' => 'sometimes|string|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:created_at,updated_at,total_amount',
            'sort_order' => 'sometimes|string|in:asc,desc',
        ]);

        return $this->executeWithLogging('Order@getUserOrders', $params, function () use ($params) {
            try {
                $results = $this->orderService->getUserOrders([
                    'user_id' => $params['user_id'],
                    'status' => $params['status'] ?? null,
                    'date_from' => $params['date_from'] ?? null,
                    'date_to' => $params['date_to'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                    'sort_by' => $params['sort_by'] ?? 'created_at',
                    'sort_order' => $params['sort_order'] ?? 'desc',
                ]);

                return [
                    'success' => true,
                    'orders' => $results['data'],
                    'pagination' => $results['pagination'],
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user orders: '.$e->getMessage(),
                    -32003,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Calculate order total
     */
    public function calculateTotal(array $params): array
    {
        $this->validate($params, [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
            'coupon_code' => 'sometimes|string|max:50',
        ]);

        return $this->executeWithLogging('Order@calculateTotal', $params, function () use ($params) {
            try {
                $calculation = $this->orderService->calculateOrderTotal([
                    'items' => $params['items'],
                    'shipping_address' => $params['shipping_address'],
                    'coupon_code' => $params['coupon_code'] ?? null,
                ]);

                return [
                    'success' => true,
                    'calculation' => $calculation,
                    'calculated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Order total calculation failed: '.$e->getMessage(),
                    -32004,
                    ['items_count' => count($params['items'])]
                );
            }
        });
    }

    /**
     * Cancel order
     */
    public function cancel(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
            'refund_amount' => 'sometimes|numeric|min:0',
        ]);

        return $this->executeWithLogging('Order@cancel', $params, function () use ($params) {
            DB::beginTransaction();
            try {
                $result = $this->orderService->cancelOrder(
                    $params['order_id'],
                    $params['reason'],
                    $params['refund_amount'] ?? null
                );

                DB::commit();

                // Clear cache
                Cache::forget('order:'.$params['order_id'].':*');

                return [
                    'success' => true,
                    'order' => $result['order'],
                    'refund' => $result['refund'] ?? null,
                    'cancelled_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                DB::rollBack();

                throw new RuntimeException(
                    'Order cancellation failed: '.$e->getMessage(),
                    -32005,
                    ['order_id' => $params['order_id']]
                );
            }
        });
    }

    /**
     * Get order statistics
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:today,week,month,quarter,year',
            'user_id' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
        ]);

        return $this->executeWithLogging('Order@getStatistics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $userId = $params['user_id'] ?? null;
            $status = $params['status'] ?? null;

            // Check cache first
            $cacheKey = 'order_stats:'.$period.':'.($userId ?? 'all').':'.($status ?? 'all');
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            try {
                $statistics = $this->orderService->getOrderStatistics($period, $userId, $status);

                $result = [
                    'success' => true,
                    'statistics' => $statistics,
                    'period' => $period,
                    'filters' => [
                        'user_id' => $userId,
                        'status' => $status,
                    ],
                    'generated_at' => now()->toISOString(),
                ];

                // Cache for 30 minutes
                Cache::put($cacheKey, $result, 1800);

                return $result;

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve order statistics: '.$e->getMessage(),
                    -32001,
                    ['period' => $period]
                );
            }
        });
    }

    /**
     * Process order payment
     */
    public function processPayment(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer',
            'payment_details' => 'required|array',
        ]);

        return $this->executeWithLogging('Order@processPayment', $this->sanitizeForLogging($params), function () use ($params) {
            DB::beginTransaction();
            try {
                $result = $this->orderService->processOrderPayment(
                    $params['order_id'],
                    $params['payment_method'],
                    $params['payment_details']
                );

                DB::commit();

                // Clear cache
                Cache::forget('order:'.$params['order_id'].':*');

                return [
                    'success' => true,
                    'payment' => $result['payment'],
                    'order' => $result['order'],
                    'processed_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                DB::rollBack();

                throw new RuntimeException(
                    'Payment processing failed: '.$e->getMessage(),
                    -32006,
                    ['order_id' => $params['order_id'], 'payment_method' => $params['payment_method']]
                );
            }
        });
    }

    /**
     * Add order tracking
     */
    public function addTracking(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer|min:1',
            'tracking_number' => 'required|string|max:100',
            'carrier' => 'required|string|max:100',
            'tracking_url' => 'sometimes|url',
        ]);

        return $this->executeWithLogging('Order@addTracking', $params, function () use ($params) {
            try {
                $result = $this->orderService->addOrderTracking(
                    $params['order_id'],
                    $params['tracking_number'],
                    $params['carrier'],
                    $params['tracking_url'] ?? null
                );

                // Clear cache
                Cache::forget('order:'.$params['order_id'].':*');

                return [
                    'success' => true,
                    'tracking' => $result,
                    'added_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to add order tracking: '.$e->getMessage(),
                    -32007,
                    ['order_id' => $params['order_id']]
                );
            }
        });
    }

    // ========================================
    // ENHANCED ORDER MANAGEMENT METHODS
    // ========================================

    /**
     * Create order from winning bid
     */
    public function createOrderFromBid(array $params): array
    {
        $this->validate($params, [
            'bid_id' => 'required|integer',
            'order_data' => 'sometimes|array',
            'order_data.currency' => 'sometimes|string|in:SAR,USD,EUR',
            'order_data.delivery_method' => 'sometimes|string|in:express,fast,standard,economy',
            'order_data.delivery_address' => 'sometimes|array',
            'order_data.payment_method' => 'sometimes|string',
            'order_data.notes' => 'sometimes|array',
            'order_data.metadata' => 'sometimes|array',
        ]);

        return $this->executeWithLogging('Order@createOrderFromBid', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->createOrderFromBid(
                $params['bid_id'],
                $params['order_data'] ?? []
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32001,
                    ['bid_id' => $params['bid_id']]
                );
            }

            return [
                'success' => true,
                'order' => $result['order'],
                'message' => $result['message'],
                'created_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get order details with enhanced information
     */
    public function getOrderDetails(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('Order@getOrderDetails', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->getOrderDetails($params['order_id']);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32002,
                    ['order_id' => $params['order_id']]
                );
            }

            return [
                'success' => true,
                'order' => $result['order'],
                'payment_status' => $result['payment_status'],
                'delivery_status' => $result['delivery_status'],
                'next_actions' => $result['next_actions'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Update order status with enhanced validation
     */
    public function updateOrderStatus(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer',
            'new_status' => 'required|string|in:pending_payment,payment_confirmed,processing,shipped,delivered,completed,cancelled,refunded,disputed',
            'status_data' => 'sometimes|array',
            'status_data.note' => 'sometimes|string|max:500',
            'status_data.updated_by' => 'sometimes|integer',
            'status_data.payment_reference' => 'sometimes|string|max:100',
            'status_data.tracking_number' => 'sometimes|string|max:100',
            'status_data.delivery_date' => 'sometimes|date',
            'status_data.cancellation_reason' => 'sometimes|string|max:500',
            'status_data.metadata' => 'sometimes|array',
        ]);

        return $this->executeWithLogging('Order@updateOrderStatus', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->updateOrderStatus(
                $params['order_id'],
                $params['new_status'],
                $params['status_data'] ?? []
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32003,
                    ['order_id' => $params['order_id'], 'new_status' => $params['new_status']]
                );
            }

            return [
                'success' => true,
                'order' => $result['order'],
                'previous_status' => $result['previous_status'],
                'new_status' => $result['new_status'],
                'message' => $result['message'],
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Search orders with advanced filtering
     */
    public function searchOrders(array $params): array
    {
        $this->validate($params, [
            'criteria' => 'sometimes|array',
            'criteria.customer_id' => 'sometimes|integer',
            'criteria.merchant_id' => 'sometimes|integer',
            'criteria.status' => 'sometimes|string|in:pending_payment,payment_confirmed,processing,shipped,delivered,completed,cancelled,refunded,disputed',
            'criteria.date_from' => 'sometimes|date',
            'criteria.date_to' => 'sometimes|date',
            'criteria.amount_min' => 'sometimes|numeric|min:0',
            'criteria.amount_max' => 'sometimes|numeric|min:0',
            'criteria.payment_status' => 'sometimes|string|in:paid,unpaid,overdue',
            'criteria.delivery_method' => 'sometimes|string|in:express,fast,standard,economy',
            'criteria.order_number' => 'sometimes|string|max:50',
            'criteria.sort_by' => 'sometimes|string|in:created_at,total_amount,status,order_number',
            'criteria.sort_direction' => 'sometimes|string|in:asc,desc',
            'criteria.page' => 'sometimes|integer|min:1',
            'criteria.per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('Order@searchOrders', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for search operations
            $key = 'order_search:'.request()->ip();
            if (RateLimiter::tooManyAttempts($key, 60)) {
                throw $this->createRuntimeException(
                    'Too many search requests. Please try again later.',
                    -32004,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            RateLimiter::hit($key, 60); // 1 minute window

            $result = $this->orderService->searchOrders($params['criteria'] ?? []);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32005,
                    ['criteria' => $params['criteria'] ?? []]
                );
            }

            return [
                'success' => true,
                'orders' => $result['orders'],
                'pagination' => $result['pagination'],
                'summary' => $result['summary'],
                'searched_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get order analytics and statistics
     */
    public function getOrderAnalytics(array $params): array
    {
        $this->validate($params, [
            'filters' => 'sometimes|array',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'filters.customer_id' => 'sometimes|integer',
            'filters.merchant_id' => 'sometimes|integer',
        ]);

        return $this->executeWithLogging('Order@getOrderAnalytics', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->getOrderAnalytics($params['filters'] ?? []);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32006,
                    ['filters' => $params['filters'] ?? []]
                );
            }

            return [
                'success' => true,
                'analytics' => $result['analytics'],
                'generated_at' => $result['generated_at'],
            ];
        });
    }

    /**
     * Cancel order with enhanced validation
     */
    public function cancelOrder(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer',
            'reason' => 'required|string|max:500',
            'cancelled_by' => 'required|integer',
        ]);

        return $this->executeWithLogging('Order@cancelOrder', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->cancelOrder(
                $params['order_id'],
                $params['reason'],
                $params['cancelled_by']
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32007,
                    ['order_id' => $params['order_id']]
                );
            }

            return [
                'success' => true,
                'order' => $result['order'],
                'message' => $result['message'],
                'cancelled_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get customer orders
     */
    public function getCustomerOrders(array $params): array
    {
        $this->validate($params, [
            'customer_id' => 'required|integer',
            'filters' => 'sometimes|array',
            'filters.status' => 'sometimes|string|in:pending_payment,payment_confirmed,processing,shipped,delivered,completed,cancelled,refunded,disputed',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'filters.page' => 'sometimes|integer|min:1',
            'filters.per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        return $this->executeWithLogging('Order@getCustomerOrders', $this->sanitizeForLogging($params), function () use ($params) {
            $criteria = array_merge(
                ['customer_id' => $params['customer_id']],
                $params['filters'] ?? []
            );

            $result = $this->orderService->searchOrders($criteria);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32008,
                    ['customer_id' => $params['customer_id']]
                );
            }

            return [
                'success' => true,
                'orders' => $result['orders'],
                'pagination' => $result['pagination'],
                'summary' => $result['summary'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get merchant orders
     */
    public function getMerchantOrders(array $params): array
    {
        $this->validate($params, [
            'merchant_id' => 'required|integer',
            'filters' => 'sometimes|array',
            'filters.status' => 'sometimes|string|in:pending_payment,payment_confirmed,processing,shipped,delivered,completed,cancelled,refunded,disputed',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date',
            'filters.page' => 'sometimes|integer|min:1',
            'filters.per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        return $this->executeWithLogging('Order@getMerchantOrders', $this->sanitizeForLogging($params), function () use ($params) {
            $criteria = array_merge(
                ['merchant_id' => $params['merchant_id']],
                $params['filters'] ?? []
            );

            $result = $this->orderService->searchOrders($criteria);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32009,
                    ['merchant_id' => $params['merchant_id']]
                );
            }

            return [
                'success' => true,
                'orders' => $result['orders'],
                'pagination' => $result['pagination'],
                'summary' => $result['summary'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get order by number
     */
    public function getOrderByNumber(array $params): array
    {
        $this->validate($params, [
            'order_number' => 'required|string|max:50',
        ]);

        return $this->executeWithLogging('Order@getOrderByNumber', $this->sanitizeForLogging($params), function () use ($params) {
            $criteria = ['order_number' => $params['order_number']];
            $result = $this->orderService->searchOrders($criteria);

            if (! $result['success'] || empty($result['orders'])) {
                throw $this->createRuntimeException(
                    'Order not found',
                    -32010,
                    ['order_number' => $params['order_number']]
                );
            }

            $order = $result['orders'][0];
            $detailsResult = $this->orderService->getOrderDetails($order->id);

            return [
                'success' => true,
                'order' => $detailsResult['order'],
                'payment_status' => $detailsResult['payment_status'],
                'delivery_status' => $detailsResult['delivery_status'],
                'next_actions' => $detailsResult['next_actions'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get orders requiring action
     */
    public function getOrdersRequiringAction(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
            'action_types' => 'sometimes|array',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        return $this->executeWithLogging('Order@getOrdersRequiringAction', $this->sanitizeForLogging($params), function () use ($params) {
            $criteria = [
                'page' => $params['page'] ?? 1,
                'per_page' => $params['per_page'] ?? 20,
            ];

            // Set user filter based on type
            if ($params['user_type'] === 'customer') {
                $criteria['customer_id'] = $params['user_id'];
            } else {
                $criteria['merchant_id'] = $params['user_id'];
            }

            // Filter by statuses that require action
            $actionRequiredStatuses = [
                'pending_payment',
                'payment_confirmed',
                'processing',
                'shipped',
                'delivered',
                'disputed',
            ];

            $criteria['status'] = $actionRequiredStatuses;

            $result = $this->orderService->searchOrders($criteria);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32011,
                    ['user_id' => $params['user_id'], 'user_type' => $params['user_type']]
                );
            }

            // Filter orders that actually require action
            $ordersRequiringAction = collect($result['orders'])->filter(function ($order) use ($params) {
                return match ($order->status) {
                    'pending_payment' => $params['user_type'] === 'customer',
                    'payment_confirmed', 'processing' => $params['user_type'] === 'merchant',
                    'shipped' => $params['user_type'] === 'customer',
                    'delivered' => true, // Both can rate/complete
                    'disputed' => true, // Both can respond
                    default => false
                };
            })->values();

            return [
                'success' => true,
                'orders' => $ordersRequiringAction,
                'total_requiring_action' => $ordersRequiringAction->count(),
                'pagination' => $result['pagination'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get order status history
     */
    public function getOrderStatusHistory(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('Order@getOrderStatusHistory', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->getOrderDetails($params['order_id']);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32012,
                    ['order_id' => $params['order_id']]
                );
            }

            $order = $result['order'];
            $statusHistory = $order->status_history ?? [];

            // Sort by timestamp
            usort($statusHistory, function ($a, $b) {
                return strtotime($a['timestamp']) - strtotime($b['timestamp']);
            });

            return [
                'success' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_status' => $order->status,
                'status_history' => $statusHistory,
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }
}
