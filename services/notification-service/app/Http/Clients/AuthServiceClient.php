<?php

namespace App\Http\Clients;

class AuthServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.auth_service.url'));
    }

    /**
     * Validate notification access token.
     */
    public function validateToken(string $token): ?array
    {
        try {
            $response = $this->post('/auth/validate-token', ['token' => $token]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if user has notification permissions.
     */
    public function hasNotificationPermission(int $userId, string $permission): bool
    {
        try {
            $response = $this->get("/auth/users/{$userId}/permissions/{$permission}");

            return $response->successful() && $response->json('has_permission', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log notification activity.
     */
    public function logNotificationActivity(int $userId, string $action, array $data): bool
    {
        try {
            $response = $this->post('/auth/audit/notification', [
                'user_id' => $userId,
                'action' => $action,
                'data' => $data,
                'timestamp' => now()->toISOString(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
