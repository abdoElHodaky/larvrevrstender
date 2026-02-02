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
     * Check if user has specific permission.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        try {
            $response = $this->get("/auth/users/{$userId}/permissions/{$permission}");

            return $response->successful() && $response->json('has_permission', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Refresh user token.
     */
    public function refreshToken(string $refreshToken): ?array
    {
        try {
            $response = $this->post('/auth/refresh', [
                'refresh_token' => $refreshToken,
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Logout user from auth service.
     */
    public function logout(string $token): bool
    {
        try {
            $response = $this->post('/auth/logout', [
                'token' => $token,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get user session information.
     */
    public function getUserSession(string $token): ?array
    {
        try {
            $response = $this->get('/auth/session', [
                'token' => $token,
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
