<?php

namespace App\Http\Clients;

class UserServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.user_service.url'));
    }

    /**
     * Get user profile by ID.
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
     * Create user profile.
     */
    public function createUserProfile(array $userData): ?array
    {
        try {
            $response = $this->post('/users', $userData);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update user profile.
     */
    public function updateUserProfile(int $userId, array $userData): ?array
    {
        try {
            $response = $this->put("/users/{$userId}", $userData);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user by email.
     */
    public function getUserByEmail(string $email): ?array
    {
        try {
            $response = $this->get('/users/by-email', ['email' => $email]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify user KYC status.
     */
    public function verifyKycStatus(int $userId): bool
    {
        try {
            $response = $this->get("/users/{$userId}/kyc-status");
            return $response->successful() && $response->json('verified', false);
        } catch (\Exception $e) {
            return false;
        }
    }
}

