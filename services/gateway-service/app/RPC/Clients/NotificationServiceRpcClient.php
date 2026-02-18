<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Notification Service
 * 
 * Provides RPC-based communication with the notification service for
 * sending notifications, managing preferences, and notification history.
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
     * Send notification to user
     *
     * @param array $notificationData Notification data (user_id, type, message, etc.)
     * @return array RPC response with send result
     */
    public function sendNotification(array $notificationData): array
    {
        return $this->call('notification.send', $notificationData);
    }
    
    /**
     * Send bulk notifications
     *
     * @param array $notifications Array of notification data
     * @return array RPC response with bulk send results
     */
    public function sendBulkNotifications(array $notifications): array
    {
        return $this->call('notification.sendBulk', [
            'notifications' => $notifications,
        ]);
    }
    
    /**
     * Get user notifications
     *
     * @param int $userId User ID
     * @param array $filters Optional filters (type, status, date_range, etc.)
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with user notifications
     */
    public function getUserNotifications(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('notification.getUserNotifications', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Mark notification as read
     *
     * @param int $notificationId Notification ID
     * @return array RPC response
     */
    public function markAsRead(int $notificationId): array
    {
        return $this->call('notification.markAsRead', [
            'notification_id' => $notificationId,
        ]);
    }
    
    /**
     * Mark multiple notifications as read
     *
     * @param array $notificationIds Array of notification IDs
     * @return array RPC response
     */
    public function markMultipleAsRead(array $notificationIds): array
    {
        return $this->call('notification.markMultipleAsRead', [
            'notification_ids' => $notificationIds,
        ]);
    }
    
    /**
     * Mark all user notifications as read
     *
     * @param int $userId User ID
     * @return array RPC response
     */
    public function markAllAsRead(int $userId): array
    {
        return $this->call('notification.markAllAsRead', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Delete notification
     *
     * @param int $notificationId Notification ID
     * @return array RPC response
     */
    public function deleteNotification(int $notificationId): array
    {
        return $this->call('notification.delete', [
            'notification_id' => $notificationId,
        ]);
    }
    
    /**
     * Get unread notification count for user
     *
     * @param int $userId User ID
     * @return array RPC response with unread count
     */
    public function getUnreadCount(int $userId): array
    {
        return $this->call('notification.getUnreadCount', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get user notification preferences
     *
     * @param int $userId User ID
     * @return array RPC response with preferences
     */
    public function getUserPreferences(int $userId): array
    {
        return $this->call('notification.getUserPreferences', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Update user notification preferences
     *
     * @param int $userId User ID
     * @param array $preferences Notification preferences
     * @return array RPC response
     */
    public function updateUserPreferences(int $userId, array $preferences): array
    {
        return $this->call('notification.updatePreferences', [
            'user_id' => $userId,
            'preferences' => $preferences,
        ]);
    }
    
    /**
     * Send email notification
     *
     * @param array $emailData Email notification data
     * @return array RPC response
     */
    public function sendEmailNotification(array $emailData): array
    {
        return $this->call('notification.sendEmail', $emailData);
    }
    
    /**
     * Send SMS notification
     *
     * @param array $smsData SMS notification data
     * @return array RPC response
     */
    public function sendSmsNotification(array $smsData): array
    {
        return $this->call('notification.sendSms', $smsData);
    }
    
    /**
     * Send push notification
     *
     * @param array $pushData Push notification data
     * @return array RPC response
     */
    public function sendPushNotification(array $pushData): array
    {
        return $this->call('notification.sendPush', $pushData);
    }
    
    /**
     * Send auction-related notification
     *
     * @param int $auctionId Auction ID
     * @param string $type Notification type (bid_placed, auction_ended, etc.)
     * @param array $recipients Array of user IDs
     * @param array $data Additional notification data
     * @return array RPC response
     */
    public function sendAuctionNotification(int $auctionId, string $type, array $recipients, array $data = []): array
    {
        return $this->call('notification.sendAuctionNotification', [
            'auction_id' => $auctionId,
            'type' => $type,
            'recipients' => $recipients,
            'data' => $data,
        ]);
    }
    
    /**
     * Send bid-related notification
     *
     * @param int $bidId Bid ID
     * @param string $type Notification type (outbid, bid_accepted, etc.)
     * @param array $recipients Array of user IDs
     * @param array $data Additional notification data
     * @return array RPC response
     */
    public function sendBidNotification(int $bidId, string $type, array $recipients, array $data = []): array
    {
        return $this->call('notification.sendBidNotification', [
            'bid_id' => $bidId,
            'type' => $type,
            'recipients' => $recipients,
            'data' => $data,
        ]);
    }
    
    /**
     * Send payment-related notification
     *
     * @param int $paymentId Payment ID
     * @param string $type Notification type (payment_success, payment_failed, etc.)
     * @param int $userId User ID
     * @param array $data Additional notification data
     * @return array RPC response
     */
    public function sendPaymentNotification(int $paymentId, string $type, int $userId, array $data = []): array
    {
        return $this->call('notification.sendPaymentNotification', [
            'payment_id' => $paymentId,
            'type' => $type,
            'user_id' => $userId,
            'data' => $data,
        ]);
    }
    
    /**
     * Get notification templates
     *
     * @param string|null $type Optional template type filter
     * @return array RPC response with templates
     */
    public function getNotificationTemplates(?string $type = null): array
    {
        $params = [];
        if ($type) {
            $params['type'] = $type;
        }
        
        return $this->call('notification.getTemplates', $params);
    }
    
    /**
     * Batch operation: Send multiple notifications
     *
     * @param array $notifications Array of notification data
     * @return array Array of RPC responses
     */
    public function batchSendNotifications(array $notifications): array
    {
        $calls = [];
        foreach ($notifications as $notification) {
            $calls[] = [
                'method' => 'notification.send',
                'params' => $notification,
            ];
        }
        
        return $this->batchCall($calls);
    }
}

