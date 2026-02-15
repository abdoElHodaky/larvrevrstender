<?php

namespace App\Activities;

use App\Models\Auction;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Revert Auction Status Activity (Compensation)
 * 
 * Compensation activity that reverts an auction's status when the ending saga fails.
 * This ensures the auction can be properly ended again or remain in a valid state.
 */
class RevertAuctionStatusActivity implements ActivityInterface
{
    /**
     * Execute the auction status reversion compensation activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting auction status reversion compensation', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];
            $revertToStatus = $input['revert_to_status'] ?? 'active';

            // Use database transaction for atomic operation
            $result = DB::transaction(function () use ($auctionId, $revertToStatus) {
                $auction = Auction::find($auctionId);

                if (!$auction) {
                    Log::warning('Auction not found for status reversion', ['auction_id' => $auctionId]);
                    return [
                        'success' => true,
                        'message' => 'Auction not found - no reversion needed'
                    ];
                }

                $originalStatus = $auction->status;

                // Revert auction status
                $auction->update([
                    'status' => $revertToStatus,
                    'ended_at' => null, // Clear ended timestamp if it was set
                ]);

                Log::info('Auction status reverted successfully during compensation', [
                    'auction_id' => $auctionId,
                    'original_status' => $originalStatus,
                    'reverted_to_status' => $revertToStatus
                ]);

                return [
                    'success' => true,
                    'auction_id' => $auctionId,
                    'original_status' => $originalStatus,
                    'reverted_to_status' => $revertToStatus,
                    'message' => 'Auction status reverted successfully'
                ];
            });

            return $result;

        } catch (\Exception $e) {
            Log::error('Auction status reversion compensation activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to revert auction status during compensation'
            ];
        }
    }
}

