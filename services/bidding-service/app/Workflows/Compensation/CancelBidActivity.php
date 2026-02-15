<?php

namespace App\Workflows\Compensation;

use App\Models\Bid;
use App\Workflows\Activities\BaseRpcActivity;
use Illuminate\Support\Facades\DB;

/**
 * Cancel Bid Compensation Activity
 * 
 * Cancels the bid record when the bid placement saga fails.
 * This compensation activity ensures invalid bids are marked as cancelled.
 */
class CancelBidActivity extends BaseRpcActivity
{
    /**
     * Execute bid cancellation compensation
     *
     * @param array $compensationData Data from the failed saga step
     * @return array Bid cancellation result
     */
    public function __invoke(array $compensationData): array
    {
        $this->validateData($compensationData, [
            'bid_id'
        ]);
        
        try {
            return DB::transaction(function () use ($compensationData) {
                $bid = Bid::find($compensationData['bid_id']);
                
                if (!$bid) {
                    // Bid doesn't exist, compensation is effectively complete
                    return $this->successResponse([
                        'bid_id' => $compensationData['bid_id'],
                        'status' => 'not_found',
                        'compensation_completed' => true,
                        'message' => 'Bid not found, no cancellation needed'
                    ]);
                }
                
                // Update bid status to cancelled
                $bid->update([
                    'status' => 'cancelled',
                    'metadata' => array_merge($bid->metadata ?? [], [
                        'cancelled_by_saga' => true,
                        'cancellation_reason' => 'saga_compensation',
                        'cancelled_at' => now()->toISOString(),
                        'saga_id' => $this->getSagaId(),
                    ])
                ]);
                
                return $this->successResponse([
                    'bid_id' => $bid->id,
                    'auction_id' => $bid->auction_id,
                    'user_id' => $bid->user_id,
                    'status' => $bid->status,
                    'compensation_completed' => true,
                ]);
            });
            
        } catch (\Exception $e) {
            // Log but don't re-throw - compensation should be resilient
            $this->logError($e);
            
            return $this->errorResponse(
                "Bid cancellation compensation failed but saga continues",
                ['error' => $e->getMessage()]
            );
        }
    }
}

