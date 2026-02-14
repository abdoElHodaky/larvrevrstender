<?php

namespace Shared\Traits;

/**
 * Order Notification Trait
 * 
 * Provides order-specific notification methods with predefined templates
 * and business logic for order-related notifications. Uses RPC communication
 * to call the notification service.
 * 
 * Usage:
 * ```php
 * use Shared\Traits\OrderNotificationTrait;
 * 
 * class OrderService {
 *     use OrderNotificationTrait;
 *     
 *     public function __construct() {
 *         $this->initializeOrderNotifications();
 *     }
 *     
 *     public function createOrder($data) {
 *         $order = Order::create($data);
 *         $this->notifyOrderCreated($order->toArray(), $order->customer_email);
 *         return $order;
 *     }
 *     
 *     protected function getRpcClient(string $serviceName) {
 *         // Implement your RPC client logic here
 *         return app('rpc')->service($serviceName);
 *     }
 * }
 * ```
 */
trait OrderNotificationTrait
{
    use NotificationTrait;
    
    /**
     * Initialize order notification context
     */
    public function initializeOrderNotifications(): void
    {
        $this->setNotificationService('order');
    }
    
    /**
     * Notify customer when order is created
     *
     * @param array $orderData Order information
     * @param string $customerEmail Customer email
     * @param string|null $customerPhone Customer phone (optional)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyOrderCreated(
        array $orderData,
        string $customerEmail,
        ?string $customerPhone = null,
        string $language = 'en'
    ): bool {
        $success = true;
        
        // Send email confirmation
        $emailSuccess = $this->sendEmail([
            'template' => 'order.created',
            'recipients' => [$customerEmail],
            'data' => $orderData,
            'language' => $language,
            'subject' => 'Order Confirmation #' . ($orderData['order_number'] ?? 'N/A'),
            'priority' => 'normal',
            'tracking' => [
                'event' => 'order_created',
                'order_id' => $orderData['id'] ?? null
            ]
        ]);
        
        $success = $success && $emailSuccess;
        
        // Send SMS if phone provided
        if ($customerPhone) {
            $smsSuccess = $this->sendSms([
                'template' => 'order.created.sms',
                'recipients' => [$customerPhone],
                'data' => $orderData,
                'language' => $language,
                'provider' => 'unifonic',
                'sender_id' => 'OrderSys',
                'tracking' => [
                    'event' => 'order_created_sms',
                    'order_id' => $orderData['id'] ?? null
                ]
            ]);
            
            $success = $success && $smsSuccess;
        }
        
        return $success;
    }
    
    /**
     * Notify customer when order status changes
     *
     * @param array $orderData Order information
     * @param string $newStatus New order status
     * @param string $customerEmail Customer email
     * @param string|null $customerPhone Customer phone (optional)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyOrderStatusChanged(
        array $orderData,
        string $newStatus,
        string $customerEmail,
        ?string $customerPhone = null,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($orderData, [
            'new_status' => $newStatus,
            'status_display' => $this->getStatusDisplayName($newStatus, $language)
        ]);
        
        $channels = ['email'];
        if ($customerPhone && in_array($newStatus, ['shipped', 'delivered', 'cancelled'])) {
            $channels[] = 'sms';
        }
        
        return $this->sendMultiChannel([
            'channels' => $channels,
            'template' => 'order.status_changed',
            'recipients' => [
                ['recipient' => $customerEmail, 'channel' => 'email'],
                ['recipient' => $customerPhone, 'channel' => 'sms']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'tracking' => [
                'event' => 'order_status_changed',
                'order_id' => $orderData['id'] ?? null,
                'new_status' => $newStatus
            ]
        ]);
    }
    
    /**
     * Notify customer when order is shipped
     *
     * @param array $orderData Order information
     * @param array $shippingData Shipping information (tracking number, carrier, etc.)
     * @param string $customerEmail Customer email
     * @param string|null $customerPhone Customer phone (optional)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyOrderShipped(
        array $orderData,
        array $shippingData,
        string $customerEmail,
        ?string $customerPhone = null,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($orderData, $shippingData);
        
        $success = true;
        
        // Send detailed email with tracking info
        $emailSuccess = $this->sendEmail([
            'template' => 'order.shipped',
            'recipients' => [$customerEmail],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Your Order Has Shipped - Tracking #' . ($shippingData['tracking_number'] ?? 'N/A'),
            'priority' => 'high',
            'tracking' => [
                'event' => 'order_shipped',
                'order_id' => $orderData['id'] ?? null,
                'tracking_number' => $shippingData['tracking_number'] ?? null
            ]
        ]);
        
        $success = $success && $emailSuccess;
        
        // Send SMS notification
        if ($customerPhone) {
            $smsSuccess = $this->sendSms([
                'template' => 'order.shipped.sms',
                'recipients' => [$customerPhone],
                'data' => $templateData,
                'language' => $language,
                'provider' => 'unifonic',
                'sender_id' => 'OrderSys',
                'tracking' => [
                    'event' => 'order_shipped_sms',
                    'order_id' => $orderData['id'] ?? null
                ]
            ]);
            
            $success = $success && $smsSuccess;
        }
        
        return $success;
    }
    
    /**
     * Notify customer when order is delivered
     *
     * @param array $orderData Order information
     * @param string $customerEmail Customer email
     * @param string|null $customerPhone Customer phone (optional)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyOrderDelivered(
        array $orderData,
        string $customerEmail,
        ?string $customerPhone = null,
        string $language = 'en'
    ): bool {
        return $this->sendMultiChannel([
            'channels' => $customerPhone ? ['email', 'sms'] : ['email'],
            'template' => 'order.delivered',
            'recipients' => [
                ['recipient' => $customerEmail, 'channel' => 'email'],
                ['recipient' => $customerPhone, 'channel' => 'sms']
            ],
            'data' => $orderData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'high',
            'tracking' => [
                'event' => 'order_delivered',
                'order_id' => $orderData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Notify customer when order is cancelled
     *
     * @param array $orderData Order information
     * @param string $reason Cancellation reason
     * @param string $customerEmail Customer email
     * @param string|null $customerPhone Customer phone (optional)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyOrderCancelled(
        array $orderData,
        string $reason,
        string $customerEmail,
        ?string $customerPhone = null,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($orderData, [
            'cancellation_reason' => $reason,
            'refund_info' => $this->getRefundInfo($orderData)
        ]);
        
        return $this->sendMultiChannel([
            'channels' => $customerPhone ? ['email', 'sms'] : ['email'],
            'template' => 'order.cancelled',
            'recipients' => [
                ['recipient' => $customerEmail, 'channel' => 'email'],
                ['recipient' => $customerPhone, 'channel' => 'sms']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'order_cancelled',
                'order_id' => $orderData['id'] ?? null,
                'reason' => $reason
            ]
        ]);
    }
    
    /**
     * Send order reminder notifications
     *
     * @param array $orderData Order information
     * @param string $reminderType Type of reminder (payment_due, pickup_ready, etc.)
     * @param string $customerEmail Customer email
     * @param string|null $customerPhone Customer phone (optional)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function sendOrderReminder(
        array $orderData,
        string $reminderType,
        string $customerEmail,
        ?string $customerPhone = null,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($orderData, [
            'reminder_type' => $reminderType
        ]);
        
        return $this->sendMultiChannel([
            'channels' => $customerPhone ? ['email', 'sms'] : ['email'],
            'template' => "order.reminder.{$reminderType}",
            'recipients' => [
                ['recipient' => $customerEmail, 'channel' => 'email'],
                ['recipient' => $customerPhone, 'channel' => 'sms']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'first_success',
            'priority' => 'normal',
            'tracking' => [
                'event' => 'order_reminder',
                'order_id' => $orderData['id'] ?? null,
                'reminder_type' => $reminderType
            ]
        ]);
    }
    
    /**
     * Send bulk order notifications
     *
     * @param array $orders Array of order data
     * @param string $template Template name
     * @param string $channel Channel to use
     * @param array $options Additional options
     * @return bool Success status
     */
    public function sendBulkOrderNotifications(
        array $orders,
        string $template,
        string $channel = 'email',
        array $options = []
    ): bool {
        $recipients = [];
        
        foreach ($orders as $order) {
            $recipients[] = [
                'recipient' => $order['customer_email'] ?? null,
                'data' => $order
            ];
        }
        
        return $this->sendBulkNotification([
            'channel' => $channel,
            'template' => $template,
            'recipients' => $recipients,
            'batch_size' => $options['batch_size'] ?? 50,
            'rate_limit' => $options['rate_limit'] ?? 100,
            'language' => $options['language'] ?? 'en'
        ]);
    }
    
