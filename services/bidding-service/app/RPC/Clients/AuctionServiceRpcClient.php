<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Auction Service
 * 
 * Provides RPC-based communication with the auction service for
 * auction data retrieval and status updates from bidding service.
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
     * Get auction details
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction data
     */
    public function getAuction(int $auctionId): array
    {
        return $this->call('auction.get', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get multiple auctions
     *
     * @param array $auctionIds Array of auction IDs
     * @return array RPC response with auctions data
     */
    public function getMultipleAuctions(array $auctionIds): array
    {
        return $this->call('auction.getMultiple', [
            'auction_ids' => $auctionIds,
        ]);
    }
    
    /**
     * Update auction status
     *
     * @param int $auctionId Auction ID
     * @param string $status New status
     * @param array $metadata Additional metadata
     * @return array RPC response
     */
    public function updateAuctionStatus(int $auctionId, string $status, array $metadata = []): array
    {
        return $this->call('auction.updateStatus', [
            'auction_id' => $auctionId,
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }
    
    /**
     * Update auction highest bid information
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
     * Notify auction of bid placement
     *
     * @param int $auctionId Auction ID
     * @param array $bidData Bid data
     * @return array RPC response
     */
    public function notifyBidPlaced(int $auctionId, array $bidData): array
    {
        return $this->call('auction.notifyBidPlaced', [
            'auction_id' => $auctionId,
            'bid_data' => $bidData,
        ]);
    }
    
    /**
     * Check if auction is active and accepting bids
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction status
     */
    public function isAuctionActive(int $auctionId): array
    {
        return $this->call('auction.isActive', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get auction configuration and rules
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction configuration
     */
    public function getAuctionConfig(int $auctionId): array
    {
        return $this->call('auction.getConfig', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Notify auction of closure/completion
     *
     * @param int $auctionId Auction ID
     * @param array $closureData Closure data (winner, final_price, etc.)
     * @return array RPC response
     */
    public function notifyAuctionClosed(int $auctionId, array $closureData): array
    {
        return $this->call('auction.notifyClosed', [
            'auction_id' => $auctionId,
            'closure_data' => $closureData,
        ]);
    }
    
    /**
     * Get auction participants (bidders)
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with participants data
     */
    public function getAuctionParticipants(int $auctionId): array
    {
        return $this->call('auction.getParticipants', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Batch operation: Check multiple auctions active status
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function batchCheckAuctionsActive(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'auction.isActive',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

