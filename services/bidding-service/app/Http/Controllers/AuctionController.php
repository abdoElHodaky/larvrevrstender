<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\ProductImage;
use App\Services\BiddingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuctionController extends Controller
{
    private BiddingService $biddingService;

    public function __construct(BiddingService $biddingService)
    {
        $this->biddingService = $biddingService;
    }

    /**
     * Get auction details with bidding information
     */
    public function show(int $auctionId): JsonResponse
    {
        try {
            $auction = Auction::with(['productImages'])
                ->find($auctionId);

            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auction not found',
                    'error_code' => 'AUCTION_NOT_FOUND'
                ], 404);
            }

            // Get bid statistics
            $bidStats = $this->biddingService->getAuctionBids($auctionId, ['limit' => 1]);
            $statistics = [];
            
            if ($bidStats['success']) {
                $meta = $bidStats['data']['meta'];
                $statistics = [
                    'total_bids' => $meta['total'],
                    'total_bidders' => $meta['total_bidders'],
                    'highest_bid' => $meta['highest_bid'],
                ];
            }

            // Calculate time remaining
            $now = Carbon::now();
            $timeRemaining = 0;
            $auctionStatus = 'unknown';

            if ($now->lt($auction->starts_at)) {
                $auctionStatus = 'not_started';
                $timeRemaining = $auction->starts_at->diffInSeconds($now);
            } elseif ($now->between($auction->starts_at, $auction->ends_at)) {
                $auctionStatus = 'active';
                $timeRemaining = $auction->ends_at->diffInSeconds($now);
            } else {
                $auctionStatus = 'ended';
                $timeRemaining = 0;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'auction' => $auction,
                    'statistics' => $statistics,
                    'time_remaining' => $timeRemaining,
                    'auction_status' => $auctionStatus,
                    'is_reserve_met' => $auction->reserve_price ? 
                        ($auction->current_highest_bid >= $auction->reserve_price) : true,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AuctionController@show failed', [
                'error' => $e->getMessage(),
                'auction_id' => $auctionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving auction details',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Create a new auction (for bidding service context)
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

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'vehicle_id' => 'required|integer',
                'starting_price' => 'required|numeric|min:0.01',
                'reserve_price' => 'nullable|numeric|min:0.01',
                'starts_at' => 'required|date|after:now',
                'ends_at' => 'required|date|after:starts_at',
                'images' => 'nullable|array',
                'images.*' => 'nullable|array',
                'images.*.image_path' => 'required_with:images.*|string',
                'images.*.image_name' => 'required_with:images.*|string',
                'images.*.original_name' => 'required_with:images.*|string',
                'images.*.file_size' => 'required_with:images.*|integer',
                'images.*.mime_type' => 'required_with:images.*|string',
                'images.*.alt_text' => 'nullable|string',
                'images.*.description' => 'nullable|string',
                'images.*.is_primary' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Create auction
            $auctionData = $request->only([
                'title', 'description', 'vehicle_id', 'starting_price', 
                'reserve_price', 'starts_at', 'ends_at'
            ]);
            $auctionData['created_by'] = $userId;
            $auctionData['status'] = 'draft'; // Start as draft, can be activated later

            $auction = Auction::create($auctionData);

            // Add product images if provided
            if ($request->has('images') && is_array($request->input('images'))) {
                foreach ($request->input('images') as $index => $imageData) {
                    ProductImage::create([
                        'auction_id' => $auction->id,
                        'image_path' => $imageData['image_path'],
                        'image_name' => $imageData['image_name'],
                        'original_name' => $imageData['original_name'],
                        'file_size' => $imageData['file_size'],
                        'mime_type' => $imageData['mime_type'],
                        'alt_text' => $imageData['alt_text'] ?? null,
                        'description' => $imageData['description'] ?? null,
                        'sort_order' => $index,
                        'is_primary' => $imageData['is_primary'] ?? ($index === 0),
                    ]);
                }
            }

            DB::commit();

            Log::info('Auction created successfully', [
                'auction_id' => $auction->id,
                'created_by' => $userId,
                'title' => $auction->title
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Auction created successfully',
                'data' => [
                    'auction' => $auction->load('productImages')
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AuctionController@store failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the auction',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Update auction status
     */
    public function updateStatus(Request $request, int $auctionId): JsonResponse
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
                'status' => 'required|string|in:draft,active,completed,cancelled,suspended',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $auction = Auction::find($auctionId);
            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auction not found',
                    'error_code' => 'AUCTION_NOT_FOUND'
                ], 404);
            }

            // Verify user is auction owner (or admin - would need additional auth check)
            if ($auction->created_by != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update your own auctions',
                    'error_code' => 'UNAUTHORIZED_ACTION'
                ], 403);
            }

            $oldStatus = $auction->status;
            $newStatus = $request->input('status');

            // Validate status transition
            $validTransitions = $this->getValidStatusTransitions($oldStatus);
            if (!in_array($newStatus, $validTransitions)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot change status from '{$oldStatus}' to '{$newStatus}'",
                    'error_code' => 'INVALID_STATUS_TRANSITION'
                ], 400);
            }

            // Update auction
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'completed') {
                $updateData['completed_at'] = now();
            }

            $auction->update($updateData);

            Log::info('Auction status updated', [
                'auction_id' => $auctionId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => $userId,
                'reason' => $request->input('reason')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Auction status updated successfully',
                'data' => [
                    'auction' => $auction,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AuctionController@updateStatus failed', [
                'error' => $e->getMessage(),
                'auction_id' => $auctionId,
                'user_id' => $userId ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating auction status',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Get auctions with filtering (for bidding context)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|string|in:draft,active,completed,cancelled,suspended',
                'created_by' => 'nullable|integer',
                'vehicle_id' => 'nullable|integer',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'starts_after' => 'nullable|date',
                'ends_before' => 'nullable|date',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
                'sort_by' => 'nullable|string|in:created_at,starts_at,ends_at,starting_price,current_highest_bid',
                'sort_direction' => 'nullable|string|in:asc,desc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = Auction::with(['productImages']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('created_by')) {
                $query->where('created_by', $request->input('created_by'));
            }

            if ($request->has('vehicle_id')) {
                $query->where('vehicle_id', $request->input('vehicle_id'));
            }

            if ($request->has('min_price')) {
                $query->where('starting_price', '>=', $request->input('min_price'));
            }

            if ($request->has('max_price')) {
                $query->where('starting_price', '<=', $request->input('max_price'));
            }

            if ($request->has('starts_after')) {
                $query->where('starts_at', '>=', $request->input('starts_after'));
            }

            if ($request->has('ends_before')) {
                $query->where('ends_at', '<=', $request->input('ends_before'));
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $limit = min($request->input('limit', 20), 100);
            $offset = $request->input('offset', 0);

            $total = $query->count();
            $auctions = $query->skip($offset)->take($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'auctions' => $auctions,
                    'meta' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AuctionController@index failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving auctions',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Get valid status transitions for an auction
     */
    private function getValidStatusTransitions(string $currentStatus): array
    {
        $transitions = [
            'draft' => ['active', 'cancelled'],
            'active' => ['completed', 'cancelled', 'suspended'],
            'suspended' => ['active', 'cancelled'],
            'completed' => [], // Final state
            'cancelled' => [], // Final state
        ];

        return $transitions[$currentStatus] ?? [];
    }

    /**
     * Get auction with bid history
     */
    public function getAuctionWithBids(int $auctionId): JsonResponse
    {
        try {
            $auction = Auction::with(['productImages'])->find($auctionId);
            
            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auction not found',
                    'error_code' => 'AUCTION_NOT_FOUND'
                ], 404);
            }

            // Get bids for this auction
            $bidsResult = $this->biddingService->getAuctionBids($auctionId, [
                'limit' => 50, // Get top 50 bids
                'status' => 'pending'
            ]);

            $bids = [];
            $statistics = [];
            
            if ($bidsResult['success']) {
                $bids = $bidsResult['data']['bids'];
                $statistics = $bidsResult['data']['meta'];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'auction' => $auction,
                    'bids' => $bids,
                    'statistics' => $statistics
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AuctionController@getAuctionWithBids failed', [
                'error' => $e->getMessage(),
                'auction_id' => $auctionId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving auction with bids',
                'error_code' => 'INTERNAL_ERROR'
            ], 500);
        }
    }
}
