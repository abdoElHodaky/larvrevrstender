<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Notification Service
 * 
 * Provides RPC-based communication with the notification service for
 * email, SMS, push notifications, and multi-channel messaging.
 */
class NotificationServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('notification-service', [
            'timeout' => 15, // Notifications should be fast
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Send email notification
     *
     * @param array $emailData Email data (to, subject, body, template, etc.)
     * @return array RPC response with send result
     */
    public function sendEmail(array $emailData): array
    {
        return $this->call('notification.sendEmail', $emailData);
    }
    
    /**
     * Send SMS notification
     *
     * @param array $smsData SMS data (to, message, template, etc.)
     * @return array RPC response with send result
     */
    public function sendSms(array $smsData): array
    {
        return $this->call('notification.sendSms', $smsData);
    }
    
    /**
     * Send push notification
     *
     * @param array $pushData Push notification data (user_id, title, body, data, etc.)
     * @return array RPC response with send result
     */
    public function sendPushNotification(array $pushData): array
    {
        return $this->call('notification.sendPushNotification', $pushData);
    }
    
    /**
     * Send WhatsApp message
     *
     * @param array $whatsappData WhatsApp data (to, message, template, etc.)
     * @return array RPC response with send result
     */
    public function sendWhatsApp(array $whatsappData): array
    {
        return $this->call('notification.sendWhatsApp', $whatsappData);
    }
    
    /**
     * Send Telegram message
     *
     * @param array $telegramData Telegram data (chat_id, message, etc.)
     * @return array RPC response with send result
     */
    public function sendTelegram(array $telegramData): array
    {
        return $this->call('notification.sendTelegram', $telegramData);
    }
    
    /**
     * Send multi-channel notification
     *
     * @param array $notificationData Multi-channel notification data
     * @return array RPC response with send results for all channels
     */
    public function sendMultiChannel(array $notificationData): array
    {
        return $this->call('notification.sendMultiChannel', $notificationData);
    }
    
    /**
     * Send bulk notifications
     *
     * @param array $bulkData Bulk notification data (recipients, message, channels, etc.)
     * @return array RPC response with bulk send results
     */
    public function sendBulkNotification(array $bulkData): array
    {
        return $this->call('notification.sendBulkNotification', $bulkData);
    }
    
    /**
     * Schedule notification for later delivery
     *
     * @param array $scheduleData Scheduled notification data (delivery_time, notification_data, etc.)
     * @return array RPC response with scheduling result
     */
    public function scheduleNotification(array $scheduleData): array
    {
        return $this->call('notification.scheduleNotification', $scheduleData);
    }
    
    /**
     * Cancel scheduled notification
     *
     * @param string $notificationId Notification ID
     * @return array RPC response with cancellation result
     */
    public function cancelNotification(string $notificationId): array
    {
        return $this->call('notification.cancelNotification', [
            'notification_id' => $notificationId,
        ]);
    }
    
    /**
     * Get notification status
     *
     * @param string $notificationId Notification ID
     * @return array RPC response with notification status
     */
    public function getNotificationStatus(string $notificationId): array
    {
        return $this->call('notification.getStatus', [
            'notification_id' => $notificationId,
        ]);
    }
    
    /**
     * Get user notification preferences
     *
     * @param int $userId User ID
     * @return array RPC response with user preferences
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
     * @param array $preferences New preferences
     * @return array RPC response with update result
     */
    public function updateUserPreferences(int $userId, array $preferences): array
    {
        return $this->call('notification.updateUserPreferences', [
            'user_id' => $userId,
            'preferences' => $preferences,
        ]);
    }
    
    /**
     * Get notification templates
     *
     * @param string|null $category Optional template category filter
     * @return array RPC response with available templates
     */
    public function getTemplates(?string $category = null): array
    {
        $params = [];
        if ($category) {
            $params['category'] = $category;
        }
        
        return $this->call('notification.getTemplates', $params);
    }
    
    /**
     * Get notification history for user
     *
     * @param int $userId User ID
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param array $filters Optional filters (channel, status, date_range, etc.)
     * @return array RPC response with notification history
     */
    public function getUserNotificationHistory(
        int $userId, 
        int $limit = 50, 
        int $offset = 0,
        array $filters = []
    ): array {
        return $this->call('notification.getUserHistory', [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get notification analytics
     *
     * @param array $filters Analytics filters (date_range, channel, template, etc.)
     * @return array RPC response with analytics data
     */
    public function getAnalytics(array $filters = []): array
    {
        return $this->call('notification.getAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Send auction creation confirmation to creator
     *
     * @param array $auctionData Auction data for notification
     * @return array RPC response
     */
    public function sendCreatorConfirmation(array $auctionData): array
    {
        return $this->call('notification.sendCreatorConfirmation', $auctionData);
    }
    
    /**
     * Send bidder notification about new auction
     *
     * @param array $notificationData Bidder notification data
     * @return array RPC response
     */
    public function sendBidderNotification(array $notificationData): array
    {
        return $this->call('notification.sendBidderNotification', $notificationData);
    }
    
    /**
     * Send admin notification for high-value auctions
     *
     * @param array $auctionData Auction data for admin notification
     * @return array RPC response
     */
    public function sendAdminNotification(array $auctionData): array
    {
        return $this->call('notification.sendAdminNotification', $auctionData);
    }
    
    /**
     * Send bid confirmation to bidder
     *
     * @param array $bidData Bid data for confirmation
     * @return array RPC response
     */
    public function sendBidConfirmation(array $bidData): array
    {
        return $this->call('notification.sendBidConfirmation', $bidData);
    }
    
    /**
     * Send outbid notification to previous highest bidder
     *
     * @param array $outbidData Outbid notification data
     * @return array RPC response
     */
    public function sendOutbidNotification(array $outbidData): array
    {
        return $this->call('notification.sendOutbidNotification', $outbidData);
    }
    
    /**
     * Send auction ending reminder
     *
     * @param array $reminderData Reminder notification data
     * @return array RPC response
     */
    public function sendAuctionEndingReminder(array $reminderData): array
    {
        return $this->call('notification.sendAuctionEndingReminder', $reminderData);
    }
    
    /**
     * Send auction won notification
     *
     * @param array $winnerData Winner notification data
     * @return array RPC response
     */
    public function sendAuctionWonNotification(array $winnerData): array
    {
        return $this->call('notification.sendAuctionWonNotification', $winnerData);
    }
    
    /**
     * Send payment reminder
     *
     * @param array $paymentData Payment reminder data
     * @return array RPC response
     */
    public function sendPaymentReminder(array $paymentData): array
    {
        return $this->call('notification.sendPaymentReminder', $paymentData);
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
            $method = $notification['method'] ?? 'notification.sendMultiChannel';
            $calls[] = [
                'method' => $method,
                'params' => $notification['data'] ?? $notification,
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get status of multiple notifications
     *
     * @param array $notificationIds Array of notification IDs
     * @return array Array of RPC responses
     */
    public function batchGetNotificationStatus(array $notificationIds): array
    {
        $calls = [];
        foreach ($notificationIds as $notificationId) {
            $calls[] = [
                'method' => 'notification.getStatus',
                'params' => ['notification_id' => $notificationId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

