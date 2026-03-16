<?php

declare(strict_types=1);

namespace Shared\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Bidding Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 * 
 * Modern, type-safe RPC client for bidding management service
 * with comprehensive bid lifecycle and workflow management.
 */
class BiddingServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::BIDDING, $environment);
    }

    /**
     * Place a bid on an auction
     */
    public function placeBid(int $auctionId, int $bidderId, float $amount): RpcResponse
    {
        $request = RpcRequest::post('/bids', [
            'auction_id' => $auctionId,
            'bidder_id' => $bidderId,
            'amount' => $amount,
        ]);
        return $this->call($request);
    }

    /**
     * Get bid by ID
     */
    public function getBid(int $bidId): RpcResponse
    {
        $request = RpcRequest::get("/bids/{$bidId}");
        return $this->call($request);
    }

    /**
     * Get bids for an auction
     */
    public function getAuctionBids(int $auctionId, int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/bids/by-auction', [
            'auction_id' => $auctionId,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    /**
     * Get bids by bidder
     */
    public function getBidsByBidder(int $bidderId, int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/bids/by-bidder', [
            'bidder_id' => $bidderId,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    /**
     * Get highest bid for auction
     */
    public function getHighestBid(int $auctionId): RpcResponse
    {
        $request = RpcRequest::get("/auctions/{$auctionId}/highest-bid");
        return $this->call($request);
    }

    /**
     * Cancel a bid
     */
    public function cancelBid(int $bidId, string $reason): RpcResponse
    {
        $request = RpcRequest::post("/bids/{$bidId}/cancel", [
            'reason' => $reason,
        ]);
        return $this->call($request);
    }

    /**
     * Accept a bid (for reverse auctions)
     */
    public function acceptBid(int $bidId): RpcResponse
    {
        $request = RpcRequest::post("/bids/{$bidId}/accept");
        return $this->call($request);
    }

    /**
     * Reject a bid
     */
    public function rejectBid(int $bidId, string $reason): RpcResponse
    {
        $request = RpcRequest::post("/bids/{$bidId}/reject", [
            'reason' => $reason,
        ]);
        return $this->call($request);
    }

    /**
     * Get bid history for auction
     */
    public function getBidHistory(int $auctionId): RpcResponse
    {
        $request = RpcRequest::get("/auctions/{$auctionId}/bid-history");
        return $this->call($request);
    }

    /**
     * Get bidding statistics
     */
    public function getBiddingStats(int $auctionId): RpcResponse
    {
        $request = RpcRequest::get("/auctions/{$auctionId}/bidding-stats");
        return $this->call($request);
    }

    /**
     * Set auto-bid for bidder
     */
    public function setAutoBid(int $auctionId, int $bidderId, float $maxAmount, float $increment): RpcResponse
    {
        $request = RpcRequest::post('/auto-bids', [
            'auction_id' => $auctionId,
            'bidder_id' => $bidderId,
            'max_amount' => $maxAmount,
            'increment' => $increment,
        ]);
        return $this->call($request);
    }

    /**
     * Cancel auto-bid
     */
    public function cancelAutoBid(int $autoBidId): RpcResponse
    {
        $request = RpcRequest::delete("/auto-bids/{$autoBidId}");
        return $this->call($request);
    }

    /**
     * Get auto-bids for bidder
     */
    public function getAutoBids(int $bidderId): RpcResponse
    {
        $request = RpcRequest::get('/auto-bids/by-bidder', [
            'bidder_id' => $bidderId,
        ]);
        return $this->call($request);
    }

    /**
     * Validate bid amount
     */
    public function validateBid(int $auctionId, float $amount): RpcResponse
    {
        $request = RpcRequest::post('/bids/validate', [
            'auction_id' => $auctionId,
            'amount' => $amount,
        ]);
        return $this->call($request);
    }

    /**
     * Get winning bids for bidder
     */
    public function getWinningBids(int $bidderId): RpcResponse
    {
        $request = RpcRequest::get('/bids/winning', [
            'bidder_id' => $bidderId,
        ]);
        return $this->call($request);
    }

    /**
     * Process bid workflow
     */
    public function processBidWorkflow(int $bidId, string $action, array $data = []): RpcResponse
    {
        $request = RpcRequest::post("/bids/{$bidId}/workflow", [
            'action' => $action,
            'data' => $data,
        ]);
        return $this->call($request);
    }
}
