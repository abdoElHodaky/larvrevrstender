<?php

namespace App\Activities;

use App\Models\Auction;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Delete Auction Activity (Compensation)
 * 
 * Compensation activity that removes an auction record when the saga fails.
 * This ensures data consistency by cleaning up partially created auctions.
 */
class DeleteAuctionActivity implements ActivityInterface
{
    /**
     * Execute the auction deletion compensation activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting auction deletion compensation', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];

            // Use database transaction for atomic operation
            $result = DB::transaction(function () use ($auctionId) {
                $auction = Auction::find($auctionId);

                if (!$auction) {
                    Log::warning('Auction not found for deletion', ['auction_id' => $auctionId]);
                    return [
                        'success' => true,
                        'message' => 'Auction already deleted or not found'
                    ];
                }

                // Store auction data for logging before deletion
                $auctionData = $auction->toArray();

                // Delete the auction
                $auction->delete();

                Log::info('Auction deleted successfully during compensation', [
                    'auction_id' => $auctionId,
                    'deleted_auction' => $auctionData
                ]);

                return [
                    'success' => true,
                    'auction_id' => $auctionId,
                    'deleted_auction' => $auctionData,
                    'message' => 'Auction deleted successfully'
                ];
            });

            return $result;

        } catch (\Exception $e) {
            Log::error('Auction deletion compensation activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to delete auction during compensation'
            ];
        }
    }
}

