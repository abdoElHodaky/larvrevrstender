<?php

namespace App\Services\Contracts;

/**
 * Push Notification Service Contract
 * 
 * Defines the interface for mobile push notification services
 */
interface PushNotificationServiceInterface
{
    /**
     * Send push notification
     */
    public function sendPushNotification(string $deviceToken, array $notificationData): array;

    /**
     * Send bulk push notifications
     */
    public function sendBulkPushNotifications(array $deviceTokens, array $notificationData): array;

    /**
     * Send push notification to topic
     */
    public function sendToTopic(string $topic, array $notificationData): array;

    /**
     * Subscribe device to topic
     */
    public function subscribeToTopic(string $deviceToken, string $topic): array;

    /**
     * Unsubscribe device from topic
     */
    public function unsubscribeFromTopic(string $deviceToken, string $topic): array;

    /**
     * Get device subscriptions
     */
    public function getDeviceSubscriptions(string $deviceToken): array;

    /**
     * Register device token
     */
    public function registerDeviceToken(string $deviceToken, array $deviceInfo): array;

    /**
     * Unregister device token
     */
    public function unregisterDeviceToken(string $deviceToken): array;

    /**
     * Get push notification statistics
     */
    public function getPushStatistics(array $filters = []): array;

    /**
     * Validate device token
     */
    public function validateDeviceToken(string $deviceToken): array;

    /**
     * Get push notification preferences
     */
    public function getPushPreferences(string $deviceToken): array;

    /**
     * Update push notification preferences
     */
    public function updatePushPreferences(string $deviceToken, array $preferences): array;

    /**
     * Handle push notification feedback
     */
    public function handlePushFeedback(string $deviceToken, array $feedbackData): array;
}
