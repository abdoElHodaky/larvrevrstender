<?php

namespace App\Services\Contracts;

/**
 * Web Push Service Contract
 * 
 * Defines the interface for web push notification services
 */
interface WebPushServiceInterface
{
    /**
     * Send web push notification
     */
    public function sendWebPush(string $endpoint, array $notificationData): array;

    /**
     * Send bulk web push notifications
     */
    public function sendBulkWebPush(array $subscriptions, array $notificationData): array;

    /**
     * Subscribe to web push
     */
    public function subscribe(array $subscriptionData): array;

    /**
     * Unsubscribe from web push
     */
    public function unsubscribe(string $endpoint): array;

    /**
     * Get subscription info
     */
    public function getSubscriptionInfo(string $endpoint): array;

    /**
     * Validate subscription
     */
    public function validateSubscription(array $subscriptionData): array;

    /**
     * Generate VAPID keys
     */
    public function generateVapidKeys(): array;

    /**
     * Get VAPID public key
     */
    public function getVapidPublicKey(): string;

    /**
     * Set VAPID keys
     */
    public function setVapidKeys(string $publicKey, string $privateKey): array;

    /**
     * Get web push statistics
     */
    public function getWebPushStatistics(array $filters = []): array;

    /**
     * Handle push subscription change
     */
    public function handleSubscriptionChange(array $subscriptionData): array;

    /**
     * Get active subscriptions
     */
    public function getActiveSubscriptions(): array;

    /**
     * Clean expired subscriptions
     */
    public function cleanExpiredSubscriptions(): array;
}
