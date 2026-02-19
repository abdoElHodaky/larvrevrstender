<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Notification Service (from Bidding Service)
 * 
 * Provides RPC-based communication with the notification service for
 * bid-related notifications and alerts.
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
     * Send bid confirmation notification
     *
     * @param array $bidData Bid data for notification
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
     * Send bid rejection notification
     *
     * @param array $rejectionData Bid rejection data
     * @return array RPC response
     */
    public function sendBidRejectionNotification(array $rejectionData): array
    {
        return $this->call('notification.sendBidRejection', $rejectionData);
    }
    
    /**
     * Send auction status change notification
     *
     * @param array $statusData Status change data
     * @return array RPC response
     */
    public function sendAuctionStatusNotification(array $statusData): array
    {
        return $this->call('notification.sendAuctionStatusChange', $statusData);
    }
    
    /**
     * Send bulk notifications to multiple bidders
     *
     * @param array $bulkData Bulk notification data
     * @return array RPC response
     */
    public function sendBulkBidderNotifications(array $bulkData): array
    {
        return $this->call('notification.sendBulkBidderNotifications', $bulkData);
    }
    
    /**
     * Send email notification
     *
     * @param array $emailData Email data
     * @return array RPC response
     */
    public function sendEmail(array $emailData): array
    {
        return $this->call('notification.sendEmail', $emailData);
    }
    
    /**
     * Send SMS notification
     *
     * @param array $smsData SMS data
     * @return array RPC response
     */
    public function sendSms(array $smsData): array
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
        return $this->call('notification.sendPushNotification', $pushData);
    }
}

