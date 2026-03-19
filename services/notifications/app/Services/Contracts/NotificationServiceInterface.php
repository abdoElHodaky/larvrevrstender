<?php

namespace App\Services\Contracts;

/**
 * Notification Service Contract
 * 
 * Defines the interface for core notification management services
 */
interface NotificationServiceInterface
{
    /**
     * Send notification
     */
    public function sendNotification(array $notificationData): array;

    /**
     * Get notification by ID
     */
    public function getNotification(int $notificationId): array;

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, array $filters = []): array;

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): array;

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(array $notificationIds): array;

    /**
     * Delete notification
     */
    public function deleteNotification(int $notificationId): array;

    /**
     * Get notification preferences
     */
    public function getNotificationPreferences(int $userId): array;

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences(int $userId, array $preferences): array;

    /**
     * Get notification statistics
     */
    public function getNotificationStatistics(int $userId): array;

    /**
     * Schedule notification
     */
    public function scheduleNotification(array $notificationData, string $scheduledAt): array;

    /**
     * Cancel scheduled notification
     */
    public function cancelScheduledNotification(int $notificationId): array;

    /**
     * Get notification templates
     */
    public function getNotificationTemplates(): array;
}
