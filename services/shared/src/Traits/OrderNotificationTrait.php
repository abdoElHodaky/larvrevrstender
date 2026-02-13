<?php

namespace Shared\Traits;

/**
 * Order Notification Trait
 * 
 * Provides order-specific notification methods that leverage the shared NotificationProcedure.
 * This trait demonstrates how services can create domain-specific notification functionality
 * while using the unified notification infrastructure.
 * 
 * Usage:
 * ```php
 * use Shared\Procedures\Micro\NotificationProcedure;
 * use Shared\Traits\OrderNotificationTrait;
 * 
 * class OrderService {
 *     use NotificationProcedure, OrderNotificationTrait;
 *     
 *     public function createOrder($data) {
 *         $order = Order::create($data);
 *         $this->sendOrderCreatedNotification($order);
 *         return $order;
 *     }
 * }
 * ```
 */
trait OrderNotificationTrait
{
    /**
     * Send order created notification
     *
     * @param object|array $order Order object or array
     * @param array $options Additional options
     * @return array
     */
    public function sendOrderCreatedNotification($order, array $options = []): array
    {
        $orderData = is_object($order) ? $order->toArray() : $order;
        
        $channels = $options['channels'] ?? ['email', 'push'];
        $priority = $options['priority'] ?? 'normal';
        
        // Prepare recipient data
        $recipient = [
            'email' => $orderData['customer_email'] ?? null,
            'device_tokens' => $orderData['customer_device_tokens'] ?? [],
            'phone' => $orderData['customer_phone'] ?? null
        ];
        
        // Prepare notification data
        $notificationData = [
            'customer_name' => $orderData['customer_name'] ?? 'Customer',
            'order_number' => $orderData['order_number'] ?? $orderData['id'],
            'total_amount' => $orderData['total_amount'] ?? $orderData['total'],
            'status' => $orderData['status'] ?? 'created',
            'order_url' => $options['order_url'] ?? config('app.frontend_url') . "/orders/{$orderData['id']}",
            'subject' => "Order #{$orderData['order_number']} Created Successfully",
            'title' => 'Order Created',
            'body' => "Your order #{$orderData['order_number']} has been created successfully!"
        ];
        
        if (count($channels) === 1) {
            // Single channel notification
            return $this->sendChannelNotification($channels[0], $recipient, 'order.created', $notificationData, $priority);
        } else {
            // Multi-channel notification
            return $this->sendMultiChannel([
                'channels' => $channels,
                'recipient' => $recipient,
                'template' => 'order.created',
                'data' => $notificationData,
                'priority' => $priority,
                'fallback_order' => ['email', 'sms', 'push']
            ]);
        }
    }
    
    /**
     * Send order status update notification
     *
     * @param object|array $order Order object or array
     * @param string $newStatus New order status
     * @param array $options Additional options
     * @return array
     */
    public function sendOrderStatusUpdateNotification($order, string $newStatus, array $options = []): array
    {
        $orderData = is_object($order) ? $order->toArray() : $order;
        
        $channels = $options['channels'] ?? ['email', 'push'];
        $priority = $this->getStatusUpdatePriority($newStatus);
        
        // Prepare recipient data
        $recipient = [
            'email' => $orderData['customer_email'] ?? null,
            'device_tokens' => $orderData['customer_device_tokens'] ?? [],
            'phone' => $orderData['customer_phone'] ?? null
        ];
        
        // Prepare notification data
        $notificationData = [
            'customer_name' => $orderData['customer_name'] ?? 'Customer',
            'order_number' => $orderData['order_number'] ?? $orderData['id'],
            'old_status' => $orderData['status'] ?? 'unknown',
            'status' => $newStatus,
            'status_message' => $this->getStatusMessage($newStatus),
            'order_url' => $options['order_url'] ?? config('app.frontend_url') . "/orders/{$orderData['id']}",
            'subject' => "Order #{$orderData['order_number']} Status Update",
            'title' => 'Order Update',
            'body' => "Your order #{$orderData['order_number']} is now {$newStatus}"
        ];
        
        return $this->sendMultiChannel([
            'channels' => $channels,
            'recipient' => $recipient,
            'template' => 'order.status_update',
            'data' => $notificationData,
            'priority' => $priority
        ]);
    }
    
    /**
     * Send order completed notification
     *
     * @param object|array $order Order object or array
     * @param array $options Additional options
     * @return array
     */
    public function sendOrderCompletedNotification($order, array $options = []): array
    {
        $orderData = is_object($order) ? $order->toArray() : $order;
        
        $channels = $options['channels'] ?? ['email', 'push', 'sms'];
        
        // Prepare recipient data
        $recipient = [
            'email' => $orderData['customer_email'] ?? null,
            'device_tokens' => $orderData['customer_device_tokens'] ?? [],
            'phone' => $orderData['customer_phone'] ?? null
        ];
        
        // Prepare notification data
        $notificationData = [
            'customer_name' => $orderData['customer_name'] ?? 'Customer',
            'order_number' => $orderData['order_number'] ?? $orderData['id'],
            'total_amount' => $orderData['total_amount'] ?? $orderData['total'],
            'completed_at' => $orderData['completed_at'] ?? now()->format('Y-m-d H:i:s'),
            'tracking_number' => $orderData['tracking_number'] ?? null,
            'order_url' => $options['order_url'] ?? config('app.frontend_url') . "/orders/{$orderData['id']}",
            'subject' => "Order #{$orderData['order_number']} Completed",
            'title' => 'Order Completed',
            'body' => "Your order #{$orderData['order_number']} has been completed successfully!"
        ];
        
        return $this->sendMultiChannel([
            'channels' => $channels,
            'recipient' => $recipient,
            'template' => 'order.completed',
            'data' => $notificationData,
            'priority' => 'high'
        ]);
    }
    
