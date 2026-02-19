<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Auction Service
 * 
 * Provides RPC-based communication with the auction service for
 * auction management, listing, and lifecycle operations.
 */
class AuctionServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('auction-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Create a new auction
     *
     * @param array $auctionData Auction creation data
     * @return array RPC response
     */
    public function createAuction(array $auctionData): array
    {
        return $this->call('auction.create', $auctionData);
    }
    
    /**
     * Get auction details by ID
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction details
     */
    public function getAuction(int $auctionId): array
    {
        return $this->call('auction.get', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Update auction details
     *
     * @param int $auctionId Auction ID
     * @param array $updateData Data to update
     * @return array RPC response
     */
    public function updateAuction(int $auctionId, array $updateData): array
    {
        return $this->call('auction.update', [
            'auction_id' => $auctionId,
            'data' => $updateData,
        ]);
    }
    
    /**
     * Delete/cancel auction
     *
     * @param int $auctionId Auction ID
     * @param string|null $reason Cancellation reason
     * @return array RPC response
     */
    public function deleteAuction(int $auctionId, ?string $reason = null): array
    {
        $params = ['auction_id' => $auctionId];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('auction.delete', $params);
    }
    
    /**
     * Get auctions with filtering and pagination
     *
     * @param array $filters Filters (status, category, user_id, etc.)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param string $orderBy Field to order by
     * @param string $orderDirection Order direction (asc/desc)
     * @return array RPC response with paginated auctions
     */
    public function getAuctions(
        array $filters = [],
        int $limit = 20,
        int $offset = 0,
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array {
        return $this->call('auction.list', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => $orderBy,
            'order_direction' => $orderDirection,
        ]);
    }
    
    /**
     * Start auction
     *
     * @param int $auctionId Auction ID
     * @return array RPC response
     */
    public function startAuction(int $auctionId): array
    {
        return $this->call('auction.start', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * End auction
     *
     * @param int $auctionId Auction ID
     * @param array $endData End auction data (winner, final_price, etc.)
     * @return array RPC response
     */
    public function endAuction(int $auctionId, array $endData = []): array
    {
        return $this->call('auction.end', [
            'auction_id' => $auctionId,
            'end_data' => $endData,
        ]);
    }
    
    /**
     * Get auction statistics
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction statistics
     */
    public function getAuctionStatistics(int $auctionId): array
    {
        return $this->call('auction.getStatistics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Search auctions by criteria
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @param int $limit Number of results
     * @param int $offset Pagination offset
     * @return array RPC response with search results
     */
    public function searchAuctions(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('auction.search', [
            'query' => $query,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get user's auctions
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with user auctions
     */
    public function getUserAuctions(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('auction.getUserAuctions', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Validate auction data
     *
     * @param array $auctionData Auction data to validate
     * @return array RPC response with validation results
     */
    public function validateAuctionData(array $auctionData): array
    {
        return $this->call('auction.validate', $auctionData);
    }
    
    /**
     * Get auction categories
     *
     * @return array RPC response with categories
     */
    public function getCategories(): array
    {
        return $this->call('auction.getCategories');
    }
    
    /**
     * Batch operation: Get multiple auctions
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getMultipleAuctions(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'auction.get',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

