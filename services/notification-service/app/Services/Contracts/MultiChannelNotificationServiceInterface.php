<?php

namespace App\Services\Contracts;

/**
 * Multi-Channel Notification Service Contract
 * 
 * Defines the interface for orchestrating notifications across multiple channels
 */
interface MultiChannelNotificationServiceInterface
{
    /**
     * Send notification across multiple channels
     */
    public function sendMultiChannelNotification(array $channels, array $notificationData): array;

    /**
     * Send notification with fallback channels
     */
    public function sendWithFallback(array $primaryChannels, array $fallbackChannels, array $notificationData): array;

    /**
     * Get available notification channels
     */
    public function getAvailableChannels(): array;

    /**
     * Get channel status
     */
    public function getChannelStatus(string $channel): array;

    /**
     * Configure channel preferences
     */
    public function configureChannelPreferences(int $userId, array $preferences): array;

    /**
     * Get user channel preferences
     */
    public function getUserChannelPreferences(int $userId): array;

    /**
     * Send notification based on user preferences
     */
    public function sendBasedOnPreferences(int $userId, array $notificationData): array;

    /**
     * Schedule multi-channel notification
     */
    public function scheduleMultiChannelNotification(array $channels, array $notificationData, string $scheduledAt): array;

    /**
     * Get notification delivery report
     */
    public function getDeliveryReport(string $notificationId): array;

    /**
     * Get multi-channel statistics
     */
    public function getMultiChannelStatistics(array $filters = []): array;

    /**
     * Test channel connectivity
     */
    public function testChannelConnectivity(string $channel): array;

    /**
     * Retry failed notifications
     */
    public function retryFailedNotifications(array $notificationIds): array;

    /**
     * Get failed notifications
     */
    public function getFailedNotifications(array $filters = []): array;

    /**
     * Configure notification routing rules
     */
    public function configureRoutingRules(array $rules): array;
}
