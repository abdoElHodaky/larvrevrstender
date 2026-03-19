<?php

namespace App\Services\Contracts;

/**
 * Bid Evaluation Service Contract
 * 
 * Defines the interface for bid evaluation and scoring services
 */
interface BidEvaluationServiceInterface
{
    /**
     * Evaluate bid against auction criteria
     */
    public function evaluateBid(int $bidId, array $evaluationCriteria = []): array;

    /**
     * Score bid based on multiple factors
     */
    public function scoreBid(int $bidId): array;

    /**
     * Compare bids for ranking
     */
    public function compareBids(array $bidIds): array;

    /**
     * Validate bid meets minimum requirements
     */
    public function validateBidRequirements(int $bidId): array;

    /**
     * Get bid evaluation history
     */
    public function getBidEvaluationHistory(int $bidId): array;

    /**
     * Calculate bid competitiveness
     */
    public function calculateBidCompetitiveness(int $bidId, int $auctionId): array;

    /**
     * Evaluate bidder credibility
     */
    public function evaluateBidderCredibility(int $bidderId): array;

    /**
     * Get evaluation metrics
     */
    public function getEvaluationMetrics(int $auctionId): array;

    /**
     * Process bid quality assessment
     */
    public function processBidQualityAssessment(int $bidId): array;

    /**
     * Generate bid evaluation report
     */
    public function generateBidEvaluationReport(int $auctionId): array;

    /**
     * Update evaluation criteria
     */
    public function updateEvaluationCriteria(int $auctionId, array $criteria): array;
}
