<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NotificationServiceAdapter for Order Service
 * 
 * Provides HTTP-like interface for RPC calls to the notification service.
 * Order service needs notification operations for sending order-related notifications.
 */
class NotificationServiceAdapter
{
    private $notificationRpc;

    public function __construct()
    {
        $this->notificationRpc = app('NotificationRpc');
    }

    /**
     * Send notification
     */
    public function sendNotification(array $notificationData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendNotification', ['notification_data' => $notificationData], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'notification_data' => $notificationData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendNotification', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendNotification', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Send order created notification
     */
    public function sendOrderCreatedNotification(array $orderData): ?array
    {
        return $this->sendNotification([
            'type' => 'order_created',
            'user_id' => $orderData['customer_id'],
            'title' => 'Order Created Successfully',
            'message' => "Your order #{$orderData['order_number']} has been created successfully.",
            'data' => [
                'order_id' => $orderData['id'],
                'order_number' => $orderData['order_number'],
                'status' => $orderData['status'],
            ],
            'channels' => ['push', 'email'],
        ]);
    }

    /**
     * Send order published notification
     */
    public function sendOrderPublishedNotification(array $orderData): ?array
    {
        return $this->sendNotification([
            'type' => 'order_published',
            'user_id' => $orderData['customer_id'],
            'title' => 'Order Published',
            'message' => "Your order #{$orderData['order_number']} has been published and is now available for bidding.",
            'data' => [
                'order_id' => $orderData['id'],
                'order_number' => $orderData['order_number'],
                'status' => $orderData['status'],
            ],
            'channels' => ['push', 'email'],
        ]);
    }

    /**
     * Send order status update notification
     */
    public function sendOrderStatusUpdateNotification(array $orderData, string $type): ?array
    {
        $messages = [
            'order_completed' => [
                'title' => 'Order Completed',
                'message' => "Your order #{$orderData['order_number']} has been completed successfully!",
            ],
            'order_cancelled' => [
                'title' => 'Order Cancelled',
                'message' => "Your order #{$orderData['order_number']} has been cancelled.",
            ],
            'bid_accepted' => [
                'title' => 'Bid Accepted',
                'message' => "Your bid on order #{$orderData['order_number']} has been accepted!",
            ],
            'bid_rejected' => [
                'title' => 'Bid Not Selected',
                'message' => "Your bid on order #{$orderData['order_number']} was not selected.",
            ],
        ];

        $messageData = $messages[$type] ?? [
            'title' => 'Order Update',
            'message' => "Order #{$orderData['order_number']} has been updated.",
        ];

        return $this->sendNotification([
            'type' => $type,
            'user_id' => $orderData['customer_id'],
            'title' => $messageData['title'],
            'message' => $messageData['message'],
            'data' => [
                'order_id' => $orderData['id'],
                'order_number' => $orderData['order_number'],
                'status' => $orderData['status'],
            ],
            'channels' => ['push', 'email'],
        ]);
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Order NotificationService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'notification-service',
            'caller' => 'order-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Order NotificationService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'notification-service',
            'caller' => 'order-service'
        ]);
    }
}
