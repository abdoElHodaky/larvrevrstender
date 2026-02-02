<?php

namespace Shared\Http\Clients;

use Psr\Http\Message\ResponseInterface;

class AuthServiceClient extends BaseServiceClient
{
    /**
     * Validate a user token.
     */
    public function validateToken(string $token): ?array
    {
        $response = $this->post('/auth/validate', [
            'token' => $token,
        ]);

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Get user permissions.
     */
    public function getUserPermissions(int $userId): ?array
    {
        $response = $this->get("/auth/permissions/{$userId}");

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Check if user has specific permission.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $response = $this->post('/auth/check-permission', [
            'user_id' => $userId,
            'permission' => $permission,
        ]);

        if (!$this->isSuccessful($response)) {
            return false;
        }

        $data = $this->decodeJsonResponse($response);
        return $data['has_permission'] ?? false;
    }

    /**
     * Log user activity for audit trail.
     */
    public function logActivity(int $userId, string $action, array $metadata = []): bool
    {
        $response = $this->post('/auth/log-activity', [
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]);

        return $this->isSuccessful($response);
    }

    /**
     * Create a new user session.
     */
    public function createSession(int $userId, array $metadata = []): ?array
    {
        $response = $this->post('/auth/sessions', [
            'user_id' => $userId,
            'metadata' => $metadata,
        ]);

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Invalidate a user session.
     */
    public function invalidateSession(string $sessionId): bool
    {
        $response = $this->delete("/auth/sessions/{$sessionId}");

        return $this->isSuccessful($response);
    }

    /**
     * Get user roles.
     */
    public function getUserRoles(int $userId): ?array
    {
        $response = $this->get("/auth/roles/{$userId}");

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Check if user has specific role.
     */
    public function hasRole(int $userId, string $role): bool
    {
        $response = $this->post('/auth/check-role', [
            'user_id' => $userId,
            'role' => $role,
        ]);

        if (!$this->isSuccessful($response)) {
            return false;
        }

        $data = $this->decodeJsonResponse($response);
        return $data['has_role'] ?? false;
    }
}
