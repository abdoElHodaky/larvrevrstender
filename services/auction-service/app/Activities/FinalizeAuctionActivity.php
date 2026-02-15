<?php

namespace App\Activities;

use App\Models\Auction;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Finalize Auction Activity
 * 
 * Finalizes the auction by updating its status and recording the end time.
 * This marks the auction as officially ended and prevents further bids.
 */
class FinalizeAuctionActivity implements ActivityInterface
{
    /**
     * Execute the auction finalization activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting auction finalization', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];

            // Use database transaction for atomic operation
            $result = DB::transaction(function () use ($auctionId) {
                $auction = Auction::find($auctionId);

                if (!$auction) {
                    throw new \Exception("Auction not found: {$auctionId}");
                }

                // Check if auction is in a valid state to be ended
                if (!in_array($auction->status, ['active', 'scheduled'])) {
                    throw new \Exception("Auction cannot be ended from status: {$auction->status}");
                }

                // Store original status for potential rollback
                $originalStatus = $auction->status;

                // Update auction status to ended
                $auction->update([
                    'status' => 'ended',
                    'ended_at' => now(), // Record actual end time
                ]);

                Log::info('Auction finalized successfully', [
                    'auction_id' => $auctionId,
                    'original_status' => $originalStatus,
                    'new_status' => 'ended',
                    'ended_at' => $auction->ended_at
                ]);

                return [
                    'success' => true,
                    'auction_id' => $auctionId,
                    'original_status' => $originalStatus,
                    'new_status' => 'ended',
                    'ended_at' => $auction->ended_at->toISOString(),
                    'auction' => $auction->toArray(),
                    'message' => 'Auction finalized successfully'
                ];
            });

            return $result;

        } catch (\Exception $e) {
            Log::error('Auction finalization activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to finalize auction'
            ];
        }
    }
}

