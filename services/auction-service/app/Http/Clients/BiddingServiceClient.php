<?php

namespace App\Http\Clients;

class BiddingServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.bidding_service.url'));
    }

    /**
     * Get all bids for a specific auction.
     */
    public function getAuctionBids(int $auctionId, array $filters = []): array
    {
        try {
            $query = array_merge(['auction_id' => $auctionId], $filters);
            $response = $this->get('/bids', $query);

            return $response->successful() ? $response->json('data', []) : [];
        } catch (\Exception $e) {
            \Log::error('Failed to get auction bids', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get the highest bid for an auction.
     */
    public function getHighestBid(int $auctionId): ?array
    {
        try {
            $response = $this->get("/auctions/{$auctionId}/highest-bid");

            return $response->successful() ? $response->json('data') : null;
        } catch (\Exception $e) {
            \Log::error('Failed to get highest bid', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get bid count for an auction.
     */
    public function getBidCount(int $auctionId): int
    {
        try {
            $response = $this->get("/auctions/{$auctionId}/bid-count");

            return $response->successful() ? $response->json('count', 0) : 0;
        } catch (\Exception $e) {
            \Log::error('Failed to get bid count', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get a specific bid by ID.
     */
    public function getBid(int $bidId): ?array
    {
        try {
            $response = $this->get("/bids/{$bidId}");

            return $response->successful() ? $response->json('data') : null;
        } catch (\Exception $e) {
            \Log::error('Failed to get bid', [
                'bid_id' => $bidId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Place a new bid on an auction.
     */
    public function placeBid(array $bidData): ?array
    {
        try {
            $response = $this->post('/bids', $bidData);

            return $response->successful() ? $response->json('data') : null;
        } catch (\Exception $e) {
            \Log::error('Failed to place bid', [
                'bid_data' => $bidData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update bid status.
     */
    public function updateBidStatus(int $bidId, string $status, ?string $reason = null): bool
    {
        try {
            $data = ['status' => $status];
            if ($reason) {
                $data['reason'] = $reason;
            }

            $response = $this->put("/bids/{$bidId}/status", $data);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to update bid status', [
                'bid_id' => $bidId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Cancel/withdraw a bid.
     */
    public function cancelBid(int $bidId, ?string $reason = null): bool
    {
        try {
            $data = $reason ? ['reason' => $reason] : [];
            $response = $this->delete("/bids/{$bidId}", $data);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to cancel bid', [
                'bid_id' => $bidId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get bid history for an auction with pagination.
     */
    public function getBidHistory(int $auctionId, int $limit = 50, int $offset = 0): array
    {
        try {
            $query = [
                'auction_id' => $auctionId,
                'limit' => $limit,
                'offset' => $offset,
                'order_by' => 'created_at',
                'order_direction' => 'desc'
            ];

            $response = $this->get('/bids/history', $query);

            return $response->successful() ? $response->json() : [
                'data' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to get bid history', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
            return [
                'data' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset
            ];
        }
    }

    /**
     * Get user's bids for a specific auction.
     */
    public function getUserAuctionBids(int $userId, int $auctionId): array
    {
        try {
            $query = [
                'user_id' => $userId,
                'auction_id' => $auctionId
            ];

            $response = $this->get('/bids', $query);

            return $response->successful() ? $response->json('data', []) : [];
        } catch (\Exception $e) {
            \Log::error('Failed to get user auction bids', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if user has active bids on an auction.
     */
    public function hasActiveBids(int $userId, int $auctionId): bool
    {
        try {
            $query = [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'status' => 'active'
            ];

            $response = $this->get('/bids/check', $query);

            return $response->successful() && $response->json('has_active_bids', false);
        } catch (\Exception $e) {
            \Log::error('Failed to check active bids', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
