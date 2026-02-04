<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\EnhancedOrderService;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class EnhancedOrderProcedure extends BaseProcedure
{
    public function __construct(
        private EnhancedOrderService $orderService
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
     * Create order from winning bid
     * 
     * @param array $params
     * @return array
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

            if (!$result['success']) {
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
     * Get order details
     * 
     * @param array $params
     * @return array
     */
    public function getOrderDetails(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('Order@getOrderDetails', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->getOrderDetails($params['order_id']);

            if (!$result['success']) {
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
     * Update order status
     * 
     * @param array $params
     * @return array
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

            if (!$result['success']) {
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
     * 
     * @param array $params
     * @return array
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
            $key = 'order_search:' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 60)) {
                throw $this->createRuntimeException(
                    'Too many search requests. Please try again later.',
                    -32004,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            RateLimiter::hit($key, 60); // 1 minute window

            $result = $this->orderService->searchOrders($params['criteria'] ?? []);

            if (!$result['success']) {
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
     * 
     * @param array $params
     * @return array
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

            if (!$result['success']) {
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
     * Cancel order
     * 
     * @param array $params
     * @return array
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

            if (!$result['success']) {
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
     * 
     * @param array $params
     * @return array
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

            if (!$result['success']) {
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
     * 
     * @param array $params
     * @return array
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

            if (!$result['success']) {
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
     * 
     * @param array $params
     * @return array
     */
    public function getOrderByNumber(array $params): array
    {
        $this->validate($params, [
            'order_number' => 'required|string|max:50',
        ]);

        return $this->executeWithLogging('Order@getOrderByNumber', $this->sanitizeForLogging($params), function () use ($params) {
            $criteria = ['order_number' => $params['order_number']];
            $result = $this->orderService->searchOrders($criteria);

            if (!$result['success'] || empty($result['orders'])) {
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
     * 
     * @param array $params
     * @return array
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
                'disputed'
            ];

            $criteria['status'] = $actionRequiredStatuses;

            $result = $this->orderService->searchOrders($criteria);

            if (!$result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32011,
                    ['user_id' => $params['user_id'], 'user_type' => $params['user_type']]
                );
            }

            // Filter orders that actually require action
            $ordersRequiringAction = collect($result['orders'])->filter(function ($order) use ($params) {
                switch ($order->status) {
                    case 'pending_payment':
                        return $params['user_type'] === 'customer';
                    case 'payment_confirmed':
                    case 'processing':
                        return $params['user_type'] === 'merchant';
                    case 'shipped':
                        return $params['user_type'] === 'customer';
                    case 'delivered':
                        return true; // Both can rate/complete
                    case 'disputed':
                        return true; // Both can respond
                    default:
                        return false;
                }
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
     * 
     * @param array $params
     * @return array
     */
    public function getOrderStatusHistory(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('Order@getOrderStatusHistory', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->orderService->getOrderDetails($params['order_id']);

            if (!$result['success']) {
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
