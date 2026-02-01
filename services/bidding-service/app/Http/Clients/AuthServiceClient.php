<?php

namespace App\Http\Clients;

class AuthServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.auth_service.url'));
    }

    /**
     * Validate user token for bidding operations.
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
     * Check if user has bidding permissions.
     */
    public function hasBiddingPermission(int $userId, string $permission): bool
    {
        try {
            $response = $this->get("/auth/users/{$userId}/permissions/{$permission}");
            return $response->successful() && $response->json('has_permission', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate bid authorization.
     */
    public function validateBidAuth(int $userId, float $amount, int $auctionId): bool
    {
        try {
            $response = $this->post('/auth/validate-bid', [
                'user_id' => $userId,
                'amount' => $amount,
                'auction_id' => $auctionId,
            ]);
            return $response->successful() && $response->json('authorized', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Log bidding activity for audit.
     */
    public function logBiddingActivity(int $userId, string $action, array $data): bool
    {
        try {
            $response = $this->post('/auth/audit/bidding', [
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
     * Get user bidding limits.
     */
    public function getUserBiddingLimits(int $userId): array
    {
        try {
            $response = $this->get("/auth/users/{$userId}/bidding-limits");
            return $response->successful() ? $response->json('limits', []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}

