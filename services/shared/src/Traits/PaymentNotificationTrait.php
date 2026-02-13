<?php

namespace Shared\Traits;

/**
 * Payment Notification Trait
 * 
 * Provides payment-specific notification methods with predefined templates
 * and business logic for payment-related notifications.
 * 
 * @package Shared\Traits
 */
trait PaymentNotificationTrait
{
    use NotificationTrait;
    
    /**
     * Initialize payment notification context
     */
    public function initializePaymentNotifications(): void
    {
        $this->setNotificationService('payment');
    }
    
    /**
     * Notify when payment is successful
     *
     * @param array $paymentData Payment information
     * @param array $customerData Customer information
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyPaymentSuccess(
        array $paymentData,
        array $customerData,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($paymentData, $customerData, [
            'payment_method_display' => $this->getPaymentMethodDisplay($paymentData['method'] ?? 'unknown'),
            'receipt_url' => config('app.frontend_url') . "/receipts/{$paymentData['id']}"
        ]);
        
        return $this->sendEmail([
            'template' => 'payment.success',
            'recipients' => [$customerData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Payment Confirmation - ' . ($paymentData['reference'] ?? 'N/A'),
            'priority' => 'high',
            'tracking' => [
                'event' => 'payment_success',
                'payment_id' => $paymentData['id'] ?? null,
                'amount' => $paymentData['amount'] ?? null
            ]
        ]);
    }
    
    /**
     * Notify when payment fails
     *
     * @param array $paymentData Payment information
     * @param array $customerData Customer information
     * @param string $failureReason Reason for failure
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyPaymentFailed(
        array $paymentData,
        array $customerData,
        string $failureReason,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($paymentData, $customerData, [
            'failure_reason' => $failureReason,
            'retry_url' => config('app.frontend_url') . "/payments/{$paymentData['id']}/retry",
            'support_url' => config('app.frontend_url') . '/support'
        ]);
        
        return $this->sendMultiChannel([
            'channels' => ['email', 'sms'],
            'template' => 'payment.failed',
            'recipients' => [
                ['recipient' => $customerData['email'], 'channel' => 'email'],
                ['recipient' => $customerData['phone'] ?? null, 'channel' => 'sms']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'payment_failed',
                'payment_id' => $paymentData['id'] ?? null,
                'failure_reason' => $failureReason
            ]
        ]);
    }
    
    /**
     * Notify when payment is pending
     *
     * @param array $paymentData Payment information
     * @param array $customerData Customer information
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyPaymentPending(
        array $paymentData,
        array $customerData,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($paymentData, $customerData, [
            'estimated_completion' => $this->getEstimatedCompletion($paymentData['method'] ?? 'unknown'),
            'status_url' => config('app.frontend_url') . "/payments/{$paymentData['id']}/status"
        ]);
        
        return $this->sendEmail([
            'template' => 'payment.pending',
            'recipients' => [$customerData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Payment Processing - ' . ($paymentData['reference'] ?? 'N/A'),
            'priority' => 'normal',
            'tracking' => [
                'event' => 'payment_pending',
                'payment_id' => $paymentData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Notify when refund is processed
     *
     * @param array $refundData Refund information
     * @param array $customerData Customer information
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyRefundProcessed(
        array $refundData,
        array $customerData,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($refundData, $customerData, [
            'refund_method' => $refundData['method'] ?? 'original_payment_method',
            'processing_time' => $this->getRefundProcessingTime($refundData['method'] ?? 'unknown')
        ]);
        
        return $this->sendEmail([
            'template' => 'payment.refund_processed',
            'recipients' => [$customerData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Refund Processed - ' . ($refundData['reference'] ?? 'N/A'),
            'priority' => 'high',
            'tracking' => [
                'event' => 'refund_processed',
                'refund_id' => $refundData['id'] ?? null,
                'amount' => $refundData['amount'] ?? null
            ]
        ]);
    }
    
    /**
     * Notify when subscription payment is due
     *
     * @param array $subscriptionData Subscription information
     * @param array $customerData Customer information
     * @param int $daysUntilDue Days until payment is due
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifySubscriptionPaymentDue(
        array $subscriptionData,
        array $customerData,
        int $daysUntilDue,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($subscriptionData, $customerData, [
            'days_until_due' => $daysUntilDue,
            'due_date' => $subscriptionData['next_billing_date'] ?? null,
            'update_payment_url' => config('app.frontend_url') . '/billing/payment-methods'
        ]);
        
        return $this->sendEmail([
            'template' => 'payment.subscription_due',
            'recipients' => [$customerData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Upcoming Payment - ' . ($subscriptionData['plan_name'] ?? 'Subscription'),
            'priority' => 'normal',
            'tracking' => [
                'event' => 'subscription_payment_due',
                'subscription_id' => $subscriptionData['id'] ?? null,
                'days_until_due' => $daysUntilDue
            ]
        ]);
    }
    
    /**
     * Notify when subscription payment fails
     *
     * @param array $subscriptionData Subscription information
     * @param array $customerData Customer information
     * @param string $failureReason Reason for failure
     * @param int $retryAttempt Current retry attempt
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifySubscriptionPaymentFailed(
        array $subscriptionData,
        array $customerData,
        string $failureReason,
        int $retryAttempt,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($subscriptionData, $customerData, [
            'failure_reason' => $failureReason,
            'retry_attempt' => $retryAttempt,
            'max_retries' => 3,
            'next_retry_date' => now()->addDays($retryAttempt)->format('Y-m-d'),
            'update_payment_url' => config('app.frontend_url') . '/billing/payment-methods'
        ]);
        
        return $this->sendMultiChannel([
            'channels' => ['email', 'sms'],
            'template' => 'payment.subscription_failed',
            'recipients' => [
                ['recipient' => $customerData['email'], 'channel' => 'email'],
                ['recipient' => $customerData['phone'] ?? null, 'channel' => 'sms']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'subscription_payment_failed',
                'subscription_id' => $subscriptionData['id'] ?? null,
                'retry_attempt' => $retryAttempt
            ]
        ]);
    }
    
    /**
     * Notify when payment method is about to expire
     *
     * @param array $paymentMethodData Payment method information
     * @param array $customerData Customer information
     * @param int $daysUntilExpiry Days until expiry
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyPaymentMethodExpiring(
        array $paymentMethodData,
        array $customerData,
        int $daysUntilExpiry,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($paymentMethodData, $customerData, [
            'days_until_expiry' => $daysUntilExpiry,
            'expiry_date' => $paymentMethodData['expires_at'] ?? null,
            'masked_number' => $this->maskPaymentMethod($paymentMethodData),
            'update_payment_url' => config('app.frontend_url') . '/billing/payment-methods'
        ]);
        
        return $this->sendEmail([
            'template' => 'payment.method_expiring',
            'recipients' => [$customerData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Payment Method Expiring Soon',
            'priority' => 'normal',
            'tracking' => [
                'event' => 'payment_method_expiring',
                'payment_method_id' => $paymentMethodData['id'] ?? null,
                'days_until_expiry' => $daysUntilExpiry
            ]
        ]);
    }
    
    /**
     * Notify about suspicious payment activity
     *
     * @param array $paymentData Payment information
     * @param array $customerData Customer information
     * @param string $suspiciousActivity Description of suspicious activity
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyPaymentSecurity(
        array $paymentData,
        array $customerData,
        string $suspiciousActivity,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($paymentData, $customerData, [
            'suspicious_activity' => $suspiciousActivity,
            'detected_at' => now()->format('Y-m-d H:i:s'),
            'security_url' => config('app.frontend_url') . '/security',
            'support_phone' => config('app.support_phone', '1-800-SUPPORT')
        ]);
        
        return $this->sendMultiChannel([
            'channels' => ['email', 'sms'],
            'template' => 'payment.security_alert',
            'recipients' => [
                ['recipient' => $customerData['email'], 'channel' => 'email'],
                ['recipient' => $customerData['phone'] ?? null, 'channel' => 'sms']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'payment_security_alert',
                'payment_id' => $paymentData['id'] ?? null,
                'activity_type' => $suspiciousActivity
            ]
        ]);
    }
    
    /**
     * Send payment receipt
     *
     * @param array $paymentData Payment information
     * @param array $customerData Customer information
     * @param array $itemsData Items purchased
     * @param string $language Language preference
     * @return bool Success status
     */
    public function sendPaymentReceipt(
        array $paymentData,
        array $customerData,
        array $itemsData = [],
        string $language = 'en'
    ): bool {
        $templateData = array_merge($paymentData, $customerData, [
            'items' => $itemsData,
            'receipt_number' => $paymentData['receipt_number'] ?? $paymentData['id'],
            'receipt_url' => config('app.frontend_url') . "/receipts/{$paymentData['id']}",
            'tax_amount' => $paymentData['tax_amount'] ?? 0,
            'total_amount' => $paymentData['amount'] ?? 0
        ]);
        
        return $this->sendEmail([
            'template' => 'payment.receipt',
            'recipients' => [$customerData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Receipt - ' . ($paymentData['reference'] ?? 'N/A'),
            'priority' => 'normal',
            'tracking' => [
                'event' => 'payment_receipt',
                'payment_id' => $paymentData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Schedule payment reminder
     *
     * @param array $paymentData Payment information
     * @param array $customerData Customer information
     * @param string $reminderDate When to send reminder
     * @param string $reminderType Type of reminder
     * @param string $language Language preference
     * @return bool Success status
     */
    public function schedulePaymentReminder(
        array $paymentData,
        array $customerData,
        string $reminderDate,
        string $reminderType = 'due',
        string $language = 'en'
    ): bool {
        $templateData = array_merge($paymentData, $customerData, [
            'reminder_type' => $reminderType
        ]);
        
        return $this->scheduleNotification([
            'channel' => 'email',
            'template' => "payment.reminder.{$reminderType}",
            'recipients' => [$customerData['email']],
            'data' => $templateData,
            'scheduled_at' => $reminderDate,
            'language' => $language,
            'schedule_id' => "payment_reminder_{$paymentData['id']}_{$reminderType}"
        ]);
    }
    
    /**
     * Get display name for payment method
     *
     * @param string $method Payment method code
     * @return string Display name
     */
    private function getPaymentMethodDisplay(string $method): string
    {
        $methods = [
            'card' => 'Credit/Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'paypal' => 'PayPal',
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'cash' => 'Cash',
            'check' => 'Check'
        ];
        
        return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }
    
    /**
     * Get estimated completion time for payment method
     *
     * @param string $method Payment method
     * @return string Estimated completion time
     */
    private function getEstimatedCompletion(string $method): string
    {
        $times = [
            'card' => 'within a few minutes',
            'bank_transfer' => '1-3 business days',
            'paypal' => 'within a few minutes',
            'apple_pay' => 'within a few minutes',
            'google_pay' => 'within a few minutes',
            'check' => '5-7 business days'
        ];
        
        return $times[$method] ?? '1-2 business days';
    }
    
    /**
     * Get refund processing time
     *
     * @param string $method Refund method
     * @return string Processing time
     */
    private function getRefundProcessingTime(string $method): string
    {
        $times = [
            'card' => '3-5 business days',
            'bank_transfer' => '1-2 business days',
            'paypal' => '1-2 business days',
            'original_payment_method' => '3-5 business days'
        ];
        
        return $times[$method] ?? '3-5 business days';
    }
    
    /**
     * Mask payment method for display
     *
     * @param array $paymentMethodData Payment method data
     * @return string Masked payment method
     */
    private function maskPaymentMethod(array $paymentMethodData): string
    {
        $type = $paymentMethodData['type'] ?? 'card';
        $lastFour = $paymentMethodData['last_four'] ?? '****';
        
        if ($type === 'card') {
            return "**** **** **** {$lastFour}";
        }
        
        return $lastFour;
    }
}
