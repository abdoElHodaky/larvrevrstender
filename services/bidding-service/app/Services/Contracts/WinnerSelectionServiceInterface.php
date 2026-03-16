<?php

namespace App\Services\Contracts;

/**
 * Winner Selection Service Contract
 * 
 * Defines the interface for auction winner selection services
 */
interface WinnerSelectionServiceInterface
{
    /**
     * Select auction winner
     */
    public function selectAuctionWinner(int $auctionId): array;

    /**
     * Validate winner selection criteria
     */
    public function validateWinnerCriteria(int $auctionId, int $bidderId): array;

    /**
     * Process winner notification
     */
    public function processWinnerNotification(int $auctionId, int $winnerId): array;

    /**
     * Handle winner confirmation
     */
    public function handleWinnerConfirmation(int $auctionId, int $winnerId): array;

    /**
     * Process runner-up selection
     */
    public function processRunnerUpSelection(int $auctionId): array;

    /**
     * Get winner selection history
     */
    public function getWinnerSelectionHistory(int $auctionId): array;

    /**
     * Validate winner eligibility
     */
    public function validateWinnerEligibility(int $bidderId): array;

    /**
     * Handle winner rejection
     */
    public function handleWinnerRejection(int $auctionId, int $winnerId, string $reason): array;

    /**
     * Process alternative winner selection
     */
    public function processAlternativeWinnerSelection(int $auctionId): array;

    /**
     * Generate winner selection report
     */
    public function generateWinnerSelectionReport(int $auctionId): array;

    /**
     * Update winner status
     */
    public function updateWinnerStatus(int $auctionId, int $winnerId, string $status): array;

    /**
     * Get winner statistics
     */
    public function getWinnerStatistics(int $auctionId): array;
}
