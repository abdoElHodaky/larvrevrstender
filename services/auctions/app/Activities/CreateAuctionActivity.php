<?php

namespace App\Activities;

use App\Models\Auction;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Create Auction Activity
 * 
 * Creates the auction record in the database with proper transaction handling
 * and sets the auction status to 'scheduled' for future activation.
 */
class CreateAuctionActivity implements ActivityInterface
{
    /**
     * Execute the auction creation activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting auction creation', ['input' => $input]);

        try {
            // Use database transaction for atomic operation
            $auction = DB::transaction(function () use ($input) {
                // Create the auction record
                $auction = Auction::create([
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'vehicle_id' => $input['vehicle_id'],
                    'starting_price' => $input['starting_price'],
                    'reserve_price' => $input['reserve_price'] ?? null,
                    'current_highest_bid' => 0.00, // Initialize to 0
                    'status' => 'scheduled', // Initial status
                    'starts_at' => $input['starts_at'],
                    'ends_at' => $input['ends_at'],
                    'created_by' => $input['created_by'],
                ]);

                Log::info('Auction record created successfully', [
                    'auction_id' => $auction->id,
                    'title' => $auction->title
                ]);

                return $auction;
            });

            return [
                'success' => true,
                'auction_id' => $auction->id,
                'auction' => $auction->toArray(),
                'message' => 'Auction created successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Auction creation activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create auction record'
            ];
        }
    }
}

