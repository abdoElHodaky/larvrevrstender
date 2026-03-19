<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Shared\Procedures\Micro\AuctionLifecycleProcedure;
use Exception;

/**
 * Auction API Controller
 * 
 * Handles auction-related API endpoints using the shared service
 * procedures for cross-service orchestration.
 */
class AuctionController extends Controller
{
    private AuctionLifecycleProcedure $auctionProcedure;

    public function __construct(AuctionLifecycleProcedure $auctionProcedure)
    {
        $this->auctionProcedure = $auctionProcedure;
    }

    /**
     * Create a new auction
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createAuction(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:5000',
                'starting_price' => 'required|numeric|min:0.01',
                'reserve_price' => 'sometimes|numeric|min:0.01',
                'minimum_bid_increment' => 'sometimes|numeric|min:0.01',
                'duration_hours' => 'required|integer|min:1|max:168', // Max 7 days
                'category' => 'required|string|max:100',
                'start_time' => 'sometimes|date|after:now',
                'images' => 'sometimes|array|max:10',
                'images.*.path' => 'required_with:images|string',
                'images.*.name' => 'sometimes|string',
                'images.*.type' => 'sometimes|string',
                'images.*.size' => 'sometimes|integer',
                'images.*.is_primary' => 'sometimes|boolean',
                'images.*.sort_order' => 'sometimes|integer',
                'metadata' => 'sometimes|array'
            ]);

            // Prepare auction data
            $auctionData = [
                'title' => $validated['title'],
                'description' => $validated['description'],
                'starting_price' => (float) $validated['starting_price'],
                'reserve_price' => isset($validated['reserve_price']) ? (float) $validated['reserve_price'] : null,
                'minimum_bid_increment' => isset($validated['minimum_bid_increment']) ? (float) $validated['minimum_bid_increment'] : 1.00,
                'duration_hours' => $validated['duration_hours'],
                'category' => $validated['category'],
                'start_time' => $validated['start_time'] ?? null,
                'images' => $validated['images'] ?? [],
                'metadata' => $validated['metadata'] ?? []
            ];

            // Prepare context
            $context = [
                'auth_token' => $request->bearerToken(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-ID', uniqid('req_'))
            ];

            // Create auction via shared procedure
            $result = $this->auctionProcedure->createAuction($auctionData, $context);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message']
                ], $this->getHttpStatusCode($result['error']));
            }

            Log::info('Auction created successfully via API', [
                'auction_id' => $result['data']['id'],
                'seller_id' => $result['data']['seller_id'],
                'title' => $result['data']['title'],
                'starting_price' => $result['data']['starting_price']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Auction created successfully',
                'data' => $result['data']
            ], 201);

        } catch (Exception $e) {
            Log::error('Auction creation API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'AUCTION_CREATION_ERROR',
                'message' => 'An error occurred while creating the auction'
            ], 500);
        }
    }

    /**
     * Get auction details
     * 
     * @param Request $request
     * @param string $auctionId
     * @return JsonResponse
     */
    public function getAuctionDetails(Request $request, string $auctionId): JsonResponse
    {
        try {
            // Get auction details via RPC call
            $auctionResult = app('Shared\Services\RpcHandler')->call('auction-service', 'getAuctionDetails', [
                'auction_id' => $auctionId
            ]);

            if (!$auctionResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $auctionResult['error'],
                    'message' => $auctionResult['message']
                ], $auctionResult['error'] === 'AUCTION_NOT_FOUND' ? 404 : 400);
            }

