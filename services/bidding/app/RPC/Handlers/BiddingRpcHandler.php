<?php

namespace App\RPC\Handlers;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\BidAttachment;
use App\Services\BiddingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

/**
 * Bidding RPC Handler
 * 
 * Handles RPC calls from the shared service procedures
 * for bidding-related operations.
 */
class BiddingRpcHandler
{
    private BiddingService $biddingService;

    public function __construct(BiddingService $biddingService)
    {
        $this->biddingService = $biddingService;
    }

    /**
     * Create a bid record
     */
    public function createBidRecord(array $params): array
    {
        try {
            DB::beginTransaction();

            $bid = new Bid([
                'auction_id' => $params['auction_id'],
                'user_id' => $params['user_id'],
                'amount' => $params['amount'],
                'bid_type' => $params['bid_type'] ?? 'standard',
                'reservation_id' => $params['reservation_id'] ?? null,
                'metadata' => $params['metadata'] ?? [],
                'status' => 'active',
                'placed_at' => Carbon::now()
            ]);

            $bid->save();

            // Handle attachments if provided
            if (!empty($params['attachments'])) {
                foreach ($params['attachments'] as $attachmentData) {
                    $attachment = new BidAttachment([
                        'bid_id' => $bid->id,
                        'file_path' => $attachmentData['file_path'],
                        'file_name' => $attachmentData['file_name'],
                        'file_type' => $attachmentData['file_type'],
                        'file_size' => $attachmentData['file_size']
                    ]);
                    $attachment->save();
                }
            }

            // Update auction's current highest bid if this is higher
            $auction = Auction::find($params['auction_id']);
            if ($auction && $bid->amount > ($auction->current_highest_bid ?? 0)) {
                $auction->update([
                    'current_highest_bid' => $bid->amount,
                    'highest_bidder_id' => $bid->user_id,
                    'bid_count' => $auction->bid_count + 1,
                    'last_bid_at' => Carbon::now()
                ]);
            }

            DB::commit();

            // Cache the bid for quick access
            Cache::put("bid:{$bid->id}", $bid->toArray(), now()->addHours(24));

            Log::info('Bid record created successfully', [
                'bid_id' => $bid->id,
                'auction_id' => $params['auction_id'],
                'user_id' => $params['user_id'],
                'amount' => $params['amount']
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $bid->id,
                    'auction_id' => $bid->auction_id,
                    'user_id' => $bid->user_id,
                    'amount' => $bid->amount,
                    'bid_type' => $bid->bid_type,
                    'status' => $bid->status,
                    'created_at' => $bid->created_at->toISOString(),
                    'reservation_id' => $bid->reservation_id
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create bid record', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'BID_CREATION_FAILED',
                'message' => 'Failed to create bid record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get auction bids
     */
    public function getAuctionBids(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];
            $orderBy = $params['order_by'] ?? 'created_at';
            $orderDirection = $params['order_direction'] ?? 'desc';
            $limit = $params['limit'] ?? 100;

            $query = Bid::where('auction_id', $auctionId)
                ->where('status', 'active')
                ->with(['attachments']);

            // Apply ordering
            $query->orderBy($orderBy, $orderDirection);

            // Apply limit
            if ($limit > 0) {
                $query->limit($limit);
            }

            $bids = $query->get();

            $bidData = $bids->map(function ($bid) {
                return [
                    'id' => $bid->id,
                    'auction_id' => $bid->auction_id,
                    'user_id' => $bid->user_id,
                    'amount' => $bid->amount,
                    'bid_type' => $bid->bid_type,
                    'status' => $bid->status,
                    'reservation_id' => $bid->reservation_id,
                    'created_at' => $bid->created_at->toISOString(),
                    'attachments' => $bid->attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'file_type' => $attachment->file_type,
                            'file_size' => $attachment->file_size
                        ];
                    })->toArray()
                ];
            })->toArray();

            return [
                'success' => true,
                'data' => [
                    'bids' => $bidData,
                    'total_count' => count($bidData)
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to get auction bids', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'BIDS_RETRIEVAL_FAILED',
                'message' => 'Failed to retrieve auction bids: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Broadcast bid update to auction participants via WebSocket
     */
    public function broadcastToAuction(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];
            $data = $params['data'];

            // Get all users watching this auction
            $watchers = Cache::get("auction_watchers:{$auctionId}", []);

            // Broadcast to WebSocket channels
            $broadcastChannels = [
                "auction.{$auctionId}",
                "auction.{$auctionId}.bids"
            ];

            foreach ($broadcastChannels as $channel) {
                // This would integrate with your WebSocket implementation
                // For now, we'll log the broadcast
                Log::info('Broadcasting to WebSocket channel', [
                    'channel' => $channel,
                    'data' => $data,
                    'watchers_count' => count($watchers)
                ]);

                // Example WebSocket broadcast (implementation depends on your WebSocket setup)
                // broadcast(new BidPlacedEvent($data))->toOthers();
            }

            // Update real-time auction statistics
            $this->updateAuctionStatistics($auctionId, $data);

            return [
                'success' => true,
                'data' => [
                    'channels_broadcasted' => $broadcastChannels,
                    'watchers_notified' => count($watchers),
                    'broadcast_timestamp' => Carbon::now()->toISOString()
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to broadcast bid update', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'BROADCAST_FAILED',
                'message' => 'Failed to broadcast bid update: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate bid placement requirements
     */
    public function validateBidPlacement(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];
            $userId = $params['user_id'];
            $bidAmount = $params['bid_amount'];

            // Get auction details
            $auction = Auction::find($auctionId);
            if (!$auction) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_FOUND',
                    'message' => 'Auction not found'
                ];
            }

            // Check auction status
            if ($auction->status !== 'active') {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_ACTIVE',
                    'message' => 'Auction is not active'
                ];
            }

            // Check auction end time
            if ($auction->ends_at && Carbon::now()->isAfter($auction->ends_at)) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_ENDED',
                    'message' => 'Auction has already ended'
                ];
            }

