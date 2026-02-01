<?php

namespace App\Http\Clients;

class UserServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.user_service.url'));
    }

    /**
     * Get user profile for notification targeting.
     */
    public function getUserProfile(int $userId): ?array
    {
        try {
            $response = $this->get("/users/{$userId}");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user notification preferences.
     */
    public function getUserNotificationPreferences(int $userId): array
    {
        try {
            $response = $this->get("/users/{$userId}/notification-preferences");
            return $response->successful() ? $response->json('preferences', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Update user notification preferences.
     */
    public function updateNotificationPreferences(int $userId, array $preferences): bool
    {
        try {
            $response = $this->put("/users/{$userId}/notification-preferences", $preferences);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get user contact information for notifications.
     */
    public function getUserContactInfo(int $userId): array
    {
        try {
            $response = $this->get("/users/{$userId}/contact-info");
            return $response->successful() ? $response->json() : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Mark notification as delivered.
     */
    public function markNotificationDelivered(int $userId, string $notificationId): bool
    {
        try {
            $response = $this->post("/users/{$userId}/notifications/{$notificationId}/delivered", [
                'delivered_at' => now()->toISOString(),
                'source' => 'notification_service',
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get users for bulk notifications.
     */
    public function getUsersForBulkNotification(array $criteria): array
    {
        try {
            $response = $this->post('/users/bulk-notification-targets', $criteria);
            return $response->successful() ? $response->json('users', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}

