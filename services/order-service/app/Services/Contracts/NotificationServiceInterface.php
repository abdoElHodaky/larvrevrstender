<?php

namespace App\Services\Contracts;

use App\Models\Order;
use App\Models\User;

/**
 * Notification Service Interface
 * 
 * Defines the contract for notification service implementations
 * Prevents circular dependencies by providing abstraction layer
 */
interface NotificationServiceInterface
{
    /**
     * Send order created notification
     */
    public function sendOrderCreatedNotification(Order $order): void;

    /**
     * Send order published notification
     */
    public function sendOrderPublishedNotification(Order $order): void;

    /**
     * Send order cancelled notification
     */
    public function sendOrderCancelledNotification(Order $order): void;

    /**
     * Send order notification to specific user
     */
    public function sendOrderNotification(User $user, Order $order, string $type): void;

    /**
     * Send bulk notification to multiple users
     */
    public function sendBulkNotification(array $userIds, array $notificationData): void;

    /**
     * Get notification delivery status
     */
    public function getDeliveryStatus(string $notificationId): array;
}