            // Check if user is the seller (can't bid on own auction)
            if ($auction->seller_id === $userId) {
                return [
                    'success' => false,
                    'error' => 'SELLER_CANNOT_BID',
                    'message' => 'Sellers cannot bid on their own auctions'
                ];
            }

            // Check minimum bid amount
            $currentHighestBid = $auction->current_highest_bid ?? 0;
            $minimumBid = max(
                $auction->starting_price,
                $currentHighestBid + ($auction->minimum_bid_increment ?? 1.00)
            );

            if ($bidAmount < $minimumBid) {
                return [
                    'success' => false,
                    'error' => 'BID_TOO_LOW',
                    'message' => "Minimum bid amount is {$minimumBid}"
                ];
            }

            // Check if user has already placed a higher bid
            $userHighestBid = Bid::where('auction_id', $auctionId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->max('amount');

            if ($userHighestBid && $bidAmount <= $userHighestBid) {
                return [
                    'success' => false,
                    'error' => 'BID_NOT_HIGHER',
                    'message' => 'Your bid must be higher than your previous bid'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'auction_id' => $auctionId,
                    'minimum_bid' => $minimumBid,
                    'current_highest_bid' => $currentHighestBid,
                    'user_highest_bid' => $userHighestBid,
                    'bid_valid' => true
                ]
            ];

        } catch (Exception $e) {
            Log::error('Bid validation failed', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Bid validation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get bid details
     */
    public function getBidDetails(array $params): array
    {
        try {
            $bidId = $params['bid_id'];

            // Try cache first
            $cachedBid = Cache::get("bid:{$bidId}");
            if ($cachedBid) {
                return [
                    'success' => true,
                    'data' => $cachedBid
                ];
            }

            $bid = Bid::with(['attachments'])->find($bidId);
            if (!$bid) {
                return [
                    'success' => false,
                    'error' => 'BID_NOT_FOUND',
                    'message' => 'Bid not found'
                ];
            }

            $bidData = [
                'id' => $bid->id,
                'auction_id' => $bid->auction_id,
                'user_id' => $bid->user_id,
                'amount' => $bid->amount,
                'bid_type' => $bid->bid_type,
                'status' => $bid->status,
                'reservation_id' => $bid->reservation_id,
                'metadata' => $bid->metadata,
                'created_at' => $bid->created_at->toISOString(),
                'attachments' => $bid->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_type' => $attachment->file_type,
                        'file_size' => $attachment->file_size,
                        'file_path' => $attachment->file_path
                    ];
                })->toArray()
            ];

            // Cache for future requests
            Cache::put("bid:{$bidId}", $bidData, now()->addHours(24));

            return [
                'success' => true,
                'data' => $bidData
            ];

        } catch (Exception $e) {
            Log::error('Failed to get bid details', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'BID_RETRIEVAL_FAILED',
                'message' => 'Failed to retrieve bid details: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update auction statistics for real-time display
     */
    private function updateAuctionStatistics(string $auctionId, array $bidData): void
    {
        try {
            $stats = [
                'auction_id' => $auctionId,
                'current_highest_bid' => $bidData['bid']['amount'] ?? 0,
                'bid_count' => $bidData['auction']['bid_count'] ?? 0,
                'last_bid_at' => $bidData['bid']['timestamp'] ?? Carbon::now()->toISOString(),
                'updated_at' => Carbon::now()->toISOString()
            ];

            Cache::put("auction_stats:{$auctionId}", $stats, now()->addHours(24));

        } catch (Exception $e) {
            Log::warning('Failed to update auction statistics', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
