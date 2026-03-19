<?php

namespace App\Workflows\Activities;

/**
 * Update Auction Activity
 * 
 * Updates the auction's highest bid information through the auction service via RPC.
 * This activity ensures the auction reflects the new bid.
 */
class UpdateAuctionActivity extends BaseRpcActivity
{
    /**
     * Execute auction update
     *
     * @param array $bidData Bid data including auction and bid information
     * @return array Auction update result
     */
    public function __invoke(array $bidData): array
    {
        $this->validateData($bidData, [
            'auction_id',
            'bid_id',
            'amount',
            'user_id'
        ]);
        
        $updateData = [
            'auction_id' => $bidData['auction_id'],
            'highest_bid_amount' => $bidData['amount'],
            'highest_bid_id' => $bidData['bid_id'],
            'highest_bidder_id' => $bidData['user_id'],
            'bid_count_increment' => 1,
            'last_bid_at' => now()->toISOString(),
            'saga_id' => $this->getSagaId(),
        ];
        
        $result = $this->callRpc('auction-service', 'updateHighestBid', $updateData);
        
        if (!$result['success']) {
            throw new \Exception("Auction update failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        // Check if anti-sniping was triggered
        $antiSnipingTriggered = $result['data']['anti_sniping_triggered'] ?? false;
        $newEndTime = $result['data']['new_end_time'] ?? null;
        
        return $this->successResponse([
            'auction_id' => $bidData['auction_id'],
            'highest_bid_amount' => $bidData['amount'],
            'bid_count' => $result['data']['bid_count'],
            'anti_sniping_triggered' => $antiSnipingTriggered,
            'new_end_time' => $newEndTime,
            'updated_at' => now()->toISOString(),
        ]);
    }
}

