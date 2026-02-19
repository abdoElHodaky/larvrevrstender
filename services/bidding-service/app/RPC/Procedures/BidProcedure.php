<?php

namespace App\RPC\Procedures;

use Shared\Procedures\BaseProcedure;
use App\Models\Bid;
use App\Models\Auction;
use App\Services\BidService;
use App\Services\AuctionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * RPC Procedures for Bid Operations
 * 
 * Handles all bid-related RPC calls from other services.
 */
class BidProcedure extends BaseProcedure
{
    protected BidService $bidService;
    protected AuctionService $auctionService;
    
    public function __construct(BidService $bidService, AuctionService $auctionService)
    {
        $this->bidService = $bidService;
        $this->auctionService = $auctionService;
    }
    
    /**
     * Get bids for specific auction
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getByAuction(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
                'filters' => 'array',
                'filters.status' => 'string|in:active,withdrawn,expired,winning',
                'filters.user_id' => 'integer|min:1',
                'filters.min_amount' => 'numeric|min:0',
                'filters.max_amount' => 'numeric|min:0',
                'limit' => 'integer|min:1|max:100',
                'offset' => 'integer|min:0',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $auctionId = $params['auction_id'];
            $filters = $params['filters'] ?? [];
            $limit = $params['limit'] ?? 50;
            $offset = $params['offset'] ?? 0;
            
            // Check if auction exists
            $auction = Auction::find($auctionId);
            if (!$auction) {
                return $this->errorResponse('Auction not found', ['auction_id' => $auctionId], 404);
            }
            
            $query = Bid::where('auction_id', $auctionId);
            
            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }
            
            if (isset($filters['min_amount'])) {
                $query->where('amount', '>=', $filters['min_amount']);
            }
            
            if (isset($filters['max_amount'])) {
                $query->where('amount', '<=', $filters['max_amount']);
            }
            
            $total = $query->count();
            $bids = $query->orderBy('created_at', 'desc')
                         ->limit($limit)
                         ->offset($offset)
                         ->get();
            
            return $this->successResponse([
                'bids' => $bids->toArray(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'auction_id' => $auctionId,
            ]);
            
        } catch (Exception $e) {
            Log::error('BidProcedure::getByAuction failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->errorResponse('Failed to retrieve bids', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get highest bid for auction
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getHighest(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $auctionId = $params['auction_id'];
            
            $highestBid = Bid::where('auction_id', $auctionId)
                            ->where('status', 'active')
                            ->orderBy('amount', 'desc')
                            ->first();
            
            if (!$highestBid) {
                return $this->successResponse([
                    'highest_bid' => null,
                    'auction_id' => $auctionId,
                ]);
            }
            
            return $this->successResponse([
                'highest_bid' => $highestBid->toArray(),
                'auction_id' => $auctionId,
            ]);
            
        } catch (Exception $e) {
            Log::error('BidProcedure::getHighest failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get highest bid', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Place a new bid
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function place(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
                'user_id' => 'required|integer|min:1',
                'amount' => 'required|numeric|min:0.01',
                'bid_type' => 'string|in:regular,auto,proxy',
                'max_amount' => 'numeric|min:0.01',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->bidService->placeBid($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'bid' => $result['bid'],
                    'message' => 'Bid placed successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('BidProcedure::place failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to place bid', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Update bid status
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function updateStatus(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'bid_id' => 'required|integer|min:1',
                'status' => 'required|string|in:active,withdrawn,expired,winning',
                'reason' => 'string|max:255',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $bid = Bid::find($params['bid_id']);
            if (!$bid) {
                return $this->errorResponse('Bid not found', ['bid_id' => $params['bid_id']], 404);
            }
            
            $bid->status = $params['status'];
            if (isset($params['reason'])) {
                $bid->status_reason = $params['reason'];
            }
            if (isset($params['metadata'])) {
                $bid->metadata = array_merge($bid->metadata ?? [], $params['metadata']);
            }
            $bid->updated_at = now();
            $bid->save();
            
            return $this->successResponse([
                'bid' => $bid->toArray(),
                'message' => 'Bid status updated successfully',
            ]);
            
        } catch (Exception $e) {
            Log::error('BidProcedure::updateStatus failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to update bid status', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Cancel/withdraw a bid
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function cancel(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'bid_id' => 'required|integer|min:1',
                'reason' => 'string|max:255',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->bidService->cancelBid($params['bid_id'], $params['reason'] ?? null);
            
            if ($result['success']) {
                return $this->successResponse([
                    'bid' => $result['bid'],
                    'message' => 'Bid cancelled successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('BidProcedure::cancel failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to cancel bid', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get bid history with pagination
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getHistory(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
                'limit' => 'integer|min:1|max:100',
                'offset' => 'integer|min:0',
                'order_by' => 'string|in:created_at,amount,updated_at',
                'order_direction' => 'string|in:asc,desc',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $auctionId = $params['auction_id'];
            $limit = $params['limit'] ?? 50;
            $offset = $params['offset'] ?? 0;
            $orderBy = $params['order_by'] ?? 'created_at';
            $orderDirection = $params['order_direction'] ?? 'desc';
            
            $query = Bid::where('auction_id', $auctionId)
                        ->with(['user:id,name,email']);
            
            $total = $query->count();
            $bids = $query->orderBy($orderBy, $orderDirection)
                         ->limit($limit)
                         ->offset($offset)
                         ->get();
            
            return $this->successResponse([
                'history' => $bids->toArray(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'auction_id' => $auctionId,
            ]);
            
        } catch (Exception $e) {
            Log::error('BidProcedure::getHistory failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get bid history', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Check if user has active bids for auction
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function checkActive(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'auction_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $activeBids = Bid::where('user_id', $params['user_id'])
                            ->where('auction_id', $params['auction_id'])
                            ->where('status', 'active')
                            ->count();
            
            return $this->successResponse([
                'has_active_bids' => $activeBids > 0,
                'active_bid_count' => $activeBids,
                'user_id' => $params['user_id'],
                'auction_id' => $params['auction_id'],
            ]);
            
        } catch (Exception $e) {
            Log::error('BidProcedure::checkActive failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to check active bids', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get bid statistics for auction
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getStatistics(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $auctionId = $params['auction_id'];
            
            $stats = [
                'total_bids' => Bid::where('auction_id', $auctionId)->count(),
                'active_bids' => Bid::where('auction_id', $auctionId)->where('status', 'active')->count(),
                'unique_bidders' => Bid::where('auction_id', $auctionId)->distinct('user_id')->count(),
                'highest_amount' => Bid::where('auction_id', $auctionId)->where('status', 'active')->max('amount') ?? 0,
                'average_amount' => Bid::where('auction_id', $auctionId)->where('status', 'active')->avg('amount') ?? 0,
                'latest_bid_at' => Bid::where('auction_id', $auctionId)->max('created_at'),
            ];
            
            return $this->successResponse([
                'statistics' => $stats,
                'auction_id' => $auctionId,
            ]);
            
        } catch (Exception $e) {
            Log::error('BidProcedure::getStatistics failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get bid statistics', ['error' => $e->getMessage()], 500);
        }
    }
}

