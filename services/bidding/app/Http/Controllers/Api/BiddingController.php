<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Shared\Procedures\Micro\BiddingLifecycleProcedure;
use Exception;

/**
 * Bidding API Controller
 * 
 * Handles bidding-related API endpoints using the shared service
 * procedures for cross-service orchestration.
 */
class BiddingController extends Controller
{
    private BiddingLifecycleProcedure $biddingProcedure;

    public function __construct(BiddingLifecycleProcedure $biddingProcedure)
    {
        $this->biddingProcedure = $biddingProcedure;
    }

    /**
     * Place a bid on an auction
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function placeBid(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'auction_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'bid_type' => 'sometimes|string|in:standard,auto,proxy',
                'attachments' => 'sometimes|array',
                'attachments.*.file_path' => 'required_with:attachments|string',
                'attachments.*.file_name' => 'required_with:attachments|string',
                'attachments.*.file_type' => 'required_with:attachments|string',
                'attachments.*.file_size' => 'required_with:attachments|integer'
            ]);

            // Prepare bid data
            $bidData = [
                'auction_id' => $validated['auction_id'],
                'amount' => (float) $validated['amount'],
                'bid_type' => $validated['bid_type'] ?? 'standard',
                'attachments' => $validated['attachments'] ?? [],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ];

            // Prepare context
            $context = [
                'auth_token' => $request->bearerToken(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-ID', uniqid('req_'))
            ];

            // Execute bid placement workflow via shared procedure
            $result = $this->biddingProcedure->completeBidPlacement($bidData, $context);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message'],
                    'workflow_id' => $result['workflow_id'] ?? null
                ], $this->getHttpStatusCode($result['error']));
            }

            Log::info('Bid placed successfully via API', [
                'workflow_id' => $result['data']['workflow_id'],
                'bid_id' => $result['data']['bid']['id'],
                'auction_id' => $bidData['auction_id'],
                'amount' => $bidData['amount']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bid placed successfully',
                'data' => [
                    'workflow_id' => $result['data']['workflow_id'],
                    'bid' => $result['data']['bid'],
                    'auction' => $result['data']['auction'],
                    'broadcast_sent' => $result['data']['broadcast_sent'],
                    'transaction_id' => $result['data']['transaction_id']
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Bid placement API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'BID_PLACEMENT_ERROR',
                'message' => 'An error occurred while placing the bid'
            ], 500);
        }
    }

    /**
     * Get auction bids
     * 
     * @param Request $request
     * @param string $auctionId
     * @return JsonResponse
     */
    public function getAuctionBids(Request $request, string $auctionId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_by' => 'sometimes|string|in:amount,created_at',
                'order_direction' => 'sometimes|string|in:asc,desc',
                'limit' => 'sometimes|integer|min:1|max:500',
                'page' => 'sometimes|integer|min:1'
            ]);

            // Validate bid placement first to ensure user can see bids
            $validationResult = $this->biddingProcedure->validateBidPlacement([
                'auction_id' => $auctionId,
                'amount' => 1 // Dummy amount for validation
            ], [
                'auth_token' => $request->bearerToken(),
                'user_id' => $request->user()?->id
            ]);

            // Allow viewing bids even if validation fails (for transparency)
            // but log any issues
            if (!$validationResult['success']) {
                Log::info('User viewing bids for auction they cannot bid on', [
                    'auction_id' => $auctionId,
                    'user_id' => $request->user()?->id,
                    'validation_error' => $validationResult['error']
                ]);
            }

            // Get bids via RPC call
            $bidsResult = app('Shared\Services\RpcHandler')->call('bidding-service', 'getAuctionBids', [
                'auction_id' => $auctionId,
                'order_by' => $validated['order_by'] ?? 'created_at',
                'order_direction' => $validated['order_direction'] ?? 'desc',
                'limit' => $validated['limit'] ?? 100
            ]);

            if (!$bidsResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $bidsResult['error'],
                    'message' => $bidsResult['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $bidsResult['data']
            ]);

        } catch (Exception $e) {
            Log::error('Get auction bids API error', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'BIDS_RETRIEVAL_ERROR',
                'message' => 'An error occurred while retrieving bids'
            ], 500);
        }
    }

    /**
     * Get bid details
     * 
     * @param Request $request
     * @param string $bidId
     * @return JsonResponse
     */
    public function getBidDetails(Request $request, string $bidId): JsonResponse
    {
        try {
            // Get bid details via RPC call
            $bidResult = app('Shared\Services\RpcHandler')->call('bidding-service', 'getBidDetails', [
                'bid_id' => $bidId
            ]);

            if (!$bidResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $bidResult['error'],
                    'message' => $bidResult['message']
                ], $bidResult['error'] === 'BID_NOT_FOUND' ? 404 : 400);
            }

            // Check if user can view this bid (owner or auction seller)
            $bid = $bidResult['data'];
            $user = $request->user();
            
            if ($user && ($user->id !== $bid['user_id'])) {
                // Check if user is the auction seller
                $auctionResult = app('Shared\Services\RpcHandler')->call('auction-service', 'getAuctionDetails', [
                    'auction_id' => $bid['auction_id']
                ]);

                if ($auctionResult['success'] && $user->id !== $auctionResult['data']['seller_id']) {
                    // Hide sensitive information for non-owners
                    unset($bid['reservation_id'], $bid['metadata']);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $bid
            ]);

        } catch (Exception $e) {
            Log::error('Get bid details API error', [
                'bid_id' => $bidId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'BID_DETAILS_ERROR',
                'message' => 'An error occurred while retrieving bid details'
            ], 500);
        }
    }

    /**
     * Get user's bids
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserBids(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'UNAUTHORIZED',
                    'message' => 'Authentication required'
                ], 401);
            }

            $validated = $request->validate([
                'status' => 'sometimes|string|in:active,won,lost,cancelled',
                'limit' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            // This would typically involve a more complex query
            // For now, we'll return a placeholder response
            return response()->json([
                'success' => true,
                'data' => [
                    'bids' => [],
                    'total_count' => 0,
                    'page' => $validated['page'] ?? 1,
                    'per_page' => $validated['limit'] ?? 20
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get user bids API error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'USER_BIDS_ERROR',
                'message' => 'An error occurred while retrieving user bids'
            ], 500);
        }
    }

    /**
     * Validate bid placement (dry run)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateBid(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'auction_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01'
            ]);

            $bidData = [
                'auction_id' => $validated['auction_id'],
                'amount' => (float) $validated['amount']
            ];

            $context = [
                'auth_token' => $request->bearerToken(),
                'user_id' => $request->user()?->id
            ];

            // Validate bid placement without actually placing the bid
            $result = $this->biddingProcedure->validateBidPlacement($bidData, $context);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message']
                ], $this->getHttpStatusCode($result['error']));
            }

            return response()->json([
                'success' => true,
                'message' => 'Bid validation successful',
                'data' => $result['data']
            ]);

        } catch (Exception $e) {
            Log::error('Bid validation API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'BID_VALIDATION_ERROR',
                'message' => 'An error occurred while validating the bid'
            ], 500);
        }
    }

    /**
     * Get appropriate HTTP status code for error types
     */
    private function getHttpStatusCode(string $errorCode): int
    {
        return match ($errorCode) {
            'AUCTION_INVALID', 'AUCTION_NOT_FOUND' => 404,
            'AUTH_INVALID', 'UNAUTHORIZED' => 401,
            'INSUFFICIENT_FUNDS', 'BID_BELOW_RESERVE', 'BID_INCREMENT_TOO_LOW', 'BID_EXCEEDS_LIMIT' => 400,
            'FUND_RESERVATION_FAILED', 'BID_CREATION_FAILED' => 422,
            default => 500
        };
    }
}
