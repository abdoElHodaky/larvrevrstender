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
     * Create new order
     * 
     * @param array $params
     * @return array
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
            $key = 'order_create:' . $params['user_id'];
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
                    'Order creation failed: ' . $e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get order by ID
     * 
     * @param array $params
     * @return array
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
            $cacheKey = 'order:' . $params['order_id'] . ':' . 
                       ($params['include_items'] ?? false ? 'with_items' : 'no_items') . ':' .
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
                
                if (!$order) {
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
                    'Failed to retrieve order: ' . $e->getMessage(),
                    -32001,
                    ['order_id' => $params['order_id']]
                );
            }
        });
    }

    /**
     * Update order status
     * 
     * @param array $params
     * @return array
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
                Cache::forget('order:' . $params['order_id'] . ':*');
                
                return [
                    'success' => true,
                    'order' => $order,
                    'updated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                throw new RuntimeException(
                    'Order status update failed: ' . $e->getMessage(),
                    -32002,
                    ['order_id' => $params['order_id'], 'status' => $params['status']]
                );
            }
        });
    }

    /**
     * Get user orders
     * 
     * @param array $params
     * @return array
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
                    'Failed to retrieve user orders: ' . $e->getMessage(),
                    -32003,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Calculate order total
     * 
     * @param array $params
     * @return array
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
                    'Order total calculation failed: ' . $e->getMessage(),
                    -32004,
                    ['items_count' => count($params['items'])]
                );
            }
        });
    }

    /**
     * Cancel order
     * 
     * @param array $params
     * @return array
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
                Cache::forget('order:' . $params['order_id'] . ':*');
                
                return [
                    'success' => true,
                    'order' => $result['order'],
                    'refund' => $result['refund'] ?? null,
                    'cancelled_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                throw new RuntimeException(
                    'Order cancellation failed: ' . $e->getMessage(),
                    -32005,
                    ['order_id' => $params['order_id']]
                );
            }
        });
    }

    /**
     * Get order statistics
     * 
     * @param array $params
     * @return array
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
            $cacheKey = 'order_stats:' . $period . ':' . ($userId ?? 'all') . ':' . ($status ?? 'all');
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
                    'Failed to retrieve order statistics: ' . $e->getMessage(),
                    -32001,
                    ['period' => $period]
                );
            }
        });
    }

    /**
     * Process order payment
     * 
     * @param array $params
     * @return array
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
                Cache::forget('order:' . $params['order_id'] . ':*');
                
                return [
                    'success' => true,
                    'payment' => $result['payment'],
                    'order' => $result['order'],
                    'processed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                throw new RuntimeException(
                    'Payment processing failed: ' . $e->getMessage(),
                    -32006,
                    ['order_id' => $params['order_id'], 'payment_method' => $params['payment_method']]
                );
            }
        });
    }

    /**
     * Add order tracking
     * 
     * @param array $params
     * @return array
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
                Cache::forget('order:' . $params['order_id'] . ':*');
                
                return [
                    'success' => true,
                    'tracking' => $result,
                    'added_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to add order tracking: ' . $e->getMessage(),
                    -32007,
                    ['order_id' => $params['order_id']]
                );
            }
        });
    }
}
