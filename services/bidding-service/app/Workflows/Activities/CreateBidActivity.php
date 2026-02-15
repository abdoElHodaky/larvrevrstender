<?php

namespace App\Workflows\Activities;

use App\Models\Bid;
use Illuminate\Support\Facades\DB;

/**
 * Create Bid Activity
 * 
 * Creates the bid record in the database and handles race condition protection.
 * This activity is the core bid creation step in the saga.
 */
class CreateBidActivity extends BaseRpcActivity
{
    /**
     * Execute bid creation
     *
     * @param array $bidData Bid data including all necessary information
     * @return array Bid creation result with bid details
     */
    public function __invoke(array $bidData): array
    {
        $this->validateData($bidData, [
            'auction_id',
            'user_id',
            'amount',
            'reservation_id'
        ]);
        
        try {
            return DB::transaction(function () use ($bidData) {
                // Check for concurrent bids (race condition protection)
                $currentHighestBid = Bid::where('auction_id', $bidData['auction_id'])
                    ->where('status', 'active')
                    ->orderBy('amount', 'desc')
                    ->first();
                
                if ($currentHighestBid && $bidData['amount'] <= $currentHighestBid->amount) {
                    throw new \Exception('Bid amount must be higher than current highest bid');
                }
                
                // Create the bid
                $bid = Bid::create([
                    'auction_id' => $bidData['auction_id'],
                    'user_id' => $bidData['user_id'],
                    'amount' => $bidData['amount'],
                    'currency' => $bidData['currency'] ?? 'USD',
                    'status' => 'pending', // Will be activated after saga completion
                    'submitted_at' => now(),
                    'metadata' => [
                        'saga_id' => $this->getSagaId(),
                        'reservation_id' => $bidData['reservation_id'],
                        'created_via_saga' => true,
                    ],
                ]);
                
                return $this->successResponse([
                    'bid_id' => $bid->id,
                    'auction_id' => $bid->auction_id,
                    'user_id' => $bid->user_id,
                    'amount' => $bid->amount,
                    'currency' => $bid->currency,
                    'status' => $bid->status,
                    'submitted_at' => $bid->submitted_at->toISOString(),
                ]);
            });
            
        } catch (\Exception $e) {
            throw new \Exception("Bid creation failed: " . $e->getMessage());
        }
    }
}

