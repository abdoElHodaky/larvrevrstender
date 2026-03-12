<?php

namespace App\Http\Controllers;

use App\Events\AuctionCreated;
use App\Http\Clients\AuthServiceClient;
use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuctionController extends Controller
{
    public function __construct(
        protected AuthServiceClient$authService
    ) {
    }
    /**
     * Display a listing of auctions.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Auction::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            if ($request->has('vehicle_id')) {
                $query->where('vehicle_id', $request->vehicle_id);
            }

            // Apply search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $limit = min($request->get('limit', 20), 100);
            $offset = $request->get('offset', 0);

            $total = $query->count();
            $auctions = $query->skip($offset)->take($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $auctions,
                'meta' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve auctions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created auction.
     */
    public function store(Request $request): JsonResponse
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
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'vehicle_id' => 'required|integer',
                'starting_price' => 'required|numeric|min:0',
                'reserve_price' => 'nullable|numeric|min:0',
                'starts_at' => 'required|date|after:now',
                'ends_at' => 'required|date|after:starts_at',
            ]);

            // Validate auction creation authorization
            $authResult = $this->authService->validateAuctionCreation($userId, $validated);
            
            if (!$authResult['authorized']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auction creation not authorized',
                    'reason' => $authResult['reason']
                ], 403);
            }

            // Set the authenticated user as the creator
            $validated['created_by'] = $userId;
            $validated['status'] = 'pending';
            $validated['current_highest_bid'] = null;

            $auction = Auction::create($validated);

            // Log the activity
            $this->authService->logAuctionActivity($userId, 'auction.created', [
                'auction_id' => $auction->id,
                'auction_title' => $auction->title,
                'starting_price' => $auction->starting_price
            ]);

            // Fire auction created event for notifications
            event(new AuctionCreated($auction, $user));

            return response()->json([
                'success' => true,
                'message' => 'Auction created successfully',
                'data' => $auction
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
                'message' => 'Failed to create auction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified auction.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $auction = Auction::findOrFail($id);

            // Add computed attributes
            $auctionData = $auction->toArray();
            $auctionData['is_active'] = $auction->isActive();
            $auctionData['has_images'] = $auction->hasImages();
            $auctionData['primary_image_url'] = $auction->primary_image_url;
            $auctionData['image_count'] = $auction->image_count;
            
            // Add bid information via RPC
            $auctionData['bid_count'] = $auction->getBidCount();
            $auctionData['highest_bid'] = $auction->highestBid();

            return response()->json([
                'success' => true,
                'data' => $auctionData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve auction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified auction.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $auction = Auction::findOrFail($id);

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'starting_price' => 'sometimes|numeric|min:0',
                'reserve_price' => 'sometimes|nullable|numeric|min:0',
                'ends_at' => 'sometimes|date|after:starts_at',
                'status' => 'sometimes|in:pending,active,completed,cancelled',
            ]);

            $auction->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Auction updated successfully',
                'data' => $auction
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update auction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Close/end the specified auction.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        try {
            $auction = Auction::findOrFail($id);

            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $auction->update([
                'status' => 'completed',
                'ends_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Auction closed successfully',
                'data' => $auction
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close auction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bids for the specified auction.
     */
    public function getBids(int $id): JsonResponse
    {
        try {
            $auction = Auction::findOrFail($id);
            
            $bidHistory = $auction->getBidHistory();

            return response()->json([
                'success' => true,
                'data' => $bidHistory['data'],
                'meta' => [
                    'total' => $bidHistory['total'],
                    'limit' => $bidHistory['limit'],
                    'offset' => $bidHistory['offset']
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
                'message' => 'Failed to retrieve auction bids',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete the specified auction.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $auction = Auction::findOrFail($id);

            // Check if auction can be deleted (e.g., no active bids)
            if ($auction->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an active auction'
                ], 422);
            }

            $auction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Auction deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auction not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete auction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
