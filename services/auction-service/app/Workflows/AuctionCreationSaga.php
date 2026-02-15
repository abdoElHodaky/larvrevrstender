<?php

namespace App\Workflows;

use App\Activities\ValidateAuctionActivity;
use App\Activities\CreateAuctionActivity;
use App\Activities\InitializeBiddingActivity;
use App\Activities\NotifyAuctionCreatedActivity;
use Workflow\Activity;
use Workflow\Saga;
use Workflow\SagaInterface;

/**
 * Auction Creation Saga
 * 
 * Orchestrates the complete auction creation process including:
 * - Auction parameter validation
 * - Auction record creation
 * - Bidding service initialization
 * - Notification delivery
 * - Comprehensive compensation logic for rollbacks
 */
class AuctionCreationSaga implements SagaInterface
{
    use Saga;

    /**
     * Auction data for the saga
     */
    public array $auctionData;

    /**
     * Created auction ID (set after successful creation)
     */
    public ?int $auctionId = null;

    /**
     * Bidding service initialization status
     */
    public bool $biddingInitialized = false;

    /**
     * Notification delivery status
     */
    public bool $notificationSent = false;

    /**
     * Define the saga workflow
     */
    public function definition(): array
    {
        return [
            // Step 1: Validate auction parameters
            Activity::make(ValidateAuctionActivity::class, $this->auctionData)
                ->withCompensation(function () {
                    // No compensation needed for validation
                    return ['status' => 'validation_skipped'];
                }),

            // Step 2: Create auction record
            Activity::make(CreateAuctionActivity::class, $this->auctionData)
                ->withCompensation(function () {
                    if ($this->auctionId) {
                        return Activity::make(DeleteAuctionActivity::class, [
                            'auction_id' => $this->auctionId
                        ]);
                    }
                    return ['status' => 'no_auction_to_delete'];
                }),

            // Step 3: Initialize bidding service for this auction
            Activity::make(InitializeBiddingActivity::class, [
                'auction_id' => fn() => $this->auctionId,
                'auction_data' => $this->auctionData
            ])
                ->withCompensation(function () {
                    if ($this->biddingInitialized && $this->auctionId) {
                        return Activity::make(CleanupBiddingActivity::class, [
                            'auction_id' => $this->auctionId
                        ]);
                    }
                    return ['status' => 'no_bidding_cleanup_needed'];
                }),

            // Step 4: Send auction creation notifications
            Activity::make(NotifyAuctionCreatedActivity::class, [
                'auction_id' => fn() => $this->auctionId,
                'auction_data' => $this->auctionData
            ])
                ->withCompensation(function () {
                    // Notifications don't need compensation as they're informational
                    // The notification service handles its own retry logic
                    return ['status' => 'notification_compensation_skipped'];
                }),
        ];
    }

    /**
     * Handle successful saga completion
     */
    public function onSuccess(): array
    {
        return [
            'status' => 'auction_created_successfully',
            'auction_id' => $this->auctionId,
            'bidding_initialized' => $this->biddingInitialized,
            'notification_sent' => $this->notificationSent,
            'message' => 'Auction creation saga completed successfully'
        ];
    }

    /**
     * Handle saga failure and compensation
     */
    public function onFailure(\Throwable $exception): array
    {
        return [
            'status' => 'auction_creation_failed',
            'error' => $exception->getMessage(),
            'auction_id' => $this->auctionId,
            'bidding_initialized' => $this->biddingInitialized,
            'notification_sent' => $this->notificationSent,
            'message' => 'Auction creation saga failed, compensation activities executed'
        ];
    }

    /**
     * Set auction data for the saga
     */
    public function setAuctionData(array $auctionData): self
    {
        $this->auctionData = $auctionData;
        return $this;
    }

    /**
     * Set the created auction ID
     */
    public function setAuctionId(int $auctionId): self
    {
        $this->auctionId = $auctionId;
        return $this;
    }

    /**
     * Mark bidding as initialized
     */
    public function markBiddingInitialized(): self
    {
        $this->biddingInitialized = true;
        return $this;
    }

    /**
     * Mark notification as sent
     */
    public function markNotificationSent(): self
    {
        $this->notificationSent = true;
        return $this;
    }
}

