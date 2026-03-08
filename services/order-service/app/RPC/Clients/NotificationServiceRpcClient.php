<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * Notification Service RPC Client for Order Service
 *
 * Handles RPC communication with the Notification Service for order-related
 * notifications, status updates, customer communications, and multi-channel
 * notification delivery.
 *
 * This client provides comprehensive notification operations needed for
 * order processing workflows including email, SMS, push notifications,
 * and in-app messaging.
 */
class NotificationServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('notification-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }

    /**
     * Send order created notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param array $orderData Order information
     * @return array Notification sending result
     */
    public function sendOrderCreatedNotification(int $orderId, int $customerId, array $orderData): array
    {
        return $this->call('notification.send_order_created_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'order_data' => $orderData,
        ]);
    }

    /**
     * Send order status update notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param string $status New order status
     * @param array $statusData Status details
     * @return array Notification sending result
     */
    public function sendOrderStatusUpdateNotification(int $orderId, int $customerId, string $status, array $statusData = []): array
    {
        return $this->call('notification.send_order_status_update_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'status' => $status,
            'status_data' => $statusData,
        ]);
    }

    /**
     * Send order payment notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param string $paymentStatus Payment status
     * @param array $paymentData Payment details
     * @return array Notification sending result
     */
    public function sendOrderPaymentNotification(int $orderId, int $customerId, string $paymentStatus, array $paymentData): array
    {
        return $this->call('notification.send_order_payment_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'payment_status' => $paymentStatus,
            'payment_data' => $paymentData,
        ]);
    }

    /**
     * Send order shipping notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param array $shippingData Shipping information
     * @return array Notification sending result
     */
    public function sendOrderShippingNotification(int $orderId, int $customerId, array $shippingData): array
    {
        return $this->call('notification.send_order_shipping_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'shipping_data' => $shippingData,
        ]);
    }

    /**
     * Send order delivery notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param array $deliveryData Delivery information
     * @return array Notification sending result
     */
    public function sendOrderDeliveryNotification(int $orderId, int $customerId, array $deliveryData): array
    {
        return $this->call('notification.send_order_delivery_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'delivery_data' => $deliveryData,
        ]);
    }

    /**
     * Send order cancellation notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param string $cancellationReason Cancellation reason
     * @param array $refundData Refund information
     * @return array Notification sending result
     */
    public function sendOrderCancellationNotification(int $orderId, int $customerId, string $cancellationReason, array $refundData = []): array
    {
        return $this->call('notification.send_order_cancellation_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'cancellation_reason' => $cancellationReason,
            'refund_data' => $refundData,
        ]);
    }

    /**
     * Send order completion notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param array $completionData Order completion details
     * @return array Notification sending result
     */
    public function sendOrderCompletionNotification(int $orderId, int $customerId, array $completionData): array
    {
        return $this->call('notification.send_order_completion_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'completion_data' => $completionData,
        ]);
    }

    /**
     * Send order reminder notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param string $reminderType Reminder type
     * @param array $reminderData Reminder details
     * @return array Notification sending result
     */
    public function sendOrderReminderNotification(int $orderId, int $customerId, string $reminderType, array $reminderData = []): array
    {
        return $this->call('notification.send_order_reminder_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'reminder_type' => $reminderType,
            'reminder_data' => $reminderData,
        ]);
    }

    /**
     * Send custom order notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param string $template Notification template
     * @param array $templateData Template data
     * @param array $channels Notification channels
     * @return array Notification sending result
     */
    public function sendCustomOrderNotification(int $orderId, int $customerId, string $template, array $templateData, array $channels = ['email']): array
    {
        return $this->call('notification.send_custom_order_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'template' => $template,
            'template_data' => $templateData,
            'channels' => $channels,
        ]);
    }

    /**
     * Send merchant order notification
     *
     * @param int $orderId Order ID
     * @param int $merchantId Merchant ID
     * @param string $notificationType Notification type
     * @param array $notificationData Notification details
     * @return array Notification sending result
     */
    public function sendMerchantOrderNotification(int $orderId, int $merchantId, string $notificationType, array $notificationData): array
    {
        return $this->call('notification.send_merchant_order_notification', [
            'order_id' => $orderId,
            'merchant_id' => $merchantId,
            'notification_type' => $notificationType,
            'notification_data' => $notificationData,
        ]);
    }

    /**
     * Get notification preferences for user
     *
     * @param int $userId User ID
     * @return array User notification preferences
     */
    public function getUserNotificationPreferences(int $userId): array
    {
        return $this->call('notification.get_user_notification_preferences', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Update notification preferences for user
     *
     * @param int $userId User ID
     * @param array $preferences Notification preferences
     * @return array Update result
     */
    public function updateUserNotificationPreferences(int $userId, array $preferences): array
    {
        return $this->call('notification.update_user_notification_preferences', [
            'user_id' => $userId,
            'preferences' => $preferences,
        ]);
    }

    /**
     * Get notification history for order
     *
     * @param int $orderId Order ID
     * @return array Notification history
     */
    public function getOrderNotificationHistory(int $orderId): array
    {
        return $this->call('notification.get_order_notification_history', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Schedule order notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param string $notificationType Notification type
     * @param array $notificationData Notification details
     * @param string $scheduledAt Scheduled time (ISO 8601)
     * @return array Scheduling result
     */
    public function scheduleOrderNotification(int $orderId, int $customerId, string $notificationType, array $notificationData, string $scheduledAt): array
    {
        return $this->call('notification.schedule_order_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'notification_type' => $notificationType,
            'notification_data' => $notificationData,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /**
     * Cancel scheduled order notification
     *
     * @param int $notificationId Notification ID
     * @return array Cancellation result
     */
    public function cancelScheduledOrderNotification(int $notificationId): array
    {
        return $this->call('notification.cancel_scheduled_order_notification', [
            'notification_id' => $notificationId,
        ]);
    }

    /**
     * Send bulk order notifications
     *
     * @param array $notifications Array of notification data
     * @return array Bulk sending result
     */
    public function sendBulkOrderNotifications(array $notifications): array
    {
        return $this->call('notification.send_bulk_order_notifications', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Get notification delivery status
     *
     * @param int $notificationId Notification ID
     * @return array Delivery status
     */
    public function getNotificationDeliveryStatus(int $notificationId): array
    {
        return $this->call('notification.get_notification_delivery_status', [
            'notification_id' => $notificationId,
        ]);
    }

    /**
     * Resend failed order notification
     *
     * @param int $notificationId Notification ID
     * @return array Resend result
     */
    public function resendFailedOrderNotification(int $notificationId): array
    {
        return $this->call('notification.resend_failed_order_notification', [
            'notification_id' => $notificationId,
        ]);
    }

    /**
     * Send order review request notification
     *
     * @param int $orderId Order ID
     * @param int $customerId Customer ID
     * @param array $reviewData Review request details
     * @return array Notification sending result
     */
    public function sendOrderReviewRequestNotification(int $orderId, int $customerId, array $reviewData = []): array
    {
        return $this->call('notification.send_order_review_request_notification', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'review_data' => $reviewData,
        ]);
    }

    /**
     * Send batch order notifications (batch operation)
     *
     * @param array $orderNotifications Array of order notifications
     * @return array Batch notification results
     */
    public function sendBatchOrderNotifications(array $orderNotifications): array
    {
        $calls = [];
        foreach ($orderNotifications as $index => $notification) {
            $calls[] = [
                'method' => 'notification.send_order_status_update_notification',
                'params' => $notification,
                'id' => "send_order_notification_{$index}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Get notification analytics for orders
     *
     * @param array $dateRange Date range filter
     * @param array $filters Additional filters
     * @return array Notification analytics
     */
    public function getOrderNotificationAnalytics(array $dateRange = [], array $filters = []): array
    {
        return $this->call('notification.get_order_notification_analytics', [
            'date_range' => $dateRange,
            'filters' => $filters,
        ]);
    }
}
