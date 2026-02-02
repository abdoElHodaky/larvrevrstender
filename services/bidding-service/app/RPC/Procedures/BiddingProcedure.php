<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\BiddingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class BiddingProcedure extends BaseProcedure
{
    public function __construct(
        private BiddingService $biddingService
    ) {}

    /**
     * Create new auction
     * 
     * @param array $params
     * @return array
     */
    public function createAuction(array $params): array
    {
        $this->validate($params, [
            'seller_id' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'starting_price' => 'required|numeric|min:0.01',
            'reserve_price' => 'sometimes|numeric|min:0.01',
            'buy_now_price' => 'sometimes|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'category_id' => 'required|integer|min:1',
            'images' => 'sometimes|array|max:10',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'shipping_options' => 'sometimes|array',
        ]);

        return $this->executeWithLogging('Bidding@createAuction', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for auction creation
            $key = 'auction_create:' . $params['seller_id'];
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw new RuntimeException(
                    'Too many auction creation attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            DB::beginTransaction();
            try {
                $auction = $this->biddingService->createAuction([
                    'seller_id' => $params['seller_id'],
                    'title' => $params['title'],
                    'description' => $params['description'],
                    'starting_price' => $params['starting_price'],
                    'reserve_price' => $params['reserve_price'] ?? null,
                    'buy_now_price' => $params['buy_now_price'] ?? null,
                    'currency' => $params['currency'],
                    'category_id' => $params['category_id'],
                    'images' => $params['images'] ?? [],
                    'start_time' => $params['start_time'],
                    'end_time' => $params['end_time'],
                    'shipping_options' => $params['shipping_options'] ?? [],
                ]);
                
                DB::commit();
                
                // Clear rate limiting on successful creation
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'auction' => $auction,
                    'created_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Increment rate limiting on failed creation
                RateLimiter::hit($key, 300); // 5 minutes
                
                throw new RuntimeException(
                    'Auction creation failed: ' . $e->getMessage(),
                    -32001,
                    ['seller_id' => $params['seller_id']]
                );
            }
        });
    }

    /**
     * Place bid on auction
     * 
     * @param array $params
     * @return array
     */
    public function placeBid(array $params): array
    {
        $this->validate($params, [
            'auction_id' => 'required|integer|min:1',
            'bidder_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'max_bid' => 'sometimes|numeric|min:0.01',
            'auto_bid' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Bidding@placeBid', $params, function () use ($params) {
            // Rate limiting for bidding
            $key = 'bid_place:' . $params['bidder_id'] . ':' . $params['auction_id'];
            if (RateLimiter::tooManyAttempts($key, 20)) {
                throw new RuntimeException(
                    'Too many bidding attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            DB::beginTransaction();
            try {
                $bid = $this->biddingService->placeBid([
                    'auction_id' => $params['auction_id'],
                    'bidder_id' => $params['bidder_id'],
                    'amount' => $params['amount'],
                    'max_bid' => $params['max_bid'] ?? null,
                    'auto_bid' => $params['auto_bid'] ?? false,
                ]);
                
                DB::commit();
                
                // Clear rate limiting on successful bid
                RateLimiter::clear($key);
                
                // Clear auction cache
                Cache::forget('auction:' . $params['auction_id'] . ':*');
                
                return [
                    'success' => true,
                    'bid' => $bid,
                    'placed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Increment rate limiting on failed bid
                RateLimiter::hit($key, 60); // 1 minute
                
                throw new RuntimeException(
                    'Bid placement failed: ' . $e->getMessage(),
                    -32002,
                    ['auction_id' => $params['auction_id'], 'amount' => $params['amount']]
                );
            }
        });
    }

    /**
     * Get auction by ID
     * 
     * @param array $params
     * @return array
     */
    public function getAuction(array $params): array
    {
        $this->validate($params, [
            'auction_id' => 'required|integer|min:1',
            'include_bids' => 'sometimes|boolean',
            'include_watchers' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Bidding@getAuction', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'auction:' . $params['auction_id'] . ':' . 
                       ($params['include_bids'] ?? false ? 'with_bids' : 'no_bids') . ':' .
                       ($params['include_watchers'] ?? false ? 'with_watchers' : 'no_watchers');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $auction = $this->biddingService->getAuctionById(
                    $params['auction_id'],
                    $params['include_bids'] ?? true,
                    $params['include_watchers'] ?? false
                );
                
                if (!$auction) {
                    throw new RuntimeException(
                        'Auction not found',
                        -32001,
                        ['auction_id' => $params['auction_id']]
                    );
                }
                
                $result = [
                    'success' => true,
                    'auction' => $auction,
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 2 minutes (auctions change frequently)
                Cache::put($cacheKey, $result, 120);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve auction: ' . $e->getMessage(),
                    -32001,
                    ['auction_id' => $params['auction_id']]
                );
            }
        });
    }

    /**
     * Search auctions
     * 
     * @param array $params
     * @return array
     */
    public function searchAuctions(array $params): array
    {
        $this->validate($params, [
            'query' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|min:1',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'status' => 'sometimes|string|in:upcoming,active,ended,cancelled',
            'location' => 'sometimes|string|max:255',
            'seller_id' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|string|in:price,end_time,created_at,bid_count',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('Bidding@searchAuctions', $params, function () use ($params) {
            try {
                $results = $this->biddingService->searchAuctions([
                    'query' => $params['query'] ?? null,
                    'category_id' => $params['category_id'] ?? null,
                    'min_price' => $params['min_price'] ?? null,
                    'max_price' => $params['max_price'] ?? null,
                    'currency' => $params['currency'] ?? null,
                    'status' => $params['status'] ?? 'active',
                    'location' => $params['location'] ?? null,
                    'seller_id' => $params['seller_id'] ?? null,
                    'sort_by' => $params['sort_by'] ?? 'end_time',
                    'sort_order' => $params['sort_order'] ?? 'asc',
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                ]);
                
                return [
                    'success' => true,
                    'auctions' => $results['data'],
                    'pagination' => $results['pagination'],
                    'searched_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Auction search failed: ' . $e->getMessage(),
                    -32003,
                    ['search_params' => $params]
                );
            }
        });
    }

    /**
     * Get user bids
     * 
     * @param array $params
     * @return array
     */
    public function getUserBids(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'status' => 'sometimes|string|in:active,winning,outbid,won,lost',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('Bidding@getUserBids', $params, function () use ($params) {
            try {
                $results = $this->biddingService->getUserBids([
                    'user_id' => $params['user_id'],
                    'status' => $params['status'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                ]);
                
                return [
                    'success' => true,
                    'bids' => $results['data'],
                    'pagination' => $results['pagination'],
                    'retrieved_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user bids: ' . $e->getMessage(),
                    -32004,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Watch/unwatch auction
     * 
     * @param array $params
     * @return array
     */
    public function watchAuction(array $params): array
    {
        $this->validate($params, [
            'auction_id' => 'required|integer|min:1',
            'user_id' => 'required|integer|min:1',
            'watch' => 'required|boolean',
        ]);

        return $this->executeWithLogging('Bidding@watchAuction', $params, function () use ($params) {
            try {
                $result = $this->biddingService->watchAuction(
                    $params['auction_id'],
                    $params['user_id'],
                    $params['watch']
                );
                
                // Clear auction cache
                Cache::forget('auction:' . $params['auction_id'] . ':*');
                
                return [
                    'success' => true,
                    'watching' => $result['watching'],
                    'watchers_count' => $result['watchers_count'],
                    'updated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to update auction watch status: ' . $e->getMessage(),
                    -32005,
                    ['auction_id' => $params['auction_id'], 'user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * End auction early
     * 
     * @param array $params
     * @return array
     */
    public function endAuction(array $params): array
    {
        $this->validate($params, [
            'auction_id' => 'required|integer|min:1',
            'seller_id' => 'required|integer|min:1',
            'reason' => 'sometimes|string|max:500',
        ]);

        return $this->executeWithLogging('Bidding@endAuction', $params, function () use ($params) {
            DB::beginTransaction();
            try {
                $result = $this->biddingService->endAuction(
                    $params['auction_id'],
                    $params['seller_id'],
                    $params['reason'] ?? null
                );
                
                DB::commit();
                
                // Clear auction cache
                Cache::forget('auction:' . $params['auction_id'] . ':*');
                
                return [
                    'success' => true,
                    'auction' => $result['auction'],
                    'winner' => $result['winner'] ?? null,
                    'ended_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                throw new RuntimeException(
                    'Failed to end auction: ' . $e->getMessage(),
                    -32006,
                    ['auction_id' => $params['auction_id']]
                );
            }
        });
    }

    /**
     * Get auction statistics
     * 
     * @param array $params
     * @return array
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:today,week,month,quarter,year',
            'category_id' => 'sometimes|integer|min:1',
            'seller_id' => 'sometimes|integer|min:1',
        ]);

        return $this->executeWithLogging('Bidding@getStatistics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $categoryId = $params['category_id'] ?? null;
            $sellerId = $params['seller_id'] ?? null;
            
            // Check cache first
            $cacheKey = 'bidding_stats:' . $period . ':' . ($categoryId ?? 'all') . ':' . ($sellerId ?? 'all');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $statistics = $this->biddingService->getAuctionStatistics($period, $categoryId, $sellerId);
                
                $result = [
                    'success' => true,
                    'statistics' => $statistics,
                    'period' => $period,
                    'filters' => [
                        'category_id' => $categoryId,
                        'seller_id' => $sellerId,
                    ],
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 30 minutes
                Cache::put($cacheKey, $result, 1800);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve auction statistics: ' . $e->getMessage(),
                    -32007,
                    ['period' => $period]
                );
            }
        });
    }

    /**
     * Get live auction updates
     * 
     * @param array $params
     * @return array
     */
    public function getLiveUpdates(array $params): array
    {
        $this->validate($params, [
            'auction_id' => 'required|integer|min:1',
            'last_update' => 'sometimes|date',
        ]);

        return $this->executeWithLogging('Bidding@getLiveUpdates', $params, function () use ($params) {
            try {
                $updates = $this->biddingService->getLiveAuctionUpdates(
                    $params['auction_id'],
                    $params['last_update'] ?? null
                );
                
                return [
                    'success' => true,
                    'auction_id' => $params['auction_id'],
                    'updates' => $updates,
                    'timestamp' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve live updates: ' . $e->getMessage(),
                    -32008,
                    ['auction_id' => $params['auction_id']]
                );
            }
        });
    }

    /**
     * Set auto-bid configuration
     * 
     * @param array $params
     * @return array
     */
    public function setAutoBid(array $params): array
    {
        $this->validate($params, [
            'auction_id' => 'required|integer|min:1',
            'user_id' => 'required|integer|min:1',
            'max_amount' => 'required|numeric|min:0.01',
            'increment' => 'sometimes|numeric|min:0.01',
            'enabled' => 'required|boolean',
        ]);

        return $this->executeWithLogging('Bidding@setAutoBid', $params, function () use ($params) {
            try {
                $result = $this->biddingService->setAutoBidConfiguration([
                    'auction_id' => $params['auction_id'],
                    'user_id' => $params['user_id'],
                    'max_amount' => $params['max_amount'],
                    'increment' => $params['increment'] ?? null,
                    'enabled' => $params['enabled'],
                ]);
                
                return [
                    'success' => true,
                    'auto_bid' => $result,
                    'configured_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to configure auto-bid: ' . $e->getMessage(),
                    -32009,
                    ['auction_id' => $params['auction_id'], 'user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get auction categories
     * 
     * @param array $params
     * @return array
     */
    public function getCategories(array $params): array
    {
        $this->validate($params, [
            'parent_id' => 'sometimes|integer|min:1',
            'include_counts' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Bidding@getCategories', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'auction_categories:' . ($params['parent_id'] ?? 'root') . ':' . 
                       ($params['include_counts'] ?? false ? 'with_counts' : 'no_counts');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $categories = $this->biddingService->getAuctionCategories(
                    $params['parent_id'] ?? null,
                    $params['include_counts'] ?? false
                );
                
                $result = [
                    'success' => true,
                    'categories' => $categories,
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 1 hour
                Cache::put($cacheKey, $result, 3600);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve categories: ' . $e->getMessage(),
                    -32010,
                    ['parent_id' => $params['parent_id'] ?? null]
                );
            }
        });
    }
}
