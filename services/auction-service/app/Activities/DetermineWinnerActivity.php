<?php

namespace App\Activities;

use App\RPC\Adapters\BiddingServiceAdapter;
use App\Models\Auction;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;

/**
 * Determine Winner Activity
 * 
 * Coordinates with bidding-service to determine the auction winner
 * based on the highest valid bid and reserve price requirements.
 */
class DetermineWinnerActivity implements ActivityInterface
{
    private BiddingServiceAdapter $biddingClient;

    public function __construct()
    {
        $this->biddingClient = app(BiddingServiceAdapter::class);
    }

    /**
     * Execute the winner determination activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting winner determination', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];

            // Get auction details
            $auction = Auction::find($auctionId);
            if (!$auction) {
                throw new \Exception("Auction not found: {$auctionId}");
            }

            // Get highest bid from bidding service
            $highestBidResponse = $this->biddingClient->getHighestBid($auctionId);

            if (!$highestBidResponse || !$highestBidResponse['success']) {
                Log::info('No bids found for auction', ['auction_id' => $auctionId]);
                
                return [
                    'success' => true,
                    'auction_id' => $auctionId,
                    'winner' => null,
                    'reason' => 'no_bids',
                    'message' => 'Auction ended with no bids'
                ];
            }

            $highestBid = $highestBidResponse['bid'];

            // Check if highest bid meets reserve price (if set)
            if ($auction->reserve_price && $highestBid['amount'] < $auction->reserve_price) {
                Log::info('Highest bid did not meet reserve price', [
                    'auction_id' => $auctionId,
                    'highest_bid' => $highestBid['amount'],
                    'reserve_price' => $auction->reserve_price
                ]);

                return [
                    'success' => true,
                    'auction_id' => $auctionId,
                    'winner' => null,
                    'highest_bid' => $highestBid,
                    'reserve_price' => $auction->reserve_price,
                    'reason' => 'reserve_not_met',
                    'message' => 'Auction ended - reserve price not met'
                ];
            }

            // We have a winner!
            $winner = [
                'user_id' => $highestBid['user_id'],
                'bid_id' => $highestBid['id'],
                'winning_amount' => $highestBid['amount'],
                'bid_time' => $highestBid['created_at'],
                'auction_id' => $auctionId
            ];

            // Update auction with winner information
            $auction->update([
                'current_highest_bid' => $highestBid['amount'],
                'winner_user_id' => $winner['user_id'],
                'winning_bid_id' => $winner['bid_id']
            ]);

            Log::info('Winner determined successfully', [
                'auction_id' => $auctionId,
                'winner' => $winner
            ]);

            return [
                'success' => true,
                'auction_id' => $auctionId,
                'winner' => $winner,
                'reason' => 'winner_determined',
                'message' => 'Auction winner determined successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Winner determination activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to determine auction winner'
            ];
        }
    }
}
