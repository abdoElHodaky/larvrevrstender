<?php

namespace App\RPC\Procedures;

use Shared\Core\BaseProcedure;
use App\Models\Auction;
use App\Models\Bid;
use App\Services\AuctionService;
use App\Services\BidService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * RPC Procedures for Auction Operations (from Bidding Service perspective)
 * 
 * Handles auction-related RPC calls that involve bidding operations.
 */
class AuctionProcedure extends BaseProcedure
{
    protected AuctionService $auctionService;
    protected BidService $bidService;
    
    public function __construct(AuctionService $auctionService, BidService $bidService)
    {
        $this->auctionService = $auctionService;
        $this->bidService = $bidService;
    }
    
    /**
     * Initialize auction for bidding
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function initialize(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
                'starting_price' => 'required|numeric|min:0',
                'reserve_price' => 'numeric|min:0',
                'increment_amount' => 'numeric|min:0.01',
                'starts_at' => 'required|date',
                'ends_at' => 'required|date|after:starts_at',
                'auto_extend' => 'boolean',
                'extend_minutes' => 'integer|min:1|max:60',
                'metadata' => 'array',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->auctionService->initializeForBidding($params);
            
            if ($result['success']) {
                return $this->successResponse([
                    'auction' => $result['auction'],
                    'message' => 'Auction initialized for bidding successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('AuctionProcedure::initialize failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to initialize auction', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Update highest bid information for auction
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function updateHighestBid(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
                'bid_data' => 'required|array',
                'bid_data.bid_id' => 'required|integer|min:1',
                'bid_data.user_id' => 'required|integer|min:1',
                'bid_data.amount' => 'required|numeric|min:0',
                'bid_data.placed_at' => 'required|date',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $auction = Auction::find($params['auction_id']);
            if (!$auction) {
                return $this->errorResponse('Auction not found', ['auction_id' => $params['auction_id']], 404);
            }
            
            $bidData = $params['bid_data'];
            
            // Update auction with highest bid information
            $auction->current_highest_bid_id = $bidData['bid_id'];
            $auction->current_highest_amount = $bidData['amount'];
            $auction->current_highest_bidder_id = $bidData['user_id'];
            $auction->last_bid_at = $bidData['placed_at'];
            $auction->total_bids = $auction->total_bids + 1;
            $auction->updated_at = now();
            $auction->save();
            
            return $this->successResponse([
                'auction' => $auction->toArray(),
                'message' => 'Highest bid updated successfully',
            ]);
            
        } catch (Exception $e) {
            Log::error('AuctionProcedure::updateHighestBid failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to update highest bid', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Validate bid eligibility for user
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function validateBidEligibility(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer|min:1',
                'auction_id' => 'required|integer|min:1',
                'bid_amount' => 'required|numeric|min:0.01',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->bidService->validateBidEligibility(
                $params['user_id'],
                $params['auction_id'],
                $params['bid_amount']
            );
            
            return $this->successResponse([
                'eligible' => $result['eligible'],
                'reasons' => $result['reasons'] ?? [],
                'minimum_bid_amount' => $result['minimum_bid_amount'] ?? null,
                'user_id' => $params['user_id'],
                'auction_id' => $params['auction_id'],
                'requested_amount' => $params['bid_amount'],
            ]);
            
        } catch (Exception $e) {
            Log::error('AuctionProcedure::validateBidEligibility failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to validate bid eligibility', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get auction status from bidding perspective
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function getStatus(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $auction = Auction::find($params['auction_id']);
            if (!$auction) {
                return $this->errorResponse('Auction not found', ['auction_id' => $params['auction_id']], 404);
            }
            
            $highestBid = null;
            if ($auction->current_highest_bid_id) {
                $highestBid = Bid::find($auction->current_highest_bid_id);
            }
            
            $status = [
                'auction_id' => $auction->id,
                'status' => $auction->status,
                'is_active' => $auction->status === 'active' && now()->between($auction->starts_at, $auction->ends_at),
                'starts_at' => $auction->starts_at,
                'ends_at' => $auction->ends_at,
                'current_highest_amount' => $auction->current_highest_amount,
                'current_highest_bidder_id' => $auction->current_highest_bidder_id,
                'total_bids' => $auction->total_bids,
                'last_bid_at' => $auction->last_bid_at,
                'highest_bid' => $highestBid ? $highestBid->toArray() : null,
            ];
            
            return $this->successResponse($status);
            
        } catch (Exception $e) {
            Log::error('AuctionProcedure::getStatus failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to get auction status', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Close auction from bidding service
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function close(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
                'closure_data' => 'array',
                'closure_data.winner_id' => 'integer|min:1',
                'closure_data.final_price' => 'numeric|min:0',
                'closure_data.reason' => 'string|max:255',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $result = $this->auctionService->closeAuction($params['auction_id'], $params['closure_data'] ?? []);
            
            if ($result['success']) {
                return $this->successResponse([
                    'auction' => $result['auction'],
                    'message' => 'Auction closed successfully',
                ]);
            } else {
                return $this->errorResponse($result['message'], $result['errors'] ?? [], $result['code'] ?? 400);
            }
            
        } catch (Exception $e) {
            Log::error('AuctionProcedure::close failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to close auction', ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Check if auction is active and accepting bids
     *
     * @param array $params RPC parameters
     * @return array RPC response
     */
    public function isActive(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'auction_id' => 'required|integer|min:1',
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors()->toArray(), 400);
            }
            
            $auction = Auction::find($params['auction_id']);
            if (!$auction) {
                return $this->errorResponse('Auction not found', ['auction_id' => $params['auction_id']], 404);
            }
            
            $isActive = $auction->status === 'active' && 
                       now()->between($auction->starts_at, $auction->ends_at);
            
            return $this->successResponse([
                'is_active' => $isActive,
                'auction_id' => $params['auction_id'],
                'status' => $auction->status,
                'starts_at' => $auction->starts_at,
                'ends_at' => $auction->ends_at,
                'current_time' => now(),
            ]);
            
        } catch (Exception $e) {
            Log::error('AuctionProcedure::isActive failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Failed to check auction status', ['error' => $e->getMessage()], 500);
        }
    }
}
