<?php

namespace App\Procedures;

use App\Models\Bid;
use Exception;
use Shared\Procedures\CrossServiceProcedure;
use Shared\Procedures\Micro\SecurityProcedure;
use Shared\Procedures\Micro\ValidationProcedure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bidding Procedure
 *
 * Handles all bidding-related operations with business rule validation
 * and integration with the auction service.
 */
class BiddingProcedure extends CrossServiceProcedure
{
    use SecurityProcedure;
    use ValidationProcedure;

    /**
     * Place a bid on an auction
     */
    public function placeBid(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
                'user_id' => ['required' => true, 'type' => 'integer'],
                'amount' => ['required' => true, 'type' => 'numeric'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            // Get auction details from auction service
            $auctionResult = $this->callService('auction-service', 'getAuction', [
                'auction_id' => $params['auction_id']
            ], $context);

            if (!$auctionResult['success']) {
                return $this->errorResponse('Auction not found', $auctionResult['data']);
            }

            $auction = $auctionResult['data']['auction'];

            // Validate auction is active
            if ($auction['status'] !== 'active') {
                return $this->errorResponse('Auction is not active');
            }

            // Validate auction timing
            if (now() < $auction['starts_at'] || now() > $auction['ends_at']) {
                return $this->errorResponse('Auction is not currently accepting bids');
            }

            // Validate bid amount
            $bidValidation = $this->validateBidAmount($params['amount'], $auction);
            if (!$bidValidation['valid']) {
                return $this->errorResponse('Invalid bid amount', $bidValidation['reason']);
            }

            // Validate bidder eligibility
            $eligibilityResult = $this->validateBidderEligibility($params['user_id'], $auction, $context);
            if (!$eligibilityResult['success']) {
                return $this->errorResponse('Bidder not eligible', $eligibilityResult['data']);
            }

            // Use database transaction for bid placement
            return DB::transaction(function () use ($params, $auction, $context) {
                // Check for concurrent bids (race condition protection)
                $currentHighestBid = $this->getCurrentHighestBid($params['auction_id']);
                
                if ($currentHighestBid && $params['amount'] <= $currentHighestBid['amount']) {
                    return $this->errorResponse('Bid amount must be higher than current highest bid');
                }

                // Create the bid
                $bid = Bid::create([
                    'auction_id' => $params['auction_id'],
                    'user_id' => $params['user_id'],
                    'amount' => $params['amount'],
                    'status' => 'active',
                    'submitted_at' => now(),
                ]);

                // Update auction's highest bid via auction service
                $updateResult = $this->callService('auction-service', 'updateHighestBid', [
                    'auction_id' => $params['auction_id'],
                    'bid_amount' => $params['amount'],
                ], $context);

                if (!$updateResult['success']) {
                    throw new Exception('Failed to update auction highest bid');
                }

                // Check for anti-sniping (extend auction if bid placed in last minutes)
                $this->handleAntiSniping($auction, $context);

                // Publish bid placed event
                $this->publishEvent([
                    'event_type' => 'bid.placed',
                    'bid_id' => $bid->id,
                    'auction_id' => $params['auction_id'],
                    'user_id' => $params['user_id'],
                    'amount' => $params['amount'],
                    'timestamp' => now()->toISOString(),
                ], $context);

                return $this->successResponse([
                    'bid' => $bid->toArray(),
                    'auction' => $updateResult['data']['auction'],
                    'message' => 'Bid placed successfully'
                ]);
            });

        } catch (Exception $e) {
            Log::error('Failed to place bid', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to place bid', $e->getMessage());
        }
    }

    /**
     * Get highest bid for an auction
     */
    public function getHighestBid(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $highestBid = Bid::where('auction_id', $params['auction_id'])
                ->where('status', 'active')
                ->orderBy('amount', 'desc')
                ->first();

            if (!$highestBid) {
                return $this->errorResponse('No bids found for this auction');
            }

            return $this->successResponse([
                'bid' => $highestBid->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get highest bid', $e->getMessage());
        }
    }

    /**
     * Get bids for an auction
     */
    public function getAuctionBids(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $bids = Bid::where('auction_id', $params['auction_id'])
                ->where('status', 'active')
                ->orderBy('amount', 'desc')
                ->paginate($params['per_page'] ?? 20);

            return $this->successResponse([
                'bids' => $bids->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get auction bids', $e->getMessage());
        }
    }

    /**
     * Get user's bids
     */
    public function getUserBids(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $bids = Bid::where('user_id', $params['user_id'])
                ->where('status', 'active')
                ->orderBy('submitted_at', 'desc')
                ->paginate($params['per_page'] ?? 20);

            return $this->successResponse([
                'bids' => $bids->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get user bids', $e->getMessage());
        }
    }

    /**
     * Validate bid amount against business rules
     */
    private function validateBidAmount(float $bidAmount, array $auction): array
    {
        // Must be higher than current highest bid
        if ($bidAmount <= $auction['current_highest_bid']) {
            return [
                'valid' => false,
                'reason' => 'Bid must be higher than current highest bid of ' . $auction['current_highest_bid']
            ];
        }

        // Check minimum increment (e.g., $10 minimum increment)
        $minimumIncrement = 10.00;
        $requiredMinimum = $auction['current_highest_bid'] + $minimumIncrement;
        
        if ($bidAmount < $requiredMinimum) {
            return [
                'valid' => false,
                'reason' => "Bid must be at least {$requiredMinimum} (minimum increment: {$minimumIncrement})"
            ];
        }

        // Check maximum bid limit (optional business rule)
        $maximumBid = 1000000.00; // $1M max
        if ($bidAmount > $maximumBid) {
            return [
                'valid' => false,
                'reason' => "Bid cannot exceed maximum limit of {$maximumBid}"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate bidder eligibility
     */
    private function validateBidderEligibility(int $userId, array $auction, array $context): array
    {
        try {
            // Check if user is the auction creator (can't bid on own auction)
            if ($userId == $auction['created_by']) {
                return $this->errorResponse('Cannot bid on your own auction');
            }

            // Check user KYC status via user service
            $userResult = $this->callService('user-service', 'getUserProfile', [
                'user_id' => $userId
            ], $context);

            if (!$userResult['success']) {
                return $this->errorResponse('Failed to verify user eligibility');
            }

            $user = $userResult['data']['user'];

            // Check KYC verification
            if (!$user['kyc_verified']) {
                return $this->errorResponse('KYC verification required to place bids');
            }

            // Check account status
            if ($user['status'] !== 'active') {
                return $this->errorResponse('Account must be active to place bids');
            }

            return $this->successResponse(['eligible' => true]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to validate bidder eligibility', $e->getMessage());
        }
    }

    /**
     * Get current highest bid with locking
     */
    private function getCurrentHighestBid(int $auctionId): ?array
    {
        $bid = Bid::where('auction_id', $auctionId)
            ->where('status', 'active')
            ->orderBy('amount', 'desc')
            ->lockForUpdate()
            ->first();

        return $bid ? $bid->toArray() : null;
    }

    /**
     * Handle anti-sniping logic
     */
    private function handleAntiSniping(array $auction, array $context): void
    {
        $endTime = new \DateTime($auction['ends_at']);
        $now = new \DateTime();
        $timeRemaining = $endTime->getTimestamp() - $now->getTimestamp();

        // If bid placed in last 5 minutes, extend auction by 5 minutes
        if ($timeRemaining <= 300) { // 5 minutes
            $newEndTime = $now->add(new \DateInterval('PT5M'));
            
            // Update auction end time via auction service
            $this->callService('auction-service', 'extendAuction', [
                'auction_id' => $auction['id'],
                'new_end_time' => $newEndTime->format('Y-m-d H:i:s'),
            ], $context);

            Log::info("Auction {$auction['id']} extended due to anti-sniping", [
                'original_end' => $auction['ends_at'],
                'new_end' => $newEndTime->format('Y-m-d H:i:s')
            ]);
        }
    }
}
