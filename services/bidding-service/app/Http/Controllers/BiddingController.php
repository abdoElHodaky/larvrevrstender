<?php

namespace App\Http\Controllers;

use App\Services\BiddingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BiddingController extends Controller
{
    public function __construct(
        private BiddingService$biddingService
    ) {
    }

    /**
     * Place a bid on an auction
     */
    public function placeBid(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $userId = $request->attributes->get('user_id');
            $user = $request->attributes->get('user');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'auction_id' => 'required|integer|exists:auctions,id',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'nullable|string|size:3',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $bidData = array_merge($request->validated(), [
                'user_id' => $userId
            ]);

            $result = $this->biddingService->placeBid($bidData);

            $statusCode = $result['success'] ? 201 : 400;
            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            Log::error('BiddingController@placeBid failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while placing the bid',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Get bid details
     */
    public function getBid(int $bidId): JsonResponse
    {
        try {
            $result = $this->biddingService->getBid($bidId);

            $statusCode = $result['success'] ? 200 : 404;
            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            Log::error('BiddingController@getBid failed', [
                'error' => $e->getMessage(),
                'bid_id' => $bidId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the bid',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Get all bids for the authenticated user
     */
    public function getUserBids(Request $request): JsonResponse
    {
        try {
            // Get authenticated user
            $userId = $request->attributes->get('user_id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'nullable|string|in:pending,accepted,rejected,withdrawn,outbid',
                'auction_id' => 'nullable|integer|exists:auctions,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $filters = $request->only(['status', 'auction_id', 'date_from', 'date_to', 'limit', 'offset']);
            $result = $this->biddingService->getUserBids($userId, $filters);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('BiddingController@getUserBids failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user bids',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Get all bids for a specific auction
     */
    public function getAuctionBids(Request $request, int $auctionId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|string|in:pending,accepted,rejected,withdrawn,outbid',
                'min_amount' => 'nullable|numeric|min:0',
                'max_amount' => 'nullable|numeric|min:0',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $filters = $request->only(['status', 'min_amount', 'max_amount', 'limit', 'offset']);
            $result = $this->biddingService->getAuctionBids($auctionId, $filters);

            $statusCode = $result['success'] ? 200 : 404;
            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            Log::error('BiddingController@getAuctionBids failed', [
                'error' => $e->getMessage(),
                'auction_id' => $auctionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving auction bids',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Update bid status (admin/auction owner only)
     */
    public function updateBidStatus(Request $request, int $bidId): JsonResponse
    {
        try {
            // Get authenticated user
            $userId = $request->attributes->get('user_id');
            $user = $request->attributes->get('user');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:pending,accepted,rejected,withdrawn,outbid',
                'reason' => 'nullable|string|max:500',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $metadata = $request->input('metadata', []);
            if ($request->has('reason')) {
                $metadata['reason'] = $request->input('reason');
            }
            $metadata['updated_by'] = $userId;
            $metadata['updated_at'] = now()->toISOString();

            $result = $this->biddingService->updateBidStatus(
                $bidId,
                $request->input('status'),
                $metadata
            );

            $statusCode = $result['success'] ? 200 : 404;
            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            Log::error('BiddingController@updateBidStatus failed', [
                'error' => $e->getMessage(),
                'bid_id' => $bidId,
                'user_id' => $userId ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating bid status',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Withdraw a bid (user can withdraw their own bid)
     */
    public function withdrawBid(Request $request, int $bidId): JsonResponse
    {
        try {
            // Get authenticated user
            $userId = $request->attributes->get('user_id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // First get the bid to verify ownership
            $bidResult = $this->biddingService->getBid($bidId);
            if (!$bidResult['success']) {
                return response()->json($bidResult, 404);
            }

            $bid = $bidResult['data']['bid'];
            if ($bid->user_id != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only withdraw your own bids',
                    'error_code' => 'UNAUTHORIZED_ACTION'
                ], 403);
            }

            if ($bid->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending bids can be withdrawn',
                    'error_code' => 'INVALID_BID_STATUS'
                ], 400);
            }

            $metadata = [
                'withdrawn_by' => $userId,
                'withdrawn_at' => now()->toISOString(),
                'reason' => 'User withdrawal'
            ];

            $result = $this->biddingService->updateBidStatus($bidId, 'withdrawn', $metadata);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('BiddingController@withdrawBid failed', [
                'error' => $e->getMessage(),
                'bid_id' => $bidId,
                'user_id' => $userId ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while withdrawing the bid',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Get bid statistics for an auction
     */
    public function getBidStatistics(int $auctionId): JsonResponse
    {
        try {
            // Get basic auction bids data
            $result = $this->biddingService->getAuctionBids($auctionId, ['limit' => 1]);
            
            if (!$result['success']) {
                return response()->json($result, 404);
            }

            $auction = $result['data']['auction'];
            $meta = $result['data']['meta'];

            $statistics = [
                'auction_id' => $auctionId,
                'total_bids' => $meta['total'],
                'total_bidders' => $meta['total_bidders'],
                'highest_bid' => $meta['highest_bid'],
                'starting_price' => $auction->starting_price,
                'reserve_price' => $auction->reserve_price,
                'auction_status' => $auction->status,
                'time_remaining' => $auction->ends_at > now() ? $auction->ends_at->diffInSeconds(now()) : 0,
                'is_reserve_met' => $auction->reserve_price ? ($meta['highest_bid'] >= $auction->reserve_price) : true,
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('BiddingController@getBidStatistics failed', [
                'error' => $e->getMessage(),
                'auction_id' => $auctionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving bid statistics',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }
}
