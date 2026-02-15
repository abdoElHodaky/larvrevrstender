<?php

namespace App\Activities;

use App\Http\Clients\BiddingServiceClient;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup Bidding Activity (Compensation)
 * 
 * Compensation activity that removes bidding service initialization
 * when the auction creation saga fails after bidding was initialized.
 */
class CleanupBiddingActivity implements ActivityInterface
{
    private BiddingServiceClient $biddingClient;

    public function __construct()
    {
        $this->biddingClient = app(BiddingServiceClient::class);
    }

    /**
     * Execute the bidding cleanup compensation activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting bidding service cleanup compensation', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];

            // Call bidding service to cleanup auction initialization
            $response = $this->biddingClient->cleanupAuction($auctionId);

            if (!$response || !isset($response['success']) || !$response['success']) {
                // Log warning but don't fail the compensation
                // Bidding service cleanup is best-effort
                Log::warning('Bidding service cleanup returned non-success response', [
                    'auction_id' => $auctionId,
                    'response' => $response
                ]);
            }

            Log::info('Bidding service cleanup completed', [
                'auction_id' => $auctionId,
                'response' => $response
            ]);

            return [
                'success' => true,
                'auction_id' => $auctionId,
                'cleanup_response' => $response,
                'message' => 'Bidding service cleanup completed'
            ];

        } catch (\Exception $e) {
            // Log error but don't fail the compensation
            // Bidding service cleanup is best-effort during compensation
            Log::error('Bidding cleanup compensation activity encountered error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => true, // Return success to not block other compensations
                'error' => $e->getMessage(),
                'message' => 'Bidding service cleanup encountered error but compensation continues'
            ];
        }
    }
}

