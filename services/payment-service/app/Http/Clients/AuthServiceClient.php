<?php

namespace App\Http\Clients;

class AuthServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.auth_service.url'));
    }

    /**
     * Validate user token with auth service.
     */
    public function validateToken(string $token): ?array
    {
        try {
            $response = $this->post('/auth/validate-token', [
                'token' => $token,
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user permissions from auth service.
     */
    public function getUserPermissions(int $userId): array
    {
        try {
            $response = $this->get("/auth/users/{$userId}/permissions");

            return $response->successful() ? $response->json('permissions', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if user has payment permissions.
     */
    public function hasPaymentPermission(int $userId, string $permission): bool
    {
        try {
            $response = $this->get("/auth/users/{$userId}/permissions/{$permission}");

            return $response->successful() && $response->json('has_permission', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate payment authorization.
     */
    public function validatePaymentAuth(int $userId, float $amount): bool
    {
        try {
            $response = $this->post('/auth/validate-payment', [
                'user_id' => $userId,
                'amount' => $amount,
            ]);

            return $response->successful() && $response->json('authorized', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log payment activity for audit.
     */
    public function logPaymentActivity(int $userId, string $action, array $data): bool
    {
        try {
            $response = $this->post('/auth/audit/payment', [
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
