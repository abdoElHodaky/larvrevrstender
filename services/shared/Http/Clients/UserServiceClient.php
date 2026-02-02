<?php

namespace Shared\Http\Clients;

use Psr\Http\Message\ResponseInterface;

class UserServiceClient extends BaseServiceClient
{
    /**
     * Get user profile by ID.
     */
    public function getUserProfile(int $userId): ?array
    {
        $response = $this->get("/users/{$userId}");

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Update user profile.
     */
    public function updateUserProfile(int $userId, array $data): ?array
    {
        $response = $this->put("/users/{$userId}", $data);

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Get user wallet information.
     */
    public function getUserWallet(int $userId): ?array
    {
        $response = $this->get("/users/{$userId}/wallet");

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Update user wallet balance.
     */
    public function updateWalletBalance(int $userId, float $amount, string $type = 'credit', string $description = ''): ?array
    {
        $response = $this->post("/users/{$userId}/wallet/transactions", [
            'amount' => $amount,
            'type' => $type, // 'credit' or 'debit'
            'description' => $description,
        ]);

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Reserve funds in user wallet.
     */
    public function reserveFunds(int $userId, float $amount, string $reference): ?array
    {
        $response = $this->post("/users/{$userId}/wallet/reserve", [
            'amount' => $amount,
            'reference' => $reference,
        ]);

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Release reserved funds.
     */
    public function releaseFunds(int $userId, string $reservationId): bool
    {
        $response = $this->post("/users/{$userId}/wallet/release", [
            'reservation_id' => $reservationId,
        ]);

        return $this->isSuccessful($response);
    }

    /**
     * Get user preferences.
     */
    public function getUserPreferences(int $userId): ?array
    {
        $response = $this->get("/users/{$userId}/preferences");

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Update user preferences.
     */
    public function updateUserPreferences(int $userId, array $preferences): bool
    {
        $response = $this->put("/users/{$userId}/preferences", $preferences);

        return $this->isSuccessful($response);
    }

    /**
     * Get user KYC status.
     */
    public function getKycStatus(int $userId): ?array
    {
        $response = $this->get("/users/{$userId}/kyc");

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Update user KYC status.
     */
    public function updateKycStatus(int $userId, string $status, array $metadata = []): bool
    {
        $response = $this->put("/users/{$userId}/kyc", [
            'status' => $status,
            'metadata' => $metadata,
        ]);

        return $this->isSuccessful($response);
    }

    /**
     * Get users by criteria for bulk operations.
     */
    public function getUsersByCriteria(array $criteria): ?array
    {
        $response = $this->post('/users/search', $criteria);

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Get user notification preferences.
     */
    public function getNotificationPreferences(int $userId): ?array
    {
        $response = $this->get("/users/{$userId}/notification-preferences");

        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    /**
     * Update user notification preferences.
     */
    public function updateNotificationPreferences(int $userId, array $preferences): bool
    {
        $response = $this->put("/users/{$userId}/notification-preferences", $preferences);

        return $this->isSuccessful($response);
    }
}
