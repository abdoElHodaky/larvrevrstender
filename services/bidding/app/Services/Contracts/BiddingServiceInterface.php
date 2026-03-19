<?php

namespace App\Services\Contracts;

/**
 * Bidding Service Contract
 * 
 * Defines the interface for bidding process management services
 */
interface BiddingServiceInterface
{
    /**
     * Initialize bidding session
     */
    public function initializeBiddingSession(int $auctionId, int $userId): array;

    /**
     * Process bid submission
     */
    public function processBidSubmission(int $auctionId, int $userId, float $bidAmount, array $bidData = []): array;

    /**
     * Get current bidding status
     */
    public function getBiddingStatus(int $auctionId): array;

    /**
     * Get user bidding history
     */
    public function getUserBiddingHistory(int $userId, array $filters = []): array;

    /**
     * Validate bidding eligibility
     */
    public function validateBiddingEligibility(int $auctionId, int $userId): array;

    /**
     * Get minimum bid amount
     */
    public function getMinimumBidAmount(int $auctionId): array;

    /**
     * Handle bid increment
     */
    public function handleBidIncrement(int $auctionId, float $currentBid): array;

    /**
     * Process automatic bidding
     */
    public function processAutomaticBidding(int $auctionId, int $userId, float $maxBid): array;

    /**
     * Get bidding leaderboard
     */
    public function getBiddingLeaderboard(int $auctionId): array;

    /**
     * Handle bid notifications
     */
    public function handleBidNotifications(int $auctionId, int $bidId): array;

    /**
     * Calculate bid fees
     */
    public function calculateBidFees(float $bidAmount): array;

    /**
     * Get bidding analytics
     */
    public function getBiddingAnalytics(int $auctionId): array;
}
