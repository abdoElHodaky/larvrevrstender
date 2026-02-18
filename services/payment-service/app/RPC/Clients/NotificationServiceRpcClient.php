<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Notification Service (from Payment Service)
 * 
 * Provides RPC-based communication with the notification service for
 * payment-related notifications and alerts.
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
     * Send payment confirmation notification
     *
     * @param array $paymentData Payment data for notification
     * @return array RPC response
     */
    public function sendPaymentConfirmation(array $paymentData): array
    {
        return $this->call('notification.sendPaymentConfirmation', $paymentData);
    }
    
    /**
     * Send payment failure notification
     *
     * @param array $failureData Payment failure data
     * @return array RPC response
     */
    public function sendPaymentFailureNotification(array $failureData): array
    {
        return $this->call('notification.sendPaymentFailure', $failureData);
    }
    
    /**
     * Send payment reminder
     *
     * @param array $reminderData Payment reminder data
     * @return array RPC response
     */
    public function sendPaymentReminder(array $reminderData): array
    {
        return $this->call('notification.sendPaymentReminder', $reminderData);
    }
    
    /**
     * Send refund notification
     *
     * @param array $refundData Refund notification data
     * @return array RPC response
     */
    public function sendRefundNotification(array $refundData): array
    {
        return $this->call('notification.sendRefundNotification', $refundData);
    }
    
    /**
     * Send fund reservation notification
     *
     * @param array $reservationData Fund reservation data
     * @return array RPC response
     */
    public function sendFundReservationNotification(array $reservationData): array
    {
        return $this->call('notification.sendFundReservation', $reservationData);
    }
    
    /**
     * Send fund release notification
     *
     * @param array $releaseData Fund release data
     * @return array RPC response
     */
    public function sendFundReleaseNotification(array $releaseData): array
    {
        return $this->call('notification.sendFundRelease', $releaseData);
    }
    
    /**
     * Send payment method validation notification
     *
     * @param array $validationData Validation data
     * @return array RPC response
     */
    public function sendPaymentMethodValidation(array $validationData): array
    {
        return $this->call('notification.sendPaymentMethodValidation', $validationData);
    }
    
    /**
     * Send transaction receipt
     *
     * @param array $receiptData Transaction receipt data
     * @return array RPC response
     */
    public function sendTransactionReceipt(array $receiptData): array
    {
        return $this->call('notification.sendTransactionReceipt', $receiptData);
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
}

