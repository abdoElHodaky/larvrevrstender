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

    /**
     * Validate JWT token and get user information.
     */
    public function validateJwtToken(string $token): ?array
    {
        try {
            $response = $this->post('/auth/validate-jwt', [
                'token' => $token
            ]);

            return $response->successful() ? $response->json('user') : null;
        } catch (\Exception $e) {
            \Log::error('Failed to validate JWT token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get user details by ID.
     */
    public function getUser(int $userId): ?array
    {
        try {
            $response = $this->get("/auth/users/{$userId}");

            return $response->successful() ? $response->json('user') : null;
        } catch (\Exception $e) {
            \Log::error('Failed to get user details', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
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
            \Log::error('Failed to check user permission', [
                'user_id' => $userId,
                'permission' => $permission,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user has any of the specified roles.
     */
    public function hasRole(int $userId, array $roles): bool
    {
        try {
            $response = $this->post("/auth/users/{$userId}/check-roles", [
                'roles' => $roles
            ]);

            return $response->successful() && $response->json('has_role', false);
        } catch (\Exception $e) {
            \Log::error('Failed to check user roles', [
                'user_id' => $userId,
                'roles' => $roles,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate auction creation authorization.
     */
    public function validateAuctionCreation(int $userId, array $auctionData): array
    {
        try {
            $response = $this->post('/auth/validate-auction-creation', [
                'user_id' => $userId,
                'auction_data' => $auctionData
            ]);

            if ($response->successful()) {
                return [
                    'authorized' => $response->json('authorized', false),
                    'reason' => $response->json('reason'),
                    'limits' => $response->json('limits', [])
                ];
            }

            return ['authorized' => false, 'reason' => 'Service unavailable'];
        } catch (\Exception $e) {
            \Log::error('Failed to validate auction creation', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return ['authorized' => false, 'reason' => 'Authorization check failed'];
        }
    }

    /**
     * Check if user can access/modify specific auction.
     */
    public function canAccessAuction(int $userId, int $auctionId, string $action = 'view'): bool
    {
        try {
            $response = $this->post('/auth/check-auction-access', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'action' => $action
            ]);

            return $response->successful() && $response->json('can_access', false);
        } catch (\Exception $e) {
            \Log::error('Failed to check auction access', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate user eligibility for bidding on auction.
     */
    public function validateBiddingEligibility(int $userId, int $auctionId): array
    {
        try {
            $response = $this->post('/auth/validate-bidding-eligibility', [
                'user_id' => $userId,
                'auction_id' => $auctionId
            ]);

            if ($response->successful()) {
                return [
                    'eligible' => $response->json('eligible', false),
                    'reason' => $response->json('reason'),
                    'restrictions' => $response->json('restrictions', [])
                ];
            }

            return ['eligible' => false, 'reason' => 'Service unavailable'];
        } catch (\Exception $e) {
            \Log::error('Failed to validate bidding eligibility', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
            return ['eligible' => false, 'reason' => 'Eligibility check failed'];
        }
    }

    /**
     * Log auction activity for audit trail.
     */
    public function logAuctionActivity(int $userId, string $action, array $data): bool
    {
        try {
            $response = $this->post('/auth/audit/auction', [
                'user_id' => $userId,
                'action' => $action,
                'data' => $data,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to log auction activity', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
