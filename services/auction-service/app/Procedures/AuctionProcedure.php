<?php

namespace App\Procedures;

use App\Models\Auction;
use Exception;
use Shared\Procedures\CrossServiceProcedure;
use Shared\Procedures\Micro\SecurityProcedure;
use Shared\Procedures\Micro\ValidationProcedure;

/**
 * Auction Procedure
 *
 * Handles all auction-related operations including lifecycle management,
 * completion logic, and cross-service integration.
 */
class AuctionProcedure extends CrossServiceProcedure
{
    use SecurityProcedure;
    use ValidationProcedure;

    /**
     * Create a new auction
     */
    public function createAuction(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'title' => ['required' => true, 'type' => 'string'],
                'description' => ['required' => true, 'type' => 'string'],
                'vehicle_id' => ['required' => true, 'type' => 'integer'],
                'starting_price' => ['required' => true, 'type' => 'numeric'],
                'reserve_price' => ['type' => 'numeric'],
                'starts_at' => ['required' => true, 'type' => 'string'],
                'ends_at' => ['required' => true, 'type' => 'string'],
                'created_by' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $auction = Auction::create([
                'title' => $params['title'],
                'description' => $params['description'],
                'vehicle_id' => $params['vehicle_id'],
                'starting_price' => $params['starting_price'],
                'reserve_price' => $params['reserve_price'] ?? null,
                'current_highest_bid' => $params['starting_price'],
                'status' => 'draft',
                'starts_at' => $params['starts_at'],
                'ends_at' => $params['ends_at'],
                'created_by' => $params['created_by'],
            ]);

            // Publish auction created event
            $this->publishEvent([
                'event_type' => 'auction.created',
                'auction_id' => $auction->id,
                'vehicle_id' => $auction->vehicle_id,
                'starting_price' => $auction->starting_price,
                'starts_at' => $auction->starts_at,
                'ends_at' => $auction->ends_at,
            ], $context);

            return $this->successResponse([
                'auction' => $auction->toArray(),
                'message' => 'Auction created successfully'
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to create auction', $e->getMessage());
        }
    }

    /**
     * Start an auction
     */
    public function startAuction(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $auction = Auction::find($params['auction_id']);
            if (!$auction) {
                return $this->errorResponse('Auction not found');
            }

            if ($auction->status !== 'draft') {
                return $this->errorResponse('Auction cannot be started', 'Invalid status');
            }

            $auction->update(['status' => 'active']);

            // Publish auction started event
            $this->publishEvent([
                'event_type' => 'auction.started',
                'auction_id' => $auction->id,
                'vehicle_id' => $auction->vehicle_id,
                'starting_price' => $auction->starting_price,
                'ends_at' => $auction->ends_at,
            ], $context);

            return $this->successResponse([
                'auction' => $auction->fresh()->toArray(),
                'message' => 'Auction started successfully'
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to start auction', $e->getMessage());
        }
    }

    /**
     * Complete an auction (determine winner and create order)
     */
    public function completeAuction(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $auction = Auction::find($params['auction_id']);
            if (!$auction) {
                return $this->errorResponse('Auction not found');
            }

            if ($auction->status !== 'active') {
                return $this->errorResponse('Auction cannot be completed', 'Invalid status');
            }

            // Get highest bid from bidding service
            $highestBidResult = $this->callService('bidding-service', 'getHighestBid', [
                'auction_id' => $auction->id
            ], $context);

            if (!$highestBidResult['success']) {
                // No bids - auction ends without winner
                $auction->update(['status' => 'ended_no_winner']);
                
                $this->publishEvent([
                    'event_type' => 'auction.ended_no_winner',
                    'auction_id' => $auction->id,
                ], $context);

                return $this->successResponse([
                    'auction' => $auction->fresh()->toArray(),
                    'message' => 'Auction ended without winner'
                ]);
            }

            $highestBid = $highestBidResult['data'];

            // Check if reserve price is met
            if ($auction->reserve_price && $highestBid['amount'] < $auction->reserve_price) {
                $auction->update(['status' => 'ended_reserve_not_met']);
                
                $this->publishEvent([
                    'event_type' => 'auction.ended_reserve_not_met',
                    'auction_id' => $auction->id,
                    'highest_bid' => $highestBid['amount'],
                    'reserve_price' => $auction->reserve_price,
                ], $context);

                return $this->successResponse([
                    'auction' => $auction->fresh()->toArray(),
                    'message' => 'Auction ended - reserve price not met'
                ]);
            }

            // Auction has winner - update status and create order
            $auction->update([
                'status' => 'completed',
                'current_highest_bid' => $highestBid['amount']
            ]);

            // Create order via order service using existing saga workflow
            $orderResult = $this->callService('order-service', 'createOrderFromAuction', [
                'auction_id' => $auction->id,
                'winning_bid_id' => $highestBid['id'],
                'buyer_id' => $highestBid['user_id'],
                'seller_id' => $auction->created_by,
                'amount' => $highestBid['amount'],
                'vehicle_id' => $auction->vehicle_id,
            ], $context);

            if (!$orderResult['success']) {
                return $this->errorResponse('Failed to create order from auction', $orderResult['data']);
            }

            // Publish auction completed event
            $this->publishEvent([
                'event_type' => 'auction.completed',
                'auction_id' => $auction->id,
                'winner_id' => $highestBid['user_id'],
                'winning_bid_id' => $highestBid['id'],
                'winning_amount' => $highestBid['amount'],
                'order_id' => $orderResult['data']['order']['id'],
            ], $context);

            return $this->successResponse([
                'auction' => $auction->fresh()->toArray(),
                'winning_bid' => $highestBid,
                'order' => $orderResult['data']['order'],
                'message' => 'Auction completed successfully'
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to complete auction', $e->getMessage());
        }
    }

    /**
     * Get auction details
     */
    public function getAuction(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $auction = Auction::with(['productImages'])->find($params['auction_id']);
            if (!$auction) {
                return $this->errorResponse('Auction not found');
            }

            return $this->successResponse([
                'auction' => $auction->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get auction', $e->getMessage());
        }
    }

    /**
     * Get active auctions
     */
    public function getActiveAuctions(array $params, array $context = []): array
    {
        try {
            $auctions = Auction::where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->with(['productImages'])
                ->orderBy('ends_at', 'asc')
                ->paginate($params['per_page'] ?? 15);

            return $this->successResponse([
                'auctions' => $auctions->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get active auctions', $e->getMessage());
        }
    }

    /**
     * Get expired auctions that need completion
     */
    public function getExpiredAuctions(array $params, array $context = []): array
    {
        try {
            $auctions = Auction::where('status', 'active')
                ->where('ends_at', '<=', now())
                ->get();

            return $this->successResponse([
                'auctions' => $auctions->toArray()
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to get expired auctions', $e->getMessage());
        }
    }

    /**
     * Update auction current highest bid (called by bidding service)
     */
    public function updateHighestBid(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'auction_id' => ['required' => true, 'type' => 'integer'],
                'bid_amount' => ['required' => true, 'type' => 'numeric'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $auction = Auction::find($params['auction_id']);
            if (!$auction) {
                return $this->errorResponse('Auction not found');
            }

            $auction->update(['current_highest_bid' => $params['bid_amount']]);

            // Publish bid update event
            $this->publishEvent([
                'event_type' => 'auction.bid_updated',
                'auction_id' => $auction->id,
                'new_highest_bid' => $params['bid_amount'],
            ], $context);

            return $this->successResponse([
                'auction' => $auction->fresh()->toArray(),
                'message' => 'Auction highest bid updated'
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Failed to update highest bid', $e->getMessage());
        }
    }
}
