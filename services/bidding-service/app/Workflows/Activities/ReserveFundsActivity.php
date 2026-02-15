<?php

namespace App\Workflows\Activities;

/**
 * Reserve Funds Activity
 * 
 * Handles fund reservation for bid placement through the payment service via RPC.
 * This activity ensures the bidder has sufficient funds before placing a bid.
 */
class ReserveFundsActivity extends BaseRpcActivity
{
    /**
     * Execute fund reservation
     *
     * @param array $bidData Bid data including amount and user information
     * @return array Fund reservation result with reservation ID
     */
    public function __invoke(array $bidData): array
    {
        $this->validateData($bidData, [
            'user_id',
            'amount',
            'currency',
            'auction_id'
        ]);
        
        $reservationData = [
            'user_id' => $bidData['user_id'],
            'amount' => $bidData['amount'],
            'currency' => $bidData['currency'] ?? 'USD',
            'purpose' => 'bid_placement',
            'reference_id' => $bidData['auction_id'],
            'description' => "Fund reservation for bid on auction #{$bidData['auction_id']}",
            'expires_at' => now()->addHours(24)->toISOString(), // Reserve for 24 hours
        ];
        
        $result = $this->callRpc('payment-service', 'reserveFunds', $reservationData);
        
        if (!$result['success']) {
            throw new \Exception("Fund reservation failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'reservation_id' => $result['data']['reservation_id'],
            'reserved_amount' => $result['data']['reserved_amount'],
            'currency' => $result['data']['currency'],
            'expires_at' => $result['data']['expires_at'],
            'user_id' => $bidData['user_id']
        ]);
    }
}

