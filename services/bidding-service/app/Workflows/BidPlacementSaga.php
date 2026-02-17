<?php

namespace App\Workflows;

use App\Workflows\Activities\ReserveFundsActivity;
use App\Workflows\Activities\ValidateAuctionActivity;
use App\Workflows\Activities\CreateBidActivity;
use App\Workflows\Activities\UpdateAuctionActivity;
use App\Workflows\Compensation\ReleaseFundsActivity;
use App\Workflows\Compensation\CancelBidActivity;
use App\Workflows\Compensation\RestoreAuctionActivity;
use App\Models\Bid;
use Workflow\Workflow;
use Workflow\ActivityStub;
use Workflow\SagaCompensationOptions;
use Illuminate\Support\Facades\Log;

/**
 * Bid Placement Saga Workflow
 * 
 * Orchestrates the bid placement process across multiple services with compensation.
 * This saga ensures consistency when placing bids that involve fund reservation,
 * auction validation, bid creation, and auction updates.
 * 
 * Saga Steps:
 * 1. Reserve funds from bidder's account
 * 2. Validate auction eligibility and timing
 * 3. Create bid record in database
 * 4. Update auction with new highest bid
 * 
 * Compensation (if any step fails):
 * - Release reserved funds
 * - Cancel created bid
 * - Restore auction's previous state
 */
class BidPlacementSaga extends Workflow
{
    /**
     * Start the bid placement saga workflow
     *
     * @param array $input Workflow input data
     * @return mixed Workflow execution result
     */
    public static function start(array $input = [])
    {
        return parent::start($input);
    }

    /**
     * Execute the bid placement saga
     *
     * @param array $bidData Bid placement data
     * @return array Saga execution result
     */
    public function execute(array $bidData): array
    {
        $sagaId = $this->getWorkflowId();
        
        Log::info("BidPlacementSaga started", [
            'saga_id' => $sagaId,
            'auction_id' => $bidData['auction_id'],
            'user_id' => $bidData['user_id'],
            'amount' => $bidData['amount']
        ]);
        
        try {
            // Step 1: Reserve funds with compensation
            $reservationResult = ActivityStub::make(ReserveFundsActivity::class, [
                'compensation' => [
                    'activity' => ReleaseFundsActivity::class,
                    'options' => SagaCompensationOptions::new()
                        ->withRetryPolicy(['maximumAttempts' => 3])
                        ->withStartToCloseTimeout(60)
                ]
            ])->execute($bidData);
            
            if (!$reservationResult['success']) {
                throw new \Exception("Fund reservation failed: " . $reservationResult['error']);
            }
            
            // Add reservation ID to bid data for subsequent steps
            $bidData['reservation_id'] = $reservationResult['data']['reservation_id'];
            
            Log::info("BidPlacementSaga: Funds reserved", [
                'saga_id' => $sagaId,
                'reservation_id' => $reservationResult['data']['reservation_id']
            ]);
            
            // Step 2: Validate auction (no compensation needed - read-only operation)
            $validationResult = ActivityStub::make(ValidateAuctionActivity::class)->execute($bidData);
            
            if (!$validationResult['success']) {
                throw new \Exception("Auction validation failed: " . $validationResult['error']);
            }
            
            Log::info("BidPlacementSaga: Auction validated", [
                'saga_id' => $sagaId,
                'auction_id' => $bidData['auction_id']
            ]);
            
            // Step 3: Create bid with compensation
            $bidCreationResult = ActivityStub::make(CreateBidActivity::class, [
                'compensation' => [
                    'activity' => CancelBidActivity::class,
                    'options' => SagaCompensationOptions::new()
                        ->withRetryPolicy(['maximumAttempts' => 3])
                        ->withStartToCloseTimeout(60)
                ]
            ])->execute($bidData);
            
            if (!$bidCreationResult['success']) {
                throw new \Exception("Bid creation failed: " . $bidCreationResult['error']);
            }
            
            // Add bid ID to data for subsequent steps
            $bidData['bid_id'] = $bidCreationResult['data']['bid_id'];
            
            Log::info("BidPlacementSaga: Bid created", [
                'saga_id' => $sagaId,
                'bid_id' => $bidCreationResult['data']['bid_id']
            ]);
            
            // Step 4: Update auction with compensation
            $auctionUpdateResult = ActivityStub::make(UpdateAuctionActivity::class, [
                'compensation' => [
                    'activity' => RestoreAuctionActivity::class,
                    'options' => SagaCompensationOptions::new()
                        ->withRetryPolicy(['maximumAttempts' => 3])
                        ->withStartToCloseTimeout(60)
                ]
            ])->execute($bidData);
            
            if (!$auctionUpdateResult['success']) {
                throw new \Exception("Auction update failed: " . $auctionUpdateResult['error']);
            }
            
            Log::info("BidPlacementSaga: Auction updated", [
                'saga_id' => $sagaId,
                'auction_id' => $bidData['auction_id']
            ]);
            
            // Step 5: Activate the bid (mark as active instead of pending)
            $this->activateBid($bidData['bid_id']);
            
            Log::info("BidPlacementSaga completed successfully", [
                'saga_id' => $sagaId,
                'bid_id' => $bidData['bid_id'],
                'auction_id' => $bidData['auction_id']
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'bid_id' => $bidData['bid_id'],
                    'auction_id' => $bidData['auction_id'],
                    'amount' => $bidData['amount'],
                    'reservation_id' => $bidData['reservation_id'],
                    'anti_sniping_triggered' => $auctionUpdateResult['data']['anti_sniping_triggered'] ?? false,
                    'saga_id' => $sagaId
                ],
                'message' => 'Bid placed successfully via saga'
            ];
            
        } catch (\Exception $e) {
            Log::error("BidPlacementSaga failed", [
                'saga_id' => $sagaId,
                'error' => $e->getMessage(),
                'auction_id' => $bidData['auction_id'] ?? null,
                'user_id' => $bidData['user_id'] ?? null
            ]);
            
            // The workflow framework will automatically trigger compensation
            throw $e;
        }
    }
    
    /**
     * Activate the bid after successful saga completion
     */
    private function activateBid(int $bidId): void
    {
        try {
            $bid = Bid::find($bidId);
            if ($bid && $bid->status === 'pending') {
                $bid->update([
                    'status' => 'active',
                    'metadata' => array_merge($bid->metadata ?? [], [
                        'activated_by_saga' => true,
                        'activated_at' => now()->toISOString()
                    ])
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to activate bid after saga completion", [
                'bid_id' => $bidId,
                'error' => $e->getMessage()
            ]);
            // Don't throw - saga was successful, this is just a status update
        }
    }
}
