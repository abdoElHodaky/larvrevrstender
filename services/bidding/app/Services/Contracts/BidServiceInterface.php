<?php

namespace App\Services\Contracts;

/**
 * Bid Service Contract
 * 
 * Defines the interface for bid management services
 */
interface BidServiceInterface
{
    /**
     * Place a bid on an auction
     */
    public function placeBid(int $auctionId, int $bidderId, float $amount, array $bidData = []): array;

    /**
     * Get bid details
     */
    public function getBid(int $bidId): array;

    /**
     * Get bids for an auction
     */
    public function getAuctionBids(int $auctionId, array $filters = []): array;

    /**
     * Get bids by bidder
     */
    public function getBidderBids(int $bidderId, array $filters = []): array;

    /**
     * Update bid status
     */
    public function updateBidStatus(int $bidId, string $status, array $metadata = []): array;

    /**
     * Cancel bid
     */
    public function cancelBid(int $bidId, string $reason = null): array;

    /**
     * Validate bid data
     */
    public function validateBid(int $auctionId, int $bidderId, float $amount): array;

    /**
     * Get highest bid for auction
     */
    public function getHighestBid(int $auctionId): array;

    /**
     * Get bid history for auction
     */
    public function getBidHistory(int $auctionId): array;

    /**
     * Check if user can bid
     */
    public function canUserBid(int $auctionId, int $bidderId): array;

    /**
     * Get bid statistics
     */
    public function getBidStatistics(int $auctionId): array;
}