    /**
     * Schedule order reminder notification
     *
     * @param array $orderData Order information
     * @param string $scheduledAt When to send (datetime string)
     * @param string $reminderType Type of reminder
     * @param string $customerEmail Customer email
     * @param array $options Additional options
     * @return bool Success status
     */
    public function scheduleOrderReminder(
        array $orderData,
        string $scheduledAt,
        string $reminderType,
        string $customerEmail,
        array $options = []
    ): bool {
        $templateData = array_merge($orderData, [
            'reminder_type' => $reminderType
        ]);
        
        return $this->scheduleNotification([
            'channel' => $options['channel'] ?? 'email',
            'template' => "order.reminder.{$reminderType}",
            'recipients' => [$customerEmail],
            'data' => $templateData,
            'scheduled_at' => $scheduledAt,
            'timezone' => $options['timezone'] ?? 'UTC',
            'language' => $options['language'] ?? 'en',
            'schedule_id' => "order_reminder_{$orderData['id']}_{$reminderType}"
        ]);
    }
    
    /**
     * Get display name for order status
     *
     * @param string $status Status code
     * @param string $language Language
     * @return string Display name
     */
    private function getStatusDisplayName(string $status, string $language): string
    {
        $statusNames = [
            'en' => [
                'pending' => 'Pending',
                'confirmed' => 'Confirmed',
                'processing' => 'Processing',
                'shipped' => 'Shipped',
                'delivered' => 'Delivered',
                'cancelled' => 'Cancelled',
                'refunded' => 'Refunded'
            ],
            'ar' => [
                'pending' => 'في الانتظار',
                'confirmed' => 'مؤكد',
                'processing' => 'قيد المعالجة',
                'shipped' => 'تم الشحن',
                'delivered' => 'تم التسليم',
                'cancelled' => 'ملغي',
                'refunded' => 'مسترد'
            ]
        ];
        
        return $statusNames[$language][$status] ?? $status;
    }
    
    /**
     * Get refund information for cancelled order
     *
     * @param array $orderData Order data
     * @return array Refund information
     */
    private function getRefundInfo(array $orderData): array
    {
        return [
            'refund_amount' => $orderData['total_amount'] ?? 0,
            'refund_method' => $orderData['payment_method'] ?? 'original_method',
            'refund_timeline' => '3-5 business days'
        ];
    }
}

