<?php

namespace App\Http\Clients;

class UserServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.user_service.url'));
    }

    /**
     * Get user profile for bidding verification.
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
     * Verify user KYC status for bidding eligibility.
     */
    public function verifyKycStatus(int $userId): bool
    {
        try {
            $response = $this->get("/users/{$userId}/kyc-status");

            return $response->successful() && $response->json('kyc_verified', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get user wallet balance for bid validation.
     */
    public function getUserWalletBalance(int $userId): float
    {
        try {
            $response = $this->get("/users/{$userId}/wallet");

            return $response->successful() ? (float) $response->json('balance', 0) : 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Reserve funds for bid.
     */
    public function reserveFunds(int $userId, float $amount, string $bidId): bool
    {
        try {
            $response = $this->post("/users/{$userId}/wallet/reserve", [
                'amount' => $amount,
                'reference' => $bidId,
                'type' => 'bid_reserve',
                'source' => 'bidding_service',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Release reserved funds.
     */
    public function releaseFunds(int $userId, string $bidId): bool
    {
        try {
            $response = $this->post("/users/{$userId}/wallet/release", [
                'reference' => $bidId,
                'source' => 'bidding_service',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get user bidding preferences.
     */
    public function getUserBiddingPreferences(int $userId): array
    {
        try {
            $response = $this->get("/users/{$userId}/bidding-preferences");

            return $response->successful() ? $response->json('preferences', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
