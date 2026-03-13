<?php

namespace App\Http\Controllers;

use App\Events\BidPlaced;
use App\Http\Clients\AuthServiceClient;
use App\Http\Clients\BiddingServiceClient;
use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BiddingController extends Controller
{
    public function __construct(
        protected BiddingServiceClient$biddingService,
        protected AuthServiceClient$authService
    ) {
    }

    /**
     * Place a bid on an auction.
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

            $validated = $request->validate([
                'auction_id' => 'required|integer|exists:auctions,id',
                'amount' => 'required|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'notes' => 'nullable|string|max:1000',
            ]);

            // Set the authenticated user as the bidder
            $validated['user_id'] = $userId;

            // Verify auction exists and is active
            $auction = Auction::findOrFail($validated['auction_id']);
            
            if (!$auction->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auction is not active'
                ], 422);
            }

            // Validate bidding eligibility
            $eligibilityResult = $this->authService->validateBiddingEligibility($userId, $validated['auction_id']);
            
            if (!$eligibilityResult['eligible']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bidding not allowed',
                    'reason' => $eligibilityResult['reason']
                ], 403);
            }

            // Validate bid amount against starting price and current highest bid
            if ($validated['amount'] < $auction->starting_price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bid amount must be at least the starting price'
                ], 422);
            }

            $highestBid = $auction->highestBid();
            if ($highestBid && $validated['amount'] <= $highestBid['amount']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bid amount must be higher than the current highest bid'
                ], 422);
            }

            // Place bid via RPC call to bidding-service
            $bidResult = $this->biddingService->placeBid($validated);

            if (!$bidResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to place bid'
                ], 500);
            }

            // Update auction's current highest bid if this bid is higher
            if (!$auction->current_highest_bid || $validated['amount'] > $auction->current_highest_bid) {
                $auction->update(['current_highest_bid' => $validated['amount']]);
            }

            // Log the bidding activity
            $this->authService->logAuctionActivity($userId, 'bid.placed', [
                'auction_id' => $validated['auction_id'],
                'auction_title' => $auction->title,
                'bid_amount' => $validated['amount'],
                'bid_id' => $bidResult['id'] ?? null
            ]);

            // Get previous bidder information if exists
            $previousBidder = null;
            if ($highestBid) {
                $previousBidder = $this->authService->getUser($highestBid['user_id']);
                if ($previousBidder) {
                    $previousBidder['bid_amount'] = $highestBid['amount'];
                }
            }

            // Fire bid placed event for notifications
            event(new BidPlaced(
                $auction,
                $bidResult,
                $user,
                $previousBidder
            ));

            return response()->json([
                'success' => true,
                'message' => 'Bid placed successfully',
                'data' => $bidResult
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to place bid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific bid by ID.
     */
    public function getBid(int $bidId): JsonResponse
    {
        try {
            $bid = $this->biddingService->getBid($bidId);

            if (!$bid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bid not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $bid
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all bids for a user.
     */
    public function getUserBids(int $userId): JsonResponse
    {
        try {
            // This would need to be implemented in the bidding service
            // For now, return empty array with proper structure
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'User bids endpoint not yet implemented in bidding service'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user bids',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all bids for an auction.
     */
    public function getAuctionBids(int $auctionId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            $bids = $this->biddingService->getAuctionBids($auctionId);

            return response()->json([
                'success' => true,
                'data' => $bids
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve auction bids',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update bid status.
     */
    public function updateBidStatus(Request $request, int $bidId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:pending,accepted,rejected,withdrawn',
                'reason' => 'nullable|string|max:500',
            ]);

            $success = $this->biddingService->updateBidStatus(
                $bidId,
                $validated['status'],
                $validated['reason'] ?? null
            );

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update bid status'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bid status updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bid status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel/withdraw a bid.
     */
    public function cancel(Request $request, int $bidId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $success = $this->biddingService->cancelBid($bidId, $validated['reason'] ?? null);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel bid'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bid cancelled successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel bid',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bid statistics for an auction.
     */
    public function getBidStats(int $auctionId): JsonResponse
    {
        try {
            // Verify auction exists
            $auction = Auction::findOrFail($auctionId);

            $bidCount = $auction->getBidCount();
            $highestBid = $auction->highestBid();

            return response()->json([
                'success' => true,
                'data' => [
                    'auction_id' => $auctionId,
                    'total_bids' => $bidCount,
                    'highest_bid' => $highestBid,
                    'starting_price' => $auction->starting_price,
                    'reserve_price' => $auction->reserve_price,
                    'current_highest_bid' => $auction->current_highest_bid,
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bid statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
