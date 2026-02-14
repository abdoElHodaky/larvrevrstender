<?php

namespace Shared\Traits;

/**
 * User Notification Trait
 * 
 * Provides user-specific notification methods with predefined templates
 * and business logic for user-related notifications.
 * 
 * @package Shared\Traits
 */
trait UserNotificationTrait
{
    use NotificationTrait;
    
    /**
     * Initialize user notification context
     */
    public function initializeUserNotifications(): void
    {
        $this->setNotificationService('user');
    }
    
    /**
     * Send welcome notification to new user
     *
     * @param array $userData User information
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyUserWelcome(array $userData, string $language = 'en'): bool
    {
        return $this->sendEmail([
            'template' => 'user.welcome',
            'recipients' => [$userData['email']],
            'data' => $userData,
            'language' => $language,
            'subject' => 'Welcome to ' . (config('app.name') ?? 'Our Platform'),
            'priority' => 'normal',
            'tracking' => [
                'event' => 'user_welcome',
                'user_id' => $userData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Send email verification notification
     *
     * @param array $userData User information
     * @param string $verificationToken Verification token
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyEmailVerification(
        array $userData,
        string $verificationToken,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($userData, [
            'verification_token' => $verificationToken,
            'verification_url' => config('app.frontend_url') . "/verify-email?token={$verificationToken}",
            'expires_at' => now()->addHours(24)->format('Y-m-d H:i:s')
        ]);
        
        return $this->sendEmail([
            'template' => 'user.email_verification',
            'recipients' => [$userData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Please Verify Your Email Address',
            'priority' => 'high',
            'tracking' => [
                'event' => 'email_verification',
                'user_id' => $userData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Send password reset notification
     *
     * @param array $userData User information
     * @param string $resetToken Reset token
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyPasswordReset(
        array $userData,
        string $resetToken,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($userData, [
            'reset_token' => $resetToken,
            'reset_url' => config('app.frontend_url') . "/reset-password?token={$resetToken}",
            'expires_at' => now()->addHour()->format('Y-m-d H:i:s')
        ]);
        
        return $this->sendEmail([
            'template' => 'user.password_reset',
            'recipients' => [$userData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Password Reset Request',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'password_reset',
                'user_id' => $userData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Send two-factor authentication code
     *
     * @param array $userData User information
     * @param string $code 2FA code
     * @param string $channel Channel to send (sms, email)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyTwoFactorCode(
        array $userData,
        string $code,
        string $channel = 'sms',
        string $language = 'en'
    ): bool {
        $templateData = array_merge($userData, [
            'code' => $code,
            'expires_in' => '10 minutes'
        ]);
        
        if ($channel === 'sms' && !empty($userData['phone'])) {
            return $this->sendSms([
                'template' => 'user.two_factor_sms',
                'recipients' => [$userData['phone']],
                'data' => $templateData,
                'language' => $language,
                'provider' => 'unifonic',
                'sender_id' => 'AuthCode',
                'tracking' => [
                    'event' => 'two_factor_sms',
                    'user_id' => $userData['id'] ?? null
                ]
            ]);
        } else {
            return $this->sendEmail([
                'template' => 'user.two_factor_email',
                'recipients' => [$userData['email']],
                'data' => $templateData,
                'language' => $language,
                'subject' => 'Your Security Code',
                'priority' => 'urgent',
                'tracking' => [
                    'event' => 'two_factor_email',
                    'user_id' => $userData['id'] ?? null
                ]
            ]);
        }
    }
    
    /**
     * Send account suspension notification
     *
     * @param array $userData User information
     * @param string $reason Suspension reason
     * @param string|null $until Suspension end date
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyAccountSuspension(
        array $userData,
        string $reason,
        ?string $until = null,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($userData, [
            'suspension_reason' => $reason,
            'suspended_until' => $until,
            'is_permanent' => $until === null,
            'appeal_url' => config('app.frontend_url') . '/appeal'
        ]);
        
        return $this->sendEmail([
            'template' => 'user.account_suspended',
            'recipients' => [$userData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Account Suspension Notice',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'account_suspended',
                'user_id' => $userData['id'] ?? null,
                'reason' => $reason
            ]
        ]);
    }
    
    /**
     * Send profile update confirmation
     *
     * @param array $userData User information
     * @param array $changes Changed fields
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyProfileUpdate(
        array $userData,
        array $changes,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($userData, [
            'changes' => $changes,
            'updated_at' => now()->format('Y-m-d H:i:s')
        ]);
        
        return $this->sendEmail([
            'template' => 'user.profile_updated',
            'recipients' => [$userData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Profile Updated Successfully',
            'priority' => 'normal',
            'tracking' => [
                'event' => 'profile_updated',
                'user_id' => $userData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Send login alert notification
     *
     * @param array $userData User information
     * @param array $loginInfo Login information (IP, device, etc.)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyLoginAlert(
        array $userData,
        array $loginInfo,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($userData, $loginInfo, [
            'login_time' => now()->format('Y-m-d H:i:s'),
            'secure_account_url' => config('app.frontend_url') . '/security'
        ]);
        
        return $this->sendEmail([
            'template' => 'user.login_alert',
            'recipients' => [$userData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'New Login to Your Account',
            'priority' => 'high',
            'tracking' => [
                'event' => 'login_alert',
                'user_id' => $userData['id'] ?? null,
                'ip_address' => $loginInfo['ip_address'] ?? null
            ]
        ]);
    }
    
    /**
     * Send newsletter subscription confirmation
     *
     * @param array $userData User information
     * @param array $subscriptions Subscription preferences
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyNewsletterSubscription(
        array $userData,
        array $subscriptions,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($userData, [
            'subscriptions' => $subscriptions,
            'unsubscribe_url' => config('app.frontend_url') . '/unsubscribe?token=' . base64_encode($userData['email'])
        ]);
        
        return $this->sendEmail([
            'template' => 'user.newsletter_subscription',
            'recipients' => [$userData['email']],
            'data' => $templateData,
            'language' => $language,
            'subject' => 'Newsletter Subscription Confirmed',
            'priority' => 'low',
            'headers' => [
                'List-Unsubscribe' => '<' . $templateData['unsubscribe_url'] . '>',
                'Precedence' => 'bulk'
            ],
            'tracking' => [
                'event' => 'newsletter_subscription',
                'user_id' => $userData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Send bulk user notifications
     *
     * @param array $users Array of user data
     * @param string $template Template name
     * @param array $data Common template data
     * @param string $channel Channel to use
     * @param array $options Additional options
     * @return bool Success status
     */
    public function sendBulkUserNotifications(
        array $users,
        string $template,
        array $data = [],
        string $channel = 'email',
        array $options = []
    ): bool {
        $recipients = [];
        
        foreach ($users as $user) {
            $recipients[] = [
                'recipient' => $user['email'] ?? null,
                'data' => array_merge($data, $user)
            ];
        }
        
        return $this->sendBulkNotification([
            'channel' => $channel,
            'template' => $template,
            'recipients' => $recipients,
            'batch_size' => $options['batch_size'] ?? 100,
            'rate_limit' => $options['rate_limit'] ?? 200,
            'language' => $options['language'] ?? 'en',
            'priority' => $options['priority'] ?? 'normal'
        ]);
    }
    
    /**
     * Schedule user reminder notification
     *
     * @param array $userData User information
     * @param string $reminderType Type of reminder
     * @param string $scheduledAt When to send
     * @param array $options Additional options
     * @return bool Success status
     */
    public function scheduleUserReminder(
        array $userData,
        string $reminderType,
        string $scheduledAt,
        array $options = []
    ): bool {
        $templateData = array_merge($userData, [
            'reminder_type' => $reminderType
        ]);
        
        return $this->scheduleNotification([
            'channel' => $options['channel'] ?? 'email',
            'template' => "user.reminder.{$reminderType}",
            'recipients' => [$userData['email']],
            'data' => $templateData,
            'scheduled_at' => $scheduledAt,
            'timezone' => $options['timezone'] ?? 'UTC',
            'language' => $options['language'] ?? 'en',
            'schedule_id' => "user_reminder_{$userData['id']}_{$reminderType}"
        ]);
    }
}
