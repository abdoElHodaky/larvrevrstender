<?php

namespace App\Http\Clients;

class UserServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.user_service.url'));
    }

    /**
     * Get user profile information.
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
     * Get user payment methods.
     */
    public function getUserPaymentMethods(int $userId): array
    {
        try {
            $response = $this->get("/users/{$userId}/payment-methods");

            return $response->successful() ? $response->json('payment_methods', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Update user payment preferences.
     */
    public function updatePaymentPreferences(int $userId, array $preferences): bool
    {
        try {
            $response = $this->put("/users/{$userId}/payment-preferences", $preferences);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verify user KYC status for payments.
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
     * Get user billing address.
     */
    public function getUserBillingAddress(int $userId): ?array
    {
        try {
            $response = $this->get("/users/{$userId}/billing-address");

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update user wallet balance.
     */
    public function updateWalletBalance(int $userId, float $amount, string $type = 'credit'): bool
    {
        try {
            $response = $this->post("/users/{$userId}/wallet", [
                'amount' => $amount,
                'type' => $type,
                'source' => 'payment_service',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
