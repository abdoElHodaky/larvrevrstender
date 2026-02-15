<?php

namespace App\Workflows;

use App\Activities\FinalizeAuctionActivity;
use App\Activities\DetermineWinnerActivity;
use App\Activities\InitiatePaymentActivity;
use App\Activities\NotifyAuctionEndedActivity;
use Workflow\Activity;
use Workflow\Saga;
use Workflow\SagaInterface;

/**
 * Auction Ending Saga
 * 
 * Orchestrates the complete auction ending process including:
 * - Auction finalization and status update
 * - Winner determination from bidding service
 * - Payment process initiation
 * - Notification delivery to all parties
 * - Comprehensive compensation logic for rollbacks
 */
class AuctionEndingSaga implements SagaInterface
{
    use Saga;

    /**
     * Auction ID being ended
     */
    public int $auctionId;

    /**
     * Winner information (set after determination)
     */
    public ?array $winner = null;

    /**
     * Payment initiation status
     */
    public bool $paymentInitiated = false;

    /**
     * Notification delivery status
     */
    public bool $notificationsSent = false;

    /**
     * Final auction status
     */
    public string $finalStatus = 'ended';

    /**
     * Define the saga workflow
     */
    public function definition(): array
    {
        return [
            // Step 1: Finalize auction and update status
            Activity::make(FinalizeAuctionActivity::class, [
                'auction_id' => $this->auctionId
            ])
                ->withCompensation(function () {
                    return Activity::make(RevertAuctionStatusActivity::class, [
                        'auction_id' => $this->auctionId,
                        'revert_to_status' => 'active'
                    ]);
                }),

            // Step 2: Determine winner from bidding service
            Activity::make(DetermineWinnerActivity::class, [
                'auction_id' => $this->auctionId
            ])
                ->withCompensation(function () {
                    // Winner determination doesn't need compensation
                    // as it's a read-only operation from bidding service
                    return ['status' => 'winner_determination_compensation_skipped'];
                }),

            // Step 3: Initiate payment process (if there's a winner)
            Activity::make(InitiatePaymentActivity::class, [
                'auction_id' => $this->auctionId,
                'winner' => fn() => $this->winner
            ])
                ->withCompensation(function () {
                    if ($this->paymentInitiated && $this->winner) {
                        return Activity::make(CancelPaymentActivity::class, [
                            'auction_id' => $this->auctionId,
                            'winner' => $this->winner
                        ]);
                    }
                    return ['status' => 'no_payment_to_cancel'];
                }),

            // Step 4: Send auction ended notifications
            Activity::make(NotifyAuctionEndedActivity::class, [
                'auction_id' => $this->auctionId,
                'winner' => fn() => $this->winner,
                'final_status' => fn() => $this->finalStatus
            ])
                ->withCompensation(function () {
                    // Notifications don't need compensation as they're informational
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
            'status' => 'auction_ended_successfully',
            'auction_id' => $this->auctionId,
            'winner' => $this->winner,
            'payment_initiated' => $this->paymentInitiated,
            'notifications_sent' => $this->notificationsSent,
            'final_status' => $this->finalStatus,
            'message' => 'Auction ending saga completed successfully'
        ];
    }

    /**
     * Handle saga failure and compensation
     */
    public function onFailure(\Throwable $exception): array
    {
        return [
            'status' => 'auction_ending_failed',
            'error' => $exception->getMessage(),
            'auction_id' => $this->auctionId,
            'winner' => $this->winner,
            'payment_initiated' => $this->paymentInitiated,
            'notifications_sent' => $this->notificationsSent,
            'message' => 'Auction ending saga failed, compensation activities executed'
        ];
    }

    /**
     * Set auction ID for the saga
     */
    public function setAuctionId(int $auctionId): self
    {
        $this->auctionId = $auctionId;
        return $this;
    }

    /**
     * Set winner information
     */
    public function setWinner(?array $winner): self
    {
        $this->winner = $winner;
        return $this;
    }

    /**
     * Mark payment as initiated
     */
    public function markPaymentInitiated(): self
    {
        $this->paymentInitiated = true;
        return $this;
    }

    /**
     * Mark notifications as sent
     */
    public function markNotificationsSent(): self
    {
        $this->notificationsSent = true;
        return $this;
    }

    /**
     * Set final auction status
     */
    public function setFinalStatus(string $status): self
    {
        $this->finalStatus = $status;
        return $this;
    }
}

