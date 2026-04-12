<?php

namespace App\Services;

use App\Procedures\AuctionProcedure;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Auction Completion Service
 *
 * Handles the completion of expired auctions using the existing
 * cross-service infrastructure and Laravel workflow patterns.
 */
class AuctionCompletionService
{
    private AuctionProcedure $auctionProcedure;

    public function __construct()
    {
        $this->auctionProcedure = new AuctionProcedure();
    }

    /**
     * Process all expired auctions
     */
    public function processExpiredAuctions(): array
    {
        $results = [
            'processed' => 0,
            'completed' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Get expired auctions
            $expiredAuctionsResult = $this->auctionProcedure->getExpiredAuctions([], []);
            
            if (!$expiredAuctionsResult['success']) {
                throw new Exception('Failed to get expired auctions: ' . $expiredAuctionsResult['message']);
            }

            $expiredAuctions = $expiredAuctionsResult['data']['auctions'];
            $results['processed'] = count($expiredAuctions);

            Log::info("Processing {$results['processed']} expired auctions");

            foreach ($expiredAuctions as $auction) {
                try {
                    $completionResult = $this->auctionProcedure->completeAuction([
                        'auction_id' => $auction['id']
                    ], []);

                    if ($completionResult['success']) {
                        $results['completed']++;
                        Log::info("Successfully completed auction {$auction['id']}");
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Auction {$auction['id']}: " . $completionResult['message'];
                        Log::error("Failed to complete auction {$auction['id']}: " . $completionResult['message']);
                    }

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Auction {$auction['id']}: " . $e->getMessage();
                    Log::error("Exception completing auction {$auction['id']}: " . $e->getMessage());
                }
            }

            Log::info("Auction completion summary", $results);
            return $results;

        } catch (Exception $e) {
            Log::error("Failed to process expired auctions: " . $e->getMessage());
            $results['errors'][] = $e->getMessage();
            return $results;
        }
    }

    /**
     * Complete a specific auction
     */
    public function completeAuction(int $auctionId): array
    {
        try {
            Log::info("Completing auction {$auctionId}");

            $result = $this->auctionProcedure->completeAuction([
                'auction_id' => $auctionId
            ], []);

            if ($result['success']) {
                Log::info("Successfully completed auction {$auctionId}");
            } else {
                Log::error("Failed to complete auction {$auctionId}: " . $result['message']);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("Exception completing auction {$auctionId}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get auction completion statistics
     */
    public function getCompletionStats(): array
    {
        try {
            // Get active auctions
            $activeResult = $this->auctionProcedure->getActiveAuctions([], []);
            $activeCount = $activeResult['success'] ? count($activeResult['data']['auctions']['data'] ?? []) : 0;

            // Get expired auctions
            $expiredResult = $this->auctionProcedure->getExpiredAuctions([], []);
            $expiredCount = $expiredResult['success'] ? count($expiredResult['data']['auctions']) : 0;

            return [
                'active_auctions' => $activeCount,
                'expired_auctions_pending' => $expiredCount,
                'last_check' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error("Failed to get completion stats: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }
}
