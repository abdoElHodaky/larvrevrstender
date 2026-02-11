<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\BidAttachment;
use App\Http\Clients\AuthServiceClient;
use App\Http\Clients\UserServiceClient;
use App\Http\Clients\NotificationServiceClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BiddingService
{
    private AuthServiceClient $authService;
    private UserServiceClient $userService;
    private NotificationServiceClient $notificationService;

    public function __construct(
        AuthServiceClient $authService,
        UserServiceClient $userService,
        NotificationServiceClient $notificationService
    ) {
        $this->authService = $authService;
        $this->userService = $userService;
        $this->notificationService = $notificationService;
    }

    /**
     * Place a bid on an auction with comprehensive validation
     */
    public function placeBid(array $bidData): array
    {
        try {
            DB::beginTransaction();

            // Validate auction exists and is active
            $auction = Auction::find($bidData['auction_id']);
            if (!$auction) {
                return [
                    'success' => false,
                    'message' => 'Auction not found',
                    'error_code' => 'AUCTION_NOT_FOUND'
                ];
            }

            // Validate auction is active and within time bounds
            $validationResult = $this->validateAuctionForBidding($auction);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                    'error_code' => $validationResult['error_code']
                ];
            }

            // Validate user authorization
            $userValidation = $this->validateUserForBidding($bidData['user_id'], $auction);
            if (!$userValidation['authorized']) {
                return [
                    'success' => false,
                    'message' => $userValidation['message'],
                    'error_code' => 'USER_NOT_AUTHORIZED'
                ];
            }

            // Validate bid amount
            $amountValidation = $this->validateBidAmount($bidData['amount'], $auction);
            if (!$amountValidation['valid']) {
                return [
                    'success' => false,
                    'message' => $amountValidation['message'],
                    'error_code' => 'INVALID_BID_AMOUNT'
                ];
            }

            // Check for existing active bid from same user
            $existingBid = Bid::where('auction_id', $auction->id)
                ->where('user_id', $bidData['user_id'])
                ->where('status', 'pending')
                ->first();

            if ($existingBid) {
                // Update existing bid if new amount is higher
                if ($bidData['amount'] > $existingBid->amount) {
                    $existingBid->update([
                        'amount' => $bidData['amount'],
                        'submitted_at' => now(),
                        'notes' => $bidData['notes'] ?? $existingBid->notes,
                        'currency' => $bidData['currency'] ?? 'SAR',
                    ]);
                    $bid = $existingBid;
                } else {
                    return [
                        'success' => false,
                        'message' => 'New bid amount must be higher than your existing bid',
                        'error_code' => 'BID_AMOUNT_TOO_LOW'
                    ];
                }
            } else {
                // Create new bid
                $bid = Bid::create([
                    'auction_id' => $auction->id,
                    'user_id' => $bidData['user_id'],
                    'amount' => $bidData['amount'],
                    'status' => 'pending',
                    'submitted_at' => now(),
                    'notes' => $bidData['notes'] ?? null,
                    'currency' => $bidData['currency'] ?? 'SAR',
                    'bid_increment' => $this->calculateBidIncrement($bidData['amount']),
                ]);
            }

            // Update auction's current highest bid if this is higher
            if (!$auction->current_highest_bid || $bidData['amount'] > $auction->current_highest_bid) {
                $auction->update([
                    'current_highest_bid' => $bidData['amount']
                ]);

                // Mark other bids as outbid
                Bid::where('auction_id', $auction->id)
                    ->where('id', '!=', $bid->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'outbid']);
            }

            // Log bid activity
            Log::info('Bid placed successfully', [
                'bid_id' => $bid->id,
                'auction_id' => $auction->id,
                'user_id' => $bidData['user_id'],
                'amount' => $bidData['amount']
            ]);

            // Send notifications asynchronously
            $this->sendBidNotifications($bid, $auction);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Bid placed successfully',
                'data' => [
                    'bid' => $bid->load(['auction', 'attachments']),
                    'is_highest_bid' => $bid->amount == $auction->current_highest_bid,
                    'auction_status' => $auction->status
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to place bid', [
                'error' => $e->getMessage(),
                'auction_id' => $bidData['auction_id'] ?? null,
                'user_id' => $bidData['user_id'] ?? null,
                'amount' => $bidData['amount'] ?? null
            ]);

            return [
                'success' => false,
                'message' => 'Failed to place bid. Please try again.',
                'error_code' => 'BID_PLACEMENT_FAILED'
            ];
        }
    }

    /**
     * Get bid details with related data
     */
    public function getBid(int $bidId): array
    {
        try {
            $bid = Bid::with(['auction', 'attachments'])
                ->find($bidId);

            if (!$bid) {
                return [
                    'success' => false,
                    'message' => 'Bid not found',
                    'error_code' => 'BID_NOT_FOUND'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'bid' => $bid,
                    'is_highest_bid' => $bid->amount == $bid->auction->current_highest_bid,
                    'auction_status' => $bid->auction->status
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get bid', [
                'error' => $e->getMessage(),
                'bid_id' => $bidId
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve bid',
                'error_code' => 'BID_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * Get all bids for a user with filtering
     */
    public function getUserBids(int $userId, array $filters = []): array
    {
        try {
            $query = Bid::with(['auction', 'attachments'])
                ->where('user_id', $userId);

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['auction_id'])) {
                $query->where('auction_id', $filters['auction_id']);
            }

            if (isset($filters['date_from'])) {
                $query->where('submitted_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('submitted_at', '<=', $filters['date_to']);
            }

            // Pagination
            $limit = min($filters['limit'] ?? 20, 100);
            $offset = $filters['offset'] ?? 0;

            $total = $query->count();
            $bids = $query->orderBy('submitted_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return [
                'success' => true,
                'data' => [
                    'bids' => $bids,
                    'meta' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total
                    ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user bids', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve user bids',
                'error_code' => 'USER_BIDS_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * Get all bids for an auction with ranking
     */
    public function getAuctionBids(int $auctionId, array $filters = []): array
    {
        try {
            $auction = Auction::find($auctionId);
            if (!$auction) {
                return [
                    'success' => false,
                    'message' => 'Auction not found',
                    'error_code' => 'AUCTION_NOT_FOUND'
                ];
            }

            $query = Bid::with(['attachments'])
                ->where('auction_id', $auctionId);

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['min_amount'])) {
                $query->where('amount', '>=', $filters['min_amount']);
            }

            if (isset($filters['max_amount'])) {
                $query->where('amount', '<=', $filters['max_amount']);
            }

            // Pagination
            $limit = min($filters['limit'] ?? 20, 100);
            $offset = $filters['offset'] ?? 0;

            $total = $query->count();
            $bids = $query->orderBy('amount', 'desc')
                ->orderBy('submitted_at', 'asc') // Earlier bids win ties
                ->skip($offset)
                ->take($limit)
                ->get();

            // Add ranking information
            $rankedBids = $bids->map(function ($bid, $index) {
                $bid->rank = $index + 1;
                $bid->is_winning = $index === 0;
                return $bid;
            });

            return [
                'success' => true,
                'data' => [
                    'auction' => $auction,
                    'bids' => $rankedBids,
                    'meta' => [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $total,
                        'highest_bid' => $auction->current_highest_bid,
                        'total_bidders' => Bid::where('auction_id', $auctionId)->distinct('user_id')->count()
                    ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get auction bids', [
                'error' => $e->getMessage(),
                'auction_id' => $auctionId
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve auction bids',
                'error_code' => 'AUCTION_BIDS_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * Update bid status with validation
     */
    public function updateBidStatus(int $bidId, string $status, array $metadata = []): array
    {
        try {
            $bid = Bid::find($bidId);
            if (!$bid) {
                return [
                    'success' => false,
                    'message' => 'Bid not found',
                    'error_code' => 'BID_NOT_FOUND'
                ];
            }

            $validStatuses = ['pending', 'accepted', 'rejected', 'withdrawn', 'outbid'];
            if (!in_array($status, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid bid status',
                    'error_code' => 'INVALID_STATUS'
                ];
            }

            $oldStatus = $bid->status;
            $bid->update([
                'status' => $status,
                'metadata' => array_merge($bid->metadata ?? [], $metadata)
            ]);

            // Handle status-specific logic
            if ($status === 'accepted') {
                $this->handleBidAcceptance($bid);
            } elseif ($status === 'rejected') {
                $this->handleBidRejection($bid);
            }

            Log::info('Bid status updated', [
                'bid_id' => $bidId,
                'old_status' => $oldStatus,
                'new_status' => $status
            ]);

            return [
                'success' => true,
                'message' => 'Bid status updated successfully',
                'data' => [
                    'bid' => $bid->load(['auction', 'attachments'])
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update bid status', [
                'error' => $e->getMessage(),
                'bid_id' => $bidId,
                'status' => $status
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update bid status',
                'error_code' => 'BID_STATUS_UPDATE_FAILED'
            ];
        }
    }

    /**
     * Validate auction is eligible for bidding
     */
    private function validateAuctionForBidding(Auction $auction): array
    {
        $now = Carbon::now();

        if ($auction->status !== 'active') {
            return [
                'valid' => false,
                'message' => 'Auction is not active',
                'error_code' => 'AUCTION_NOT_ACTIVE'
            ];
        }

        if ($now->lt($auction->starts_at)) {
            return [
                'valid' => false,
                'message' => 'Auction has not started yet',
                'error_code' => 'AUCTION_NOT_STARTED'
            ];
        }

        if ($now->gt($auction->ends_at)) {
            return [
                'valid' => false,
                'message' => 'Auction has ended',
                'error_code' => 'AUCTION_ENDED'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate user is authorized to bid
     */
    private function validateUserForBidding(int $userId, Auction $auction): array
    {
        // User cannot bid on their own auction
        if ($auction->created_by == $userId) {
            return [
                'authorized' => false,
                'message' => 'Cannot bid on your own auction'
            ];
        }

        // Additional user validation via auth service
        try {
            $userValidation = $this->authService->validateUser($userId);
            if (!$userValidation['success'] || !$userValidation['data']['is_active']) {
                return [
                    'authorized' => false,
                    'message' => 'User account is not active'
                ];
            }
        } catch (\Exception $e) {
            Log::warning('User validation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }

        return ['authorized' => true];
    }

    /**
     * Validate bid amount meets requirements
     */
    private function validateBidAmount(float $amount, Auction $auction): array
    {
        if ($amount <= 0) {
            return [
                'valid' => false,
                'message' => 'Bid amount must be greater than zero'
            ];
        }

        if ($amount < $auction->starting_price) {
            return [
                'valid' => false,
                'message' => 'Bid amount must be at least the starting price'
            ];
        }

        if ($auction->current_highest_bid && $amount <= $auction->current_highest_bid) {
            $minimumBid = $auction->current_highest_bid + $this->calculateBidIncrement($auction->current_highest_bid);
            return [
                'valid' => false,
                'message' => "Bid amount must be at least {$minimumBid}"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Calculate minimum bid increment based on current amount
     */
    private function calculateBidIncrement(float $currentAmount): float
    {
        if ($currentAmount < 1000) {
            return 50;
        } elseif ($currentAmount < 10000) {
            return 100;
        } elseif ($currentAmount < 100000) {
            return 500;
        } else {
            return 1000;
        }
    }

    /**
     * Handle bid acceptance logic
     */
    private function handleBidAcceptance(Bid $bid): void
    {
        // Update auction with winning bid
        $bid->auction->update([
            'winner_bid_id' => $bid->id,
            'winning_amount' => $bid->amount,
            'status' => 'completed',
            'completed_at' => now()
        ]);

        // Mark other bids as rejected
        Bid::where('auction_id', $bid->auction_id)
            ->where('id', '!=', $bid->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);
    }

    /**
     * Handle bid rejection logic
     */
    private function handleBidRejection(Bid $bid): void
    {
        // Send rejection notification
        $this->sendBidRejectionNotification($bid);
    }

    /**
     * Send bid-related notifications
     */
    private function sendBidNotifications(Bid $bid, Auction $auction): void
    {
        try {
            // Notify auction owner of new bid
            $this->notificationService->send([
                'user_id' => $auction->created_by,
                'type' => 'in_app',
                'title' => 'New Bid Received',
                'message' => "A new bid of {$bid->amount} {$bid->currency} was placed on your auction: {$auction->title}",
                'data' => [
                    'auction_id' => $auction->id,
                    'bid_id' => $bid->id,
                    'bid_amount' => $bid->amount
                ]
            ]);

            // Notify previous highest bidder they've been outbid
            $previousHighestBid = Bid::where('auction_id', $auction->id)
                ->where('status', 'outbid')
                ->where('user_id', '!=', $bid->user_id)
                ->orderBy('amount', 'desc')
                ->first();

            if ($previousHighestBid) {
                $this->notificationService->send([
                    'user_id' => $previousHighestBid->user_id,
                    'type' => 'in_app',
                    'title' => 'You\'ve Been Outbid',
                    'message' => "Your bid on '{$auction->title}' has been outbid. Current highest bid: {$bid->amount} {$bid->currency}",
                    'data' => [
                        'auction_id' => $auction->id,
                        'your_bid_id' => $previousHighestBid->id,
                        'new_highest_bid' => $bid->amount
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to send bid notifications', [
                'error' => $e->getMessage(),
                'bid_id' => $bid->id
            ]);
        }
    }

    /**
     * Send bid rejection notification
     */
    private function sendBidRejectionNotification(Bid $bid): void
    {
        try {
            $this->notificationService->send([
                'user_id' => $bid->user_id,
                'type' => 'in_app',
                'title' => 'Bid Rejected',
                'message' => "Your bid of {$bid->amount} {$bid->currency} on '{$bid->auction->title}' has been rejected.",
                'data' => [
                    'auction_id' => $bid->auction_id,
                    'bid_id' => $bid->id,
                    'bid_amount' => $bid->amount
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send bid rejection notification', [
                'error' => $e->getMessage(),
                'bid_id' => $bid->id
            ]);
        }
    }
}
