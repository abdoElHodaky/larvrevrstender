<?php

namespace App\Http\Clients;

class AuthServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.auth_service.url'));
    }

    /**
     * Validate VIN OCR access token.
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
     * Check if user has VIN OCR permissions.
     */
    public function hasVinOcrPermission(int $userId, string $permission): bool
    {
        try {
            $response = $this->get("/auth/users/{$userId}/permissions/{$permission}");

            return $response->successful() && $response->json('has_permission', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate VIN OCR operation authorization.
     */
    public function validateOcrAuth(int $userId, string $operation): bool
    {
        try {
            $response = $this->post('/auth/validate-ocr', [
                'user_id' => $userId,
                'operation' => $operation,
            ]);

            return $response->successful() && $response->json('authorized', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log VIN OCR activity for audit.
     */
    public function logOcrActivity(int $userId, string $action, array $data): bool
    {
        try {
            $response = $this->post('/auth/audit/vin-ocr', [
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

    /**
     * Get user OCR usage limits.
     */
    public function getUserOcrLimits(int $userId): array
    {
        try {
            $response = $this->get("/auth/users/{$userId}/ocr-limits");

            return $response->successful() ? $response->json('limits', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