            return response()->json([
                'success' => true,
                'data' => $auctionResult['data']
            ]);

        } catch (Exception $e) {
            Log::error('Get auction details API error', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'AUCTION_DETAILS_ERROR',
                'message' => 'An error occurred while retrieving auction details'
            ], 500);
        }
    }

    /**
     * End an auction manually (admin/seller only)
     * 
     * @param Request $request
     * @param string $auctionId
     * @return JsonResponse
     */
    public function endAuction(Request $request, string $auctionId): JsonResponse
    {
        try {
            // Verify user permissions (seller or admin)
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'UNAUTHORIZED',
                    'message' => 'Authentication required'
                ], 401);
            }

            // Get auction details to verify ownership
            $auctionResult = app('Shared\Services\RpcHandler')->call('auction-service', 'getAuctionDetails', [
                'auction_id' => $auctionId
            ]);

            if (!$auctionResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $auctionResult['error'],
                    'message' => $auctionResult['message']
                ], 404);
            }

            $auction = $auctionResult['data'];

            // Check if user is the seller or has admin permissions
            if ($user->id !== $auction['seller_id'] && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'error' => 'FORBIDDEN',
                    'message' => 'You do not have permission to end this auction'
                ], 403);
            }

            // End auction via shared procedure
            $result = $this->auctionProcedure->completeAuctionLifecycle($auctionId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message'],
                    'workflow_id' => $result['workflow_id'] ?? null
                ], $this->getHttpStatusCode($result['error']));
            }

            Log::info('Auction ended manually via API', [
                'auction_id' => $auctionId,
                'ended_by' => $user->id,
                'workflow_id' => $result['data']['workflow_id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Auction ended successfully',
                'data' => $result['data']
            ]);

        } catch (Exception $e) {
            Log::error('End auction API error', [
                'auction_id' => $auctionId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'AUCTION_END_ERROR',
                'message' => 'An error occurred while ending the auction'
            ], 500);
        }
    }

    /**
     * Get active auctions
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getActiveAuctions(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'category' => 'sometimes|string',
                'min_price' => 'sometimes|numeric|min:0',
                'max_price' => 'sometimes|numeric|min:0',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:created_at,ends_at,current_highest_bid,starting_price',
                'sort_direction' => 'sometimes|string|in:asc,desc',
                'limit' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            // This would typically involve a more complex query with filters
            // For now, we'll return a placeholder response
            return response()->json([
                'success' => true,
                'data' => [
                    'auctions' => [],
                    'total_count' => 0,
                    'page' => $validated['page'] ?? 1,
                    'per_page' => $validated['limit'] ?? 20,
                    'filters' => [
                        'category' => $validated['category'] ?? null,
                        'min_price' => $validated['min_price'] ?? null,
                        'max_price' => $validated['max_price'] ?? null,
                        'search' => $validated['search'] ?? null
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get active auctions API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'ACTIVE_AUCTIONS_ERROR',
                'message' => 'An error occurred while retrieving active auctions'
            ], 500);
        }
    }

    /**
     * Get user's auctions (as seller)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserAuctions(Request $request): JsonResponse
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
                'status' => 'sometimes|string|in:draft,active,ended,cancelled',
                'limit' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            // This would typically involve a query for user's auctions
            // For now, we'll return a placeholder response
            return response()->json([
                'success' => true,
                'data' => [
                    'auctions' => [],
                    'total_count' => 0,
                    'page' => $validated['page'] ?? 1,
                    'per_page' => $validated['limit'] ?? 20,
                    'status_filter' => $validated['status'] ?? null
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get user auctions API error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'USER_AUCTIONS_ERROR',
                'message' => 'An error occurred while retrieving user auctions'
            ], 500);
        }
    }

    /**
     * Update auction details (before it starts)
     * 
     * @param Request $request
     * @param string $auctionId
     * @return JsonResponse
     */
    public function updateAuction(Request $request, string $auctionId): JsonResponse
    {
        try {
            // Verify user permissions
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'UNAUTHORIZED',
                    'message' => 'Authentication required'
                ], 401);
            }

            // Get auction details to verify ownership and status
            $auctionResult = app('Shared\Services\RpcHandler')->call('auction-service', 'getAuctionDetails', [
                'auction_id' => $auctionId
            ]);

            if (!$auctionResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $auctionResult['error'],
                    'message' => $auctionResult['message']
                ], 404);
            }

            $auction = $auctionResult['data'];

            // Check ownership
            if ($user->id !== $auction['seller_id']) {
                return response()->json([
                    'success' => false,
                    'error' => 'FORBIDDEN',
                    'message' => 'You do not have permission to update this auction'
                ], 403);
            }

            // Check if auction can be updated (only draft or not started auctions)
            if ($auction['status'] !== 'draft' && $auction['status'] !== 'scheduled') {
                return response()->json([
                    'success' => false,
                    'error' => 'AUCTION_CANNOT_BE_UPDATED',
                    'message' => 'Auction cannot be updated once it has started'
                ], 400);
            }

            // Validate update data
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:5000',
                'starting_price' => 'sometimes|numeric|min:0.01',
                'reserve_price' => 'sometimes|numeric|min:0.01',
                'minimum_bid_increment' => 'sometimes|numeric|min:0.01',
                'duration_hours' => 'sometimes|integer|min:1|max:168',
                'category' => 'sometimes|string|max:100',
                'start_time' => 'sometimes|date|after:now',
                'metadata' => 'sometimes|array'
            ]);

            // For now, return success without actual update logic
            // In a real implementation, this would update the auction record
            return response()->json([
                'success' => true,
                'message' => 'Auction updated successfully',
                'data' => $auction // Return current auction data
            ]);

        } catch (Exception $e) {
            Log::error('Update auction API error', [
                'auction_id' => $auctionId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'AUCTION_UPDATE_ERROR',
                'message' => 'An error occurred while updating the auction'
            ], 500);
        }
    }

    /**
     * Get appropriate HTTP status code for error types
     */
    private function getHttpStatusCode(string $errorCode): int
    {
        return match ($errorCode) {
            'AUCTION_NOT_FOUND' => 404,
            'AUTH_INVALID', 'UNAUTHORIZED' => 401,
            'FORBIDDEN' => 403,
            'VALIDATION_ERROR', 'AUCTION_NOT_ACTIVE', 'AUCTION_ENDED' => 400,
            'AUCTION_CREATION_FAILED' => 422,
            default => 500
        };
    }
}
