<?php

namespace App\Workflows\Activities;

/**
 * Validate Auction Activity
 * 
 * Validates auction status and bid eligibility through the auction service via RPC.
 * This activity ensures the auction is active and accepting bids.
 */
class ValidateAuctionActivity extends BaseRpcActivity
{
    /**
     * Execute auction validation
     *
     * @param array $bidData Bid data including auction and user information
     * @return array Auction validation result with auction details
     */
    public function __invoke(array $bidData): array
    {
        $this->validateData($bidData, [
            'auction_id',
            'user_id',
            'amount'
        ]);
        
        $validationData = [
            'auction_id' => $bidData['auction_id'],
            'user_id' => $bidData['user_id'],
            'bid_amount' => $bidData['amount'],
            'validate_timing' => true,
            'validate_eligibility' => true,
            'validate_amount' => true,
        ];
        
        $result = $this->callRpc('auction-service', 'validateBidEligibility', $validationData);
        
        if (!$result['success']) {
            throw new \Exception("Auction validation failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'auction_id' => $result['data']['auction']['id'],
            'auction_status' => $result['data']['auction']['status'],
            'current_highest_bid' => $result['data']['auction']['highest_bid_amount'],
            'minimum_bid' => $result['data']['auction']['minimum_bid'],
            'bid_increment' => $result['data']['auction']['bid_increment'],
            'ends_at' => $result['data']['auction']['ends_at'],
            'validation_passed' => true
        ]);
    }
}