    /**
     * Send order cancelled notification
     *
     * @param object|array $order Order object or array
     * @param string $reason Cancellation reason
     * @param array $options Additional options
     * @return array
     */
    public function sendOrderCancelledNotification($order, string $reason = '', array $options = []): array
    {
        $orderData = is_object($order) ? $order->toArray() : $order;
        
        $channels = $options['channels'] ?? ['email', 'push'];
        
        // Prepare recipient data
        $recipient = [
            'email' => $orderData['customer_email'] ?? null,
            'device_tokens' => $orderData['customer_device_tokens'] ?? [],
            'phone' => $orderData['customer_phone'] ?? null
        ];
        
        // Prepare notification data
        $notificationData = [
            'customer_name' => $orderData['customer_name'] ?? 'Customer',
            'order_number' => $orderData['order_number'] ?? $orderData['id'],
            'total_amount' => $orderData['total_amount'] ?? $orderData['total'],
            'cancelled_at' => $orderData['cancelled_at'] ?? now()->format('Y-m-d H:i:s'),
            'cancellation_reason' => $reason,
            'refund_info' => $options['refund_info'] ?? 'Refund will be processed within 3-5 business days',
            'order_url' => $options['order_url'] ?? config('app.frontend_url') . "/orders/{$orderData['id']}",
            'subject' => "Order #{$orderData['order_number']} Cancelled",
            'title' => 'Order Cancelled',
            'body' => "Your order #{$orderData['order_number']} has been cancelled"
        ];
        
        return $this->sendMultiChannel([
            'channels' => $channels,
            'recipient' => $recipient,
            'template' => 'order.cancelled',
            'data' => $notificationData,
            'priority' => 'high'
        ]);
    }
    
    /**
     * Send bulk order notifications
     *
     * @param array $orders Array of orders
     * @param string $template Template to use
     * @param string $channel Channel to send through
     * @param array $options Additional options
     * @return array
     */
    public function sendBulkOrderNotifications(array $orders, string $template, string $channel = 'email', array $options = []): array
    {
        $recipients = [];
        
        foreach ($orders as $order) {
            $orderData = is_object($order) ? $order->toArray() : $order;
            
            $recipients[] = [
                'email' => $orderData['customer_email'] ?? null,
                'phone' => $orderData['customer_phone'] ?? null,
                'order_data' => $orderData
            ];
        }
        
        return $this->sendBulkNotification([
            'recipients' => $recipients,
            'channel' => $channel,
            'template' => $template,
            'data' => $options['data'] ?? [],
            'priority' => $options['priority'] ?? 'normal',
            'batch_size' => $options['batch_size'] ?? 50
        ]);
    }
    
    /**
     * Schedule order reminder notification
     *
     * @param object|array $order Order object or array
     * @param string $scheduledAt When to send the reminder
     * @param string $reminderType Type of reminder
     * @param array $options Additional options
     * @return array
     */
    public function scheduleOrderReminderNotification($order, string $scheduledAt, string $reminderType = 'payment', array $options = []): array
    {
        $orderData = is_object($order) ? $order->toArray() : $order;
        
        // Prepare recipient data
        $recipient = [
            'email' => $orderData['customer_email'] ?? null,
            'phone' => $orderData['customer_phone'] ?? null
        ];
        
        // Prepare notification data based on reminder type
        $templates = [
            'payment' => 'order.payment_reminder',
            'shipping' => 'order.shipping_reminder',
            'review' => 'order.review_reminder'
        ];
        
        $notificationData = [
            'customer_name' => $orderData['customer_name'] ?? 'Customer',
            'order_number' => $orderData['order_number'] ?? $orderData['id'],
            'total_amount' => $orderData['total_amount'] ?? $orderData['total'],
            'reminder_type' => $reminderType,
            'order_url' => $options['order_url'] ?? config('app.frontend_url') . "/orders/{$orderData['id']}"
        ];
        
        return $this->scheduleNotification([
            'scheduled_at' => $scheduledAt,
            'channel' => $options['channel'] ?? 'email',
            'recipient' => $recipient,
            'template' => $templates[$reminderType] ?? 'order.reminder',
            'data' => $notificationData,
            'priority' => $options['priority'] ?? 'normal',
            'timezone' => $options['timezone'] ?? 'UTC'
        ]);
    }
    
    /**
     * Get priority level for status updates
     *
     * @param string $status Order status
     * @return string Priority level
     */
    private function getStatusUpdatePriority(string $status): string
    {
        $highPriorityStatuses = ['cancelled', 'failed', 'refunded', 'completed'];
        $normalPriorityStatuses = ['processing', 'shipped', 'delivered'];
        
        if (in_array($status, $highPriorityStatuses)) {
            return 'high';
        } elseif (in_array($status, $normalPriorityStatuses)) {
            return 'normal';
        }
        
        return 'low';
    }
    
    /**
     * Get human-readable status message
     *
     * @param string $status Order status
     * @return string Human-readable message
     */
    private function getStatusMessage(string $status): string
    {
        $messages = [
            'pending' => 'Your order is pending confirmation',
            'confirmed' => 'Your order has been confirmed',
            'processing' => 'Your order is being processed',
            'shipped' => 'Your order has been shipped',
            'delivered' => 'Your order has been delivered',
            'completed' => 'Your order is complete',
            'cancelled' => 'Your order has been cancelled',
            'refunded' => 'Your order has been refunded',
            'failed' => 'There was an issue with your order'
        ];
        
        return $messages[$status] ?? "Your order status has been updated to {$status}";
    }
}
