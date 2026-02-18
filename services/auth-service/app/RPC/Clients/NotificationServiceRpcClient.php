<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Notification Service (Auth Context)
 * 
 * Provides RPC-based communication with the notification service for
 * authentication-related notifications and security alerts.
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
     * Send authentication success notification
     *
     * @param int $userId User ID
     * @param array $authData Authentication data (IP, device, location, etc.)
     * @return array RPC response
     */
    public function sendAuthSuccessNotification(int $userId, array $authData): array
    {
        return $this->call('notification.sendAuthSuccess', [
            'user_id' => $userId,
            'auth_data' => $authData,
        ]);
    }
    
    /**
     * Send authentication failure notification
     *
     * @param int $userId User ID
     * @param array $failureData Failure data (IP, attempts, reason, etc.)
     * @return array RPC response
     */
    public function sendAuthFailureNotification(int $userId, array $failureData): array
    {
        return $this->call('notification.sendAuthFailure', [
            'user_id' => $userId,
            'failure_data' => $failureData,
        ]);
    }
    
    /**
     * Send account lockout notification
     *
     * @param int $userId User ID
     * @param array $lockoutData Lockout data (reason, duration, unlock_time, etc.)
     * @return array RPC response
     */
    public function sendAccountLockoutNotification(int $userId, array $lockoutData): array
    {
        return $this->call('notification.sendAccountLockout', [
            'user_id' => $userId,
            'lockout_data' => $lockoutData,
        ]);
    }
    
    /**
     * Send password reset notification
     *
     * @param int $userId User ID
     * @param array $resetData Password reset data (token, expiry, etc.)
     * @return array RPC response
     */
    public function sendPasswordResetNotification(int $userId, array $resetData): array
    {
        return $this->call('notification.sendPasswordReset', [
            'user_id' => $userId,
            'reset_data' => $resetData,
        ]);
    }
    
    /**
     * Send password change confirmation notification
     *
     * @param int $userId User ID
     * @param array $changeData Password change data
     * @return array RPC response
     */
    public function sendPasswordChangeNotification(int $userId, array $changeData): array
    {
        return $this->call('notification.sendPasswordChange', [
            'user_id' => $userId,
            'change_data' => $changeData,
        ]);
    }
    
    /**
     * Send email verification notification
     *
     * @param int $userId User ID
     * @param array $verificationData Verification data (token, link, etc.)
     * @return array RPC response
     */
    public function sendEmailVerificationNotification(int $userId, array $verificationData): array
    {
        return $this->call('notification.sendEmailVerification', [
            'user_id' => $userId,
            'verification_data' => $verificationData,
        ]);
    }
    
    /**
     * Send two-factor authentication setup notification
     *
     * @param int $userId User ID
     * @param array $setupData 2FA setup data
     * @return array RPC response
     */
    public function sendTwoFactorSetupNotification(int $userId, array $setupData): array
    {
        return $this->call('notification.sendTwoFactorSetup', [
            'user_id' => $userId,
            'setup_data' => $setupData,
        ]);
    }
    
    /**
     * Send two-factor authentication code
     *
     * @param int $userId User ID
     * @param array $codeData 2FA code data
     * @return array RPC response
     */
    public function sendTwoFactorCodeNotification(int $userId, array $codeData): array
    {
        return $this->call('notification.sendTwoFactorCode', [
            'user_id' => $userId,
            'code_data' => $codeData,
        ]);
    }
    
    /**
     * Send security alert notification
     *
     * @param int $userId User ID
     * @param array $alertData Security alert data
     * @return array RPC response
     */
    public function sendSecurityAlertNotification(int $userId, array $alertData): array
    {
        return $this->call('notification.sendSecurityAlert', [
            'user_id' => $userId,
            'alert_data' => $alertData,
        ]);
    }
    
    /**
     * Send suspicious activity notification
     *
     * @param int $userId User ID
     * @param array $activityData Suspicious activity data
     * @return array RPC response
     */
    public function sendSuspiciousActivityNotification(int $userId, array $activityData): array
    {
        return $this->call('notification.sendSuspiciousActivity', [
            'user_id' => $userId,
            'activity_data' => $activityData,
        ]);
    }
    
    /**
     * Send new device login notification
     *
     * @param int $userId User ID
     * @param array $deviceData New device data
     * @return array RPC response
     */
    public function sendNewDeviceLoginNotification(int $userId, array $deviceData): array
    {
        return $this->call('notification.sendNewDeviceLogin', [
            'user_id' => $userId,
            'device_data' => $deviceData,
        ]);
    }
    
    /**
     * Send session expiry warning notification
     *
     * @param int $userId User ID
     * @param array $sessionData Session data
     * @return array RPC response
     */
    public function sendSessionExpiryWarningNotification(int $userId, array $sessionData): array
    {
        return $this->call('notification.sendSessionExpiryWarning', [
            'user_id' => $userId,
            'session_data' => $sessionData,
        ]);
    }
    
    /**
     * Send account recovery notification
     *
     * @param int $userId User ID
     * @param array $recoveryData Account recovery data
     * @return array RPC response
     */
    public function sendAccountRecoveryNotification(int $userId, array $recoveryData): array
    {
        return $this->call('notification.sendAccountRecovery', [
            'user_id' => $userId,
            'recovery_data' => $recoveryData,
        ]);
    }
    
    /**
     * Send role change notification
     *
     * @param int $userId User ID
     * @param array $roleChangeData Role change data
     * @return array RPC response
     */
    public function sendRoleChangeNotification(int $userId, array $roleChangeData): array
    {
        return $this->call('notification.sendRoleChange', [
            'user_id' => $userId,
            'role_change_data' => $roleChangeData,
        ]);
    }
    
    /**
     * Send permission change notification
     *
     * @param int $userId User ID
     * @param array $permissionChangeData Permission change data
     * @return array RPC response
     */
    public function sendPermissionChangeNotification(int $userId, array $permissionChangeData): array
    {
        return $this->call('notification.sendPermissionChange', [
            'user_id' => $userId,
            'permission_change_data' => $permissionChangeData,
        ]);
    }
    
    /**
     * Send welcome notification for new user registration
     *
     * @param int $userId User ID
     * @param array $welcomeData Welcome data
     * @return array RPC response
     */
    public function sendWelcomeNotification(int $userId, array $welcomeData): array
    {
        return $this->call('notification.sendWelcome', [
            'user_id' => $userId,
            'welcome_data' => $welcomeData,
        ]);
    }
    
    /**
     * Send account deactivation notification
     *
     * @param int $userId User ID
     * @param array $deactivationData Deactivation data
     * @return array RPC response
     */
    public function sendAccountDeactivationNotification(int $userId, array $deactivationData): array
    {
        return $this->call('notification.sendAccountDeactivation', [
            'user_id' => $userId,
            'deactivation_data' => $deactivationData,
        ]);
    }
    
    /**
     * Send account reactivation notification
     *
     * @param int $userId User ID
     * @param array $reactivationData Reactivation data
     * @return array RPC response
     */
    public function sendAccountReactivationNotification(int $userId, array $reactivationData): array
    {
        return $this->call('notification.sendAccountReactivation', [
            'user_id' => $userId,
            'reactivation_data' => $reactivationData,
        ]);
    }
    
    /**
     * Batch operation: Send multiple authentication notifications
     *
     * @param array $notifications Array of notification data
     * @return array Array of RPC responses
     */
    public function sendBatchAuthNotifications(array $notifications): array
    {
        $calls = [];
        foreach ($notifications as $notification) {
            $calls[] = [
                'method' => $notification['method'],
                'params' => $notification['params'],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

