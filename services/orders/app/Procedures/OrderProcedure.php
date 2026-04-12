<?php

namespace App\Procedures;

use App\Models\Order;
use App\Workflows\OrderSagaWorkflow;
use Exception;
use Shared\Procedures\CrossServiceProcedure;
use Shared\Procedures\Micro\SecurityProcedure;
use Shared\Procedures\Micro\ValidationProcedure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order Procedure
 *
 * Handles all order-related operations including creation from auctions
 * and integration with the existing order saga workflow.
 */
class OrderProcedure extends CrossServiceProcedure
{
    use SecurityProcedure;
    use ValidationProcedure;

    /**
     * Create order from auction completion
     */
    public function createOrderFromAuction(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
                'winning_bid_id' => ['required' => true, 'type' => 'integer'],
                'buyer_id' => ['required' => true, 'type' => 'integer'],
                'seller_id' => ['required' => true, 'type' => 'integer'],
                'amount' => ['required' => true, 'type' => 'numeric'],
                'vehicle_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            return DB::transaction(function () use ($params, $context) {
                // Create order in Draft state
                $order = Order::create([
                    'buyer_id' => $params['buyer_id'],
                    'seller_id' => $params['seller_id'],
                    'total_amount' => $params['amount'],
                    'status' => 'draft',
                    'type' => 'auction',
                    'auction_id' => $params['auction_id'],
                    'winning_bid_id' => $params['winning_bid_id'],
                    'vehicle_id' => $params['vehicle_id'],
                    'created_at' => now(),
                ]);

                // Prepare order data for saga workflow
                $orderData = [
                    'order_id' => $order->id,
                    'buyer_id' => $params['buyer_id'],
                    'seller_id' => $params['seller_id'],
                    'amount' => $params['amount'],
                    'vehicle_id' => $params['vehicle_id'],
                    'auction_id' => $params['auction_id'],
                    'winning_bid_id' => $params['winning_bid_id'],
                    'type' => 'auction',
                ];

                // Start the order saga workflow
                try {
                    $workflow = new OrderSagaWorkflow();
                    $workflowResult = $workflow->execute($orderData);

                    Log::info("Order saga workflow initiated for auction order", [
                        'order_id' => $order->id,
                        'auction_id' => $params['auction_id'],
                        'workflow_result' => $workflowResult
                    ]);

                } catch (Exception $e) {
                    Log::error("Failed to start order saga workflow", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Don't fail the order creation, but log the workflow failure
                    // The order can be processed manually or retried later
                }

                // Publish order created event
                $this->publishEvent([
                    'event_type' => 'order.created',
                    'order_id' => $order->id,
                    'auction_id' => $params['auction_id'],
                    'buyer_id' => $params['buyer_id'],
                    'seller_id' => $params['seller_id'],
                    'amount' => $params['amount'],
                    'type' => 'auction',
                ], $context);

                return $this->successResponse([
                    'order' => $order->toArray(),
                    'message' => 'Order created from auction successfully'
                ]);
            });

        } catch (Exception $e) {
            Log::error('Failed to create order from auction', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to create order from auction', $e->getMessage());
        }
    }

    /**
     * Create regular order (non-auction)
     */
    public function createOrder(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'buyer_id' => ['required' => true, 'type' => 'integer'],
                'seller_id' => ['required' => true, 'type' => 'integer'],
                'items' => ['required' => true, 'type' => 'array'],
                'total_amount' => ['required' => true, 'type' => 'numeric'],
                'shipping_address' => ['required' => true, 'type' => 'array'],
                'payment_method' => ['required' => true, 'type' => 'string'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            return DB::transaction(function () use ($params, $context) {
                $order = Order::create([
                    'buyer_id' => $params['buyer_id'],
                    'seller_id' => $params['seller_id'],
                    'total_amount' => $params['total_amount'],
                    'status' => 'draft',
                    'type' => 'regular',
                    'items' => $params['items'],
                    'shipping_address' => $params['shipping_address'],
                    'payment_method' => $params['payment_method'],
                    'notes' => $params['notes'] ?? null,
                    'created_at' => now(),
                ]);

                // Start order saga workflow
                $orderData = [
                    'order_id' => $order->id,
                    'buyer_id' => $params['buyer_id'],
                    'seller_id' => $params['seller_id'],
                    'amount' => $params['total_amount'],
                    'items' => $params['items'],
                    'shipping_address' => $params['shipping_address'],
                    'payment_method' => $params['payment_method'],
                    'type' => 'regular',
                ];

                try {
                    $workflow = new OrderSagaWorkflow();
                    $workflow->execute($orderData);
                } catch (Exception $e) {
                    Log::error("Failed to start order saga workflow", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Publish order created event
                $this->publishEvent([
                    'event_type' => 'order.created',
                    'order_id' => $order->id,
                    'buyer_id' => $params['buyer_id'],
                    'seller_id' => $params['seller_id'],
                    'amount' => $params['total_amount'],
                    'type' => 'regular',
                ], $context);

                return $this->successResponse([
                    'order' => $order->toArray(),
                    'message' => 'Order created successfully'
                ]);
            });

        } catch (Exception $e) {
            return $this->errorResponse('Failed to create order', $e->getMessage());
        }
    }

    /**
     * Get order details
     */
    public function getOrder(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'order_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $order = Order::find($params['order_id']);
            if (!$order) {
                return $this->errorResponse('Order not found');
            }

            return $this->successResponse([
                'order' => $order->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get order', $e->getMessage());
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'order_id' => ['required' => true, 'type' => 'integer'],
                'status' => ['required' => true, 'type' => 'string'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $order = Order::find($params['order_id']);
            if (!$order) {
                return $this->errorResponse('Order not found');
            }

            $order->update(['status' => $params['status']]);

            // Publish order status updated event
            $this->publishEvent([
                'event_type' => 'order.status_updated',
                'order_id' => $order->id,
                'old_status' => $order->getOriginal('status'),
                'new_status' => $params['status'],
            ], $context);

            return $this->successResponse([
                'order' => $order->fresh()->toArray(),
                'message' => 'Order status updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to update order status', $e->getMessage());
        }
    }

    /**
     * Get orders for a user
     */
    public function getUserOrders(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $orders = Order::where(function ($query) use ($params) {
                $query->where('buyer_id', $params['user_id'])
                      ->orWhere('seller_id', $params['user_id']);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($params['per_page'] ?? 20);

            return $this->successResponse([
                'orders' => $orders->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get user orders', $e->getMessage());
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'order_id' => ['required' => true, 'type' => 'integer'],
                'reason' => ['type' => 'string'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $order = Order::find($params['order_id']);
            if (!$order) {
                return $this->errorResponse('Order not found');
            }

            if (!in_array($order->status, ['draft', 'awaiting_payment', 'paid'])) {
                return $this->errorResponse('Order cannot be cancelled', 'Invalid status for cancellation');
            }

            $order->update([
                'status' => 'cancelled',
                'cancellation_reason' => $params['reason'] ?? null,
                'cancelled_at' => now(),
            ]);

            // Publish order cancelled event
            $this->publishEvent([
                'event_type' => 'order.cancelled',
                'order_id' => $order->id,
                'reason' => $params['reason'] ?? null,
            ], $context);

            return $this->successResponse([
                'order' => $order->fresh()->toArray(),
                'message' => 'Order cancelled successfully'
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to cancel order', $e->getMessage());
        }
    }
}
