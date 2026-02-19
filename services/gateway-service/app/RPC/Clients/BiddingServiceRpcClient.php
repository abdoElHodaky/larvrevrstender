<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Bidding Service
 * 
 * Provides RPC-based communication with the bidding service for
 * bid management, placement, and bidding operations.
 */
class BiddingServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('bidding-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Place a new bid
     *
     * @param array $bidData Bid data (auction_id, user_id, amount, etc.)
     * @return array RPC response with bid placement result
     */
    public function placeBid(array $bidData): array
    {
        return $this->call('bid.place', $bidData);
    }
    
    /**
     * Get bid details by ID
     *
     * @param int $bidId Bid ID
     * @return array RPC response with bid details
     */
    public function getBid(int $bidId): array
    {
        return $this->call('bid.get', [
            'bid_id' => $bidId,
        ]);
    }
    
    /**
     * Get auction bids with optional filtering
     *
     * @param int $auctionId Auction ID
     * @param array $filters Optional filters (status, user_id, etc.)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array RPC response with bids data
     */
    public function getAuctionBids(int $auctionId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->call('bid.getByAuction', [
            'auction_id' => $auctionId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get highest bid for auction
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with highest bid data
     */
    public function getHighestBid(int $auctionId): array
    {
        return $this->call('bid.getHighest', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get user's bids
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with user bids
     */
    public function getUserBids(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('bid.getUserBids', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Update bid status
     *
     * @param int $bidId Bid ID
     * @param string $status New status (active, withdrawn, expired, etc.)
     * @param string|null $reason Optional reason for status change
     * @return array RPC response
     */
    public function updateBidStatus(int $bidId, string $status, ?string $reason = null): array
    {
        $params = [
            'bid_id' => $bidId,
            'status' => $status,
        ];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('bid.updateStatus', $params);
    }
    
    /**
     * Cancel/withdraw a bid
     *
     * @param int $bidId Bid ID
     * @param string|null $reason Optional reason for cancellation
     * @return array RPC response
     */
    public function cancelBid(int $bidId, ?string $reason = null): array
    {
        $params = ['bid_id' => $bidId];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('bid.cancel', $params);
    }
    
    /**
     * Get bid history with pagination
     *
     * @param int $auctionId Auction ID
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param string $orderBy Field to order by
     * @param string $orderDirection Order direction (asc/desc)
     * @return array RPC response with paginated bid history
     */
    public function getBidHistory(
        int $auctionId, 
        int $limit = 50, 
        int $offset = 0,
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array {
        return $this->call('bid.getHistory', [
            'auction_id' => $auctionId,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => $orderBy,
            'order_direction' => $orderDirection,
        ]);
    }
    
    /**
     * Check if user has active bids for auction
     *
     * @param int $userId User ID
     * @param int $auctionId Auction ID
     * @return array RPC response with active bid status
     */
    public function hasActiveBids(int $userId, int $auctionId): array
    {
        return $this->call('bid.checkActive', [
            'user_id' => $userId,
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get bid statistics for auction
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with bid statistics
     */
    public function getBidStatistics(int $auctionId): array
    {
        return $this->call('bid.getStatistics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Validate bid eligibility for user
     *
     * @param int $userId User ID
     * @param int $auctionId Auction ID
     * @param float $bidAmount Proposed bid amount
     * @return array RPC response with eligibility validation
     */
    public function validateBidEligibility(int $userId, int $auctionId, float $bidAmount): array
    {
        return $this->call('bid.validateEligibility', [
            'user_id' => $userId,
            'auction_id' => $auctionId,
            'bid_amount' => $bidAmount,
        ]);
    }
    
    /**
     * Get bid increments for auction
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with bid increment rules
     */
    public function getBidIncrements(int $auctionId): array
    {
        return $this->call('bid.getIncrements', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Batch operation: Get multiple auction highest bids
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getMultipleHighestBids(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'bid.getHighest',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get bid statistics for multiple auctions
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getMultipleBidStatistics(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'bid.getStatistics',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

