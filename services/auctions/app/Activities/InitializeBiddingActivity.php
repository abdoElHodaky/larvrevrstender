<?php

namespace App\Activities;

use App\RPC\Adapters\BiddingServiceAdapter;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;

/**
 * Initialize Bidding Activity
 * 
 * Coordinates with bidding-service to initialize bid tracking for the new auction.
 * This ensures the bidding service is ready to accept bids when the auction starts.
 */
class InitializeBiddingActivity implements ActivityInterface
{
    private BiddingServiceAdapter $biddingClient;

    public function __construct()
    {
        $this->biddingClient = app(BiddingServiceAdapter::class);
    }

    /**
     * Execute the bidding initialization activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting bidding service initialization', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];
            $auctionData = $input['auction_data'];

            // Prepare bidding service initialization data
            $biddingInitData = [
                'auction_id' => $auctionId,
                'starting_price' => $auctionData['starting_price'],
                'reserve_price' => $auctionData['reserve_price'] ?? null,
                'starts_at' => $auctionData['starts_at'],
                'ends_at' => $auctionData['ends_at'],
                'status' => 'initialized'
            ];

            // Call bidding service to initialize auction tracking
            $response = $this->biddingClient->initializeAuction($biddingInitData);

            if (!$response || !isset($response['success']) || !$response['success']) {
                throw new \Exception('Bidding service initialization failed: ' . ($response['message'] ?? 'Unknown error'));
            }

            Log::info('Bidding service initialized successfully', [
                'auction_id' => $auctionId,
                'response' => $response
            ]);

            return [
                'success' => true,
                'auction_id' => $auctionId,
                'bidding_response' => $response,
                'message' => 'Bidding service initialized successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Bidding initialization activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to initialize bidding service'
            ];
        }
    }
}
