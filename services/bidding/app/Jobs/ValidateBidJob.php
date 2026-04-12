<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;

/**
 * Validate Bid Job with Laravel Fuse Circuit Breaker Protection
 * 
 * This job handles bid validation with built-in circuit breaker protection
 * via Laravel Fuse integration. Critical for auction integrity in the
 * reverse tender platform.
 */
class ValidateBidJob extends BaseQueueJob
{
    /**
     * Bid ID to validate
     */
    public string $bidId;

    /**
     * Tender ID
     */
    public string $tenderId;

    /**
     * Bidder ID
     */
    public string $bidderId;

    /**
     * Bid amount in cents
     */
    public int $bidAmount;

    /**
     * Bid currency
     */
    public string $currency;

    /**
     * Validation rules to apply
     */
    public array $validationRules;

    /**
     * Additional bid metadata
     */
    public array $metadata;

    /**
     * Create a new job instance
     *
     * @param string $bidId
     * @param string $tenderId
     * @param string $bidderId
     * @param int $bidAmount
     * @param string $currency
     * @param array $validationRules
     * @param array $metadata
     */
    public function __construct(
        string $bidId,
        string $tenderId,
        string $bidderId,
        int $bidAmount,
        string $currency,
        array $validationRules = [],
        array $metadata = []
    ) {
        // Initialize with 'bidding' service for circuit breaker configuration
        parent::__construct('bidding');
        
        $this->bidId = $bidId;
        $this->tenderId = $tenderId;
        $this->bidderId = $bidderId;
        $this->bidAmount = $bidAmount;
        $this->currency = $currency;
        $this->validationRules = $validationRules;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job with circuit breaker protection
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Processing bid validation job', [
            'bid_id' => $this->bidId,
            'tender_id' => $this->tenderId,
            'bidder_id' => $this->bidderId,
            'bid_amount' => $this->bidAmount,
            'currency' => $this->currency,
            'service' => $this->getServiceName()
        ]);

        try {
            // Validate the bid
            $validationResult = $this->validateBid();
            
            Log::info('Bid validation completed', [
                'bid_id' => $this->bidId,
                'tender_id' => $this->tenderId,
                'bidder_id' => $this->bidderId,
                'validation_status' => $validationResult['status'],
                'service' => $this->getServiceName()
            ]);

            // Dispatch follow-up jobs based on validation result
            if ($validationResult['status'] === 'valid') {
                // Could dispatch:
                // - ProcessAutoBidJob for auto-bidding scenarios
                // - UpdateTenderStatusJob
                // - SendBidConfirmationJob
            } elseif ($validationResult['status'] === 'invalid') {
                // Could dispatch:
                // - SendBidRejectionNotificationJob
                // - RefundBidDepositJob (if applicable)
            }

        } catch (\Exception $e) {
            Log::error('Failed to validate bid', [
                'bid_id' => $this->bidId,
                'tender_id' => $this->tenderId,
                'bidder_id' => $this->bidderId,
                'service' => $this->getServiceName(),
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger circuit breaker failure handling
            throw $e;
        }
    }

    /**
     * Validate the bid against business rules
     *
     * @return array Validation result
     * @throws \Exception
     */
    private function validateBid(): array
    {
        $validationErrors = [];
        
        // Default validation rules
        $rules = array_merge([
            'min_bid_amount' => true,
            'max_bid_amount' => true,
            'bidder_eligibility' => true,
            'tender_status' => true,
            'bid_timing' => true,
            'duplicate_check' => true
        ], $this->validationRules);

        // Validate minimum bid amount
        if ($rules['min_bid_amount']) {
            $minAmount = $this->getMinimumBidAmount();
            if ($this->bidAmount < $minAmount) {
                $validationErrors[] = "Bid amount {$this->bidAmount} is below minimum {$minAmount}";
            }
        }

        // Validate maximum bid amount
        if ($rules['max_bid_amount']) {
            $maxAmount = $this->getMaximumBidAmount();
            if ($this->bidAmount > $maxAmount) {
                $validationErrors[] = "Bid amount {$this->bidAmount} exceeds maximum {$maxAmount}";
            }
        }

        // Validate bidder eligibility
        if ($rules['bidder_eligibility']) {
            if (!$this->isBidderEligible()) {
                $validationErrors[] = "Bidder {$this->bidderId} is not eligible for this tender";
            }
        }

        // Validate tender status
        if ($rules['tender_status']) {
            if (!$this->isTenderAcceptingBids()) {
                $validationErrors[] = "Tender {$this->tenderId} is not currently accepting bids";
            }
        }

        // Validate bid timing
        if ($rules['bid_timing']) {
            if (!$this->isBidWithinTimeframe()) {
                $validationErrors[] = "Bid submitted outside of allowed timeframe";
            }
        }

        // Check for duplicate bids
        if ($rules['duplicate_check']) {
            if ($this->isDuplicateBid()) {
                $validationErrors[] = "Duplicate bid detected from bidder {$this->bidderId}";
            }
        }

        $isValid = empty($validationErrors);
        
        return [
            'bid_id' => $this->bidId,
            'status' => $isValid ? 'valid' : 'invalid',
            'errors' => $validationErrors,
            'validated_at' => now()->toISOString(),
            'validation_rules_applied' => array_keys(array_filter($rules))
        ];
    }

    /**
     * Get minimum bid amount for the tender
     */
    private function getMinimumBidAmount(): int
    {
        // Example implementation - replace with actual tender service call
        return 100; // $1.00 minimum
    }

    /**
     * Get maximum bid amount for the tender
     */
    private function getMaximumBidAmount(): int
    {
        // Example implementation - replace with actual tender service call
        return 1000000; // $10,000 maximum
    }

    /**
     * Check if bidder is eligible for this tender
     */
    private function isBidderEligible(): bool
    {
        // Example implementation - replace with actual eligibility check
        // Could check: KYC status, account standing, geographic restrictions, etc.
        return true;
    }

    /**
     * Check if tender is currently accepting bids
     */
    private function isTenderAcceptingBids(): bool
    {
        // Example implementation - replace with actual tender status check
        return true;
    }

    /**
     * Check if bid is within allowed timeframe
     */
    private function isBidWithinTimeframe(): bool
    {
        // Example implementation - replace with actual timing validation
        return true;
    }

    /**
     * Check for duplicate bids from the same bidder
     */
    private function isDuplicateBid(): bool
    {
        // Example implementation - replace with actual duplicate check
        return false;
    }

    /**
     * Handle job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function onFailure(\Throwable $exception): void
    {
        Log::critical('Bid validation job failed permanently', [
            'bid_id' => $this->bidId,
            'tender_id' => $this->tenderId,
            'bidder_id' => $this->bidderId,
            'service' => $this->getServiceName(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Critical: Bid validation failure affects auction integrity
        // Could trigger:
        // - Alert to auction management team
        // - Bidder notification about validation failure
        // - Tender status update if needed
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return array_merge(parent::tags(), [
            'bid:' . $this->bidId,
            'tender:' . $this->tenderId,
            'bidder:' . $this->bidderId,
            'amount:' . $this->bidAmount
        ]);
    }
}
