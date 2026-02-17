<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Bidding Service
 * 
 * Provides RPC-based communication with the bidding service for
 * auction initialization, bid management, and bidding operations.
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
     * Initialize auction in bidding service
     *
     * @param array $auctionData Auction initialization data
     * @return array RPC response
     */
    public function initializeAuction(array $auctionData): array
    {
        return $this->call('auction.initialize', $auctionData);
    }
    
    /**
     * Get auction bids with optional filtering
     *
     * @param int $auctionId Auction ID
     * @param array $filters Optional filters (status, user_id, etc.)
     * @return array RPC response with bids data
     */
    public function getAuctionBids(int $auctionId, array $filters = []): array
    {
        return $this->call('bid.getByAuction', [
            'auction_id' => $auctionId,
            'filters' => $filters,
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
        return $this->call('auction.validateBidEligibility', [
            'user_id' => $userId,
            'auction_id' => $auctionId,
            'bid_amount' => $bidAmount,
        ]);
    }
    
    /**
     * Update highest bid information for auction
     *
     * @param int $auctionId Auction ID
     * @param array $bidData Highest bid data
     * @return array RPC response
     */
    public function updateHighestBid(int $auctionId, array $bidData): array
    {
        return $this->call('auction.updateHighestBid', [
            'auction_id' => $auctionId,
            'bid_data' => $bidData,
        ]);
    }
    
    /**
     * Get auction status from bidding service
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction status
     */
    public function getAuctionStatus(int $auctionId): array
    {
        return $this->call('auction.getStatus', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Close auction in bidding service
     *
     * @param int $auctionId Auction ID
     * @param array $closureData Closure data (winner, final_price, etc.)
     * @return array RPC response
     */
    public function closeAuction(int $auctionId, array $closureData = []): array
    {
        return $this->call('auction.close', [
            'auction_id' => $auctionId,
            'closure_data' => $closureData,
        ]);
    }
    
    /**
     * Batch operation: Get multiple auction statuses
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getMultipleAuctionStatuses(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'auction.getStatus',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get highest bids for multiple auctions
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
}

