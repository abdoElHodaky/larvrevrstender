<?php

namespace App\RPC\Handlers;

use App\Models\Auction;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

/**
 * Auction RPC Handler
 * 
 * Handles RPC calls from the shared service procedures
 * for auction-related operations.
 */
class AuctionRpcHandler
{
    /**
     * Validate if auction is active and available for bidding
     */
    public function validateAuctionActive(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];

            // Try cache first for performance
            $cachedAuction = Cache::get("auction_active:{$auctionId}");
            if ($cachedAuction) {
                return [
                    'success' => true,
                    'data' => $cachedAuction
                ];
            }

            $auction = Auction::find($auctionId);
            if (!$auction) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_FOUND',
                    'message' => 'Auction not found'
                ];
            }

            // Check if auction is active
            if ($auction->status !== 'active') {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_ACTIVE',
                    'message' => "Auction status is '{$auction->status}', not active"
                ];
            }

            // Check if auction has started
            if ($auction->starts_at && Carbon::now()->isBefore($auction->starts_at)) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_STARTED',
                    'message' => 'Auction has not started yet'
                ];
            }

            // Check if auction has ended
            if ($auction->ends_at && Carbon::now()->isAfter($auction->ends_at)) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_ENDED',
                    'message' => 'Auction has already ended'
                ];
            }

            $auctionData = [
                'id' => $auction->id,
                'title' => $auction->title,
                'status' => $auction->status,
                'seller_id' => $auction->seller_id,
                'starting_price' => $auction->starting_price,
                'reserve_price' => $auction->reserve_price,
                'current_highest_bid' => $auction->current_highest_bid,
                'minimum_bid_increment' => $auction->minimum_bid_increment,
                'bid_count' => $auction->bid_count ?? 0,
                'starts_at' => $auction->starts_at?->toISOString(),
                'ends_at' => $auction->ends_at?->toISOString(),
                'max_bid_limit' => $auction->max_bid_limit
            ];

            // Cache for 5 minutes to reduce database load
            Cache::put("auction_active:{$auctionId}", $auctionData, now()->addMinutes(5));

            return [
                'success' => true,
                'data' => $auctionData
            ];

        } catch (Exception $e) {
            Log::error('Failed to validate auction active status', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Failed to validate auction: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create auction record
     */
    public function createAuctionRecord(array $params): array
    {
        try {
            DB::beginTransaction();

            $auction = new Auction([
                'seller_id' => $params['seller_id'],
                'title' => $params['title'],
                'description' => $params['description'],
                'starting_price' => $params['starting_price'],
                'reserve_price' => $params['reserve_price'] ?? null,
                'minimum_bid_increment' => $params['minimum_bid_increment'] ?? 1.00,
                'category' => $params['category'],
                'status' => 'draft', // Start as draft, will be activated later
                'metadata' => $params['metadata'] ?? [],
                'created_at' => Carbon::now()
            ]);

            // Calculate end time based on duration
            if (isset($params['duration_hours'])) {
                $startTime = isset($params['start_time']) 
                    ? Carbon::parse($params['start_time'])
                    : Carbon::now();
                
                $auction->starts_at = $startTime;
                $auction->ends_at = $startTime->copy()->addHours($params['duration_hours']);
            }

            $auction->save();

            // Handle product images if provided
            if (!empty($params['images'])) {
                foreach ($params['images'] as $imageData) {
                    $productImage = new ProductImage([
                        'auction_id' => $auction->id,
                        'image_path' => $imageData['path'],
                        'image_name' => $imageData['name'] ?? null,
                        'image_type' => $imageData['type'] ?? 'image/jpeg',
                        'image_size' => $imageData['size'] ?? null,
                        'is_primary' => $imageData['is_primary'] ?? false,
                        'sort_order' => $imageData['sort_order'] ?? 0
                    ]);
                    $productImage->save();
                }
            }

            // If no start time specified, activate immediately
            if (!isset($params['start_time'])) {
                $auction->update([
                    'status' => 'active',
                    'starts_at' => Carbon::now()
                ]);
            }

            DB::commit();

            // Clear any cached auction data
            Cache::forget("auction_active:{$auction->id}");

            Log::info('Auction record created successfully', [
                'auction_id' => $auction->id,
                'seller_id' => $params['seller_id'],
                'title' => $params['title'],
                'starting_price' => $params['starting_price']
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $auction->id,
                    'title' => $auction->title,
                    'description' => $auction->description,
                    'seller_id' => $auction->seller_id,
                    'starting_price' => $auction->starting_price,
                    'reserve_price' => $auction->reserve_price,
                    'minimum_bid_increment' => $auction->minimum_bid_increment,
                    'category' => $auction->category,
                    'status' => $auction->status,
                    'starts_at' => $auction->starts_at?->toISOString(),
                    'ends_at' => $auction->ends_at?->toISOString(),
                    'created_at' => $auction->created_at->toISOString()
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create auction record', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'AUCTION_CREATION_FAILED',
                'message' => 'Failed to create auction record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get auction details
     */
    public function getAuctionDetails(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];

            $auction = Auction::with(['images'])->find($auctionId);
            if (!$auction) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_FOUND',
                    'message' => 'Auction not found'
                ];
            }

            $auctionData = [
                'id' => $auction->id,
                'title' => $auction->title,
                'description' => $auction->description,
                'seller_id' => $auction->seller_id,
                'starting_price' => $auction->starting_price,
                'reserve_price' => $auction->reserve_price,
                'current_highest_bid' => $auction->current_highest_bid,
                'highest_bidder_id' => $auction->highest_bidder_id,
                'minimum_bid_increment' => $auction->minimum_bid_increment,
                'category' => $auction->category,
                'status' => $auction->status,
                'bid_count' => $auction->bid_count ?? 0,
                'starts_at' => $auction->starts_at?->toISOString(),
                'ends_at' => $auction->ends_at?->toISOString(),
                'last_bid_at' => $auction->last_bid_at?->toISOString(),
                'metadata' => $auction->metadata,
                'created_at' => $auction->created_at->toISOString(),
                'updated_at' => $auction->updated_at->toISOString(),
                'images' => $auction->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_path' => $image->image_path,
                        'image_name' => $image->image_name,
                        'is_primary' => $image->is_primary,
                        'sort_order' => $image->sort_order
                    ];
                })->toArray()
            ];

            return [
                'success' => true,
                'data' => $auctionData
            ];

        } catch (Exception $e) {
            Log::error('Failed to get auction details', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'AUCTION_RETRIEVAL_FAILED',
                'message' => 'Failed to retrieve auction details: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update auction with new bid information
     */
    public function updateAuctionWithBid(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];
            $bidData = $params['bid_data'];

            $auction = Auction::find($auctionId);
            if (!$auction) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_FOUND',
                    'message' => 'Auction not found'
                ];
            }

            // Update auction with new bid information
            $updateData = [
                'last_bid_at' => Carbon::now()
            ];

            // Update highest bid if this bid is higher
            if ($bidData['amount'] > ($auction->current_highest_bid ?? 0)) {
                $updateData['current_highest_bid'] = $bidData['amount'];
                $updateData['highest_bidder_id'] = $bidData['user_id'];
            }

            // Increment bid count
            $updateData['bid_count'] = ($auction->bid_count ?? 0) + 1;

            $auction->update($updateData);

            // Clear cached auction data
            Cache::forget("auction_active:{$auctionId}");
            Cache::forget("auction_stats:{$auctionId}");

            Log::info('Auction updated with new bid', [
                'auction_id' => $auctionId,
                'bid_amount' => $bidData['amount'],
                'new_highest_bid' => $updateData['current_highest_bid'] ?? null
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $auction->id,
                    'title' => $auction->title,
                    'current_highest_bid' => $auction->current_highest_bid,
                    'highest_bidder_id' => $auction->highest_bidder_id,
                    'bid_count' => $auction->bid_count,
                    'last_bid_at' => $auction->last_bid_at?->toISOString(),
                    'ends_at' => $auction->ends_at?->toISOString()
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to update auction with bid', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'AUCTION_UPDATE_FAILED',
                'message' => 'Failed to update auction with bid: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update auction status
     */
    public function updateAuctionStatus(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];
            $status = $params['status'];

            $auction = Auction::find($auctionId);
            if (!$auction) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_FOUND',
                    'message' => 'Auction not found'
                ];
            }

            $updateData = ['status' => $status];

            // Add additional fields based on status
            if ($status === 'ended') {
                $updateData['ended_at'] = $params['ended_at'] ?? Carbon::now()->toISOString();
                if (isset($params['winner_bid_id'])) {
                    $updateData['winner_bid_id'] = $params['winner_bid_id'];
                }
                if (isset($params['final_price'])) {
                    $updateData['final_price'] = $params['final_price'];
                }
            }

            $auction->update($updateData);

            // Clear all cached auction data
            Cache::forget("auction_active:{$auctionId}");
            Cache::forget("auction_stats:{$auctionId}");

            Log::info('Auction status updated', [
                'auction_id' => $auctionId,
                'old_status' => $auction->getOriginal('status'),
                'new_status' => $status
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $auction->id,
                    'status' => $auction->status,
                    'ended_at' => $auction->ended_at ?? null,
                    'winner_bid_id' => $auction->winner_bid_id ?? null,
                    'final_price' => $auction->final_price ?? null
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to update auction status', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'STATUS_UPDATE_FAILED',
                'message' => 'Failed to update auction status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update auction settlement information
     */
    public function updateAuctionSettlement(array $params): array
    {
        try {
            $auctionId = $params['auction_id'];

            $auction = Auction::find($auctionId);
            if (!$auction) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_FOUND',
                    'message' => 'Auction not found'
                ];
            }

            $updateData = [
                'settlement_status' => $params['settlement_status'],
                'settled_at' => $params['settled_at'] ?? Carbon::now()->toISOString()
            ];

            if (isset($params['order_id'])) {
                $updateData['order_id'] = $params['order_id'];
            }

            if (isset($params['payment_id'])) {
                $updateData['payment_id'] = $params['payment_id'];
            }

            $auction->update($updateData);

            Log::info('Auction settlement updated', [
                'auction_id' => $auctionId,
                'settlement_status' => $params['settlement_status'],
                'order_id' => $params['order_id'] ?? null,
                'payment_id' => $params['payment_id'] ?? null
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $auction->id,
                    'settlement_status' => $auction->settlement_status,
                    'settled_at' => $auction->settled_at,
                    'order_id' => $auction->order_id ?? null,
                    'payment_id' => $auction->payment_id ?? null
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to update auction settlement', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'SETTLEMENT_UPDATE_FAILED',
                'message' => 'Failed to update auction settlement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get expired auctions that need to be processed
     */
    public function getExpiredAuctions(array $params): array
    {
        try {
            $limit = $params['limit'] ?? 50;
            $now = Carbon::now();

            $expiredAuctions = Auction::where('status', 'active')
                ->where('ends_at', '<=', $now)
                ->limit($limit)
                ->get();

            $auctionData = $expiredAuctions->map(function ($auction) {
                return [
                    'id' => $auction->id,
                    'title' => $auction->title,
                    'seller_id' => $auction->seller_id,
                    'current_highest_bid' => $auction->current_highest_bid,
                    'highest_bidder_id' => $auction->highest_bidder_id,
                    'reserve_price' => $auction->reserve_price,
                    'bid_count' => $auction->bid_count ?? 0,
                    'ends_at' => $auction->ends_at->toISOString(),
                    'status' => $auction->status
                ];
            })->toArray();

            return [
                'success' => true,
                'data' => [
                    'auctions' => $auctionData,
                    'total_count' => count($auctionData)
                ]
            ];

        } catch (Exception $e) {
            Log::error('Failed to get expired auctions', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'EXPIRED_AUCTIONS_RETRIEVAL_FAILED',
                'message' => 'Failed to retrieve expired auctions: ' . $e->getMessage()
            ];
        }
    }
}
