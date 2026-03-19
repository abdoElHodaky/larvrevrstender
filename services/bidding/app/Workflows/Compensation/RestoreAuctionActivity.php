<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;

/**
 * Restore Auction Compensation Activity
 * 
 * Restores the auction's previous highest bid when the bid placement saga fails.
 * This compensation activity ensures auction state consistency.
 */
class RestoreAuctionActivity extends BaseRpcActivity
{
    /**
     * Execute auction restoration compensation
     *
     * @param array $compensationData Data from the failed saga step
     * @return array Auction restoration result
     */
    public function __invoke(array $compensationData): array
    {
        $this->validateData($compensationData, [
            'auction_id',
            'bid_id'
        ]);
        
        $restoreData = [
            'auction_id' => $compensationData['auction_id'],
            'failed_bid_id' => $compensationData['bid_id'],
            'reason' => 'saga_compensation',
            'saga_id' => $this->getSagaId(),
            'description' => "Auction restoration due to failed bid placement saga",
        ];
        
        try {
            $result = $this->callRpc('auction-service', 'restorePreviousHighestBid', $restoreData);
            
            if (!$result['success']) {
                // Log the error but don't throw - compensation should be idempotent
                $this->logError(new \Exception("Auction restoration failed: " . ($result['error'] ?? 'Unknown error')));
                
                return $this->errorResponse(
                    "Auction restoration failed but saga compensation continues",
                    ['original_error' => $result['error'] ?? 'Unknown error']
                );
            }
            
            return $this->successResponse([
                'auction_id' => $compensationData['auction_id'],
                'restored_highest_bid' => $result['data']['highest_bid_amount'] ?? null,
                'restored_bidder_id' => $result['data']['highest_bidder_id'] ?? null,
                'bid_count' => $result['data']['bid_count'] ?? null,
                'compensation_completed' => true,
            ]);
            
        } catch (\Exception $e) {
            // Log but don't re-throw - compensation should be resilient
            $this->logError($e);
            
            return $this->errorResponse(
                "Auction restoration compensation failed but saga continues",
                ['error' => $e->getMessage()]
            );
        }
    }
}

