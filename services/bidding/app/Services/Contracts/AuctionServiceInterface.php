<?php

namespace App\Services\Contracts;

/**
 * Auction Service Contract
 * 
 * Defines the interface for auction management services
 */
interface AuctionServiceInterface
{
    /**
     * Create new auction
     */
    public function createAuction(array $auctionData): array;

    /**
     * Update auction details
     */
    public function updateAuction(int $auctionId, array $auctionData): array;

    /**
     * Get auction details
     */
    public function getAuction(int $auctionId): array;

    /**
     * Get active auctions
     */
    public function getActiveAuctions(array $filters = []): array;

    /**
     * Start auction
     */
    public function startAuction(int $auctionId): array;

    /**
     * End auction
     */
    public function endAuction(int $auctionId): array;

    /**
     * Cancel auction
     */
    public function cancelAuction(int $auctionId, string $reason = null): array;

    /**
     * Get auction status
     */
    public function getAuctionStatus(int $auctionId): array;

    /**
     * Extend auction time
     */
    public function extendAuction(int $auctionId, int $extensionMinutes): array;

    /**
     * Get auction participants
     */
    public function getAuctionParticipants(int $auctionId): array;

    /**
     * Validate auction data
     */
    public function validateAuctionData(array $auctionData): array;

    /**
     * Get auction statistics
     */
    public function getAuctionStatistics(int $auctionId): array;
}
