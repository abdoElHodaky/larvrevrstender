<?php

namespace Shared\Procedures\Micro;

use Shared\Core\ProcedureEngine;
use Shared\Services\RestHandler;
use Shared\Services\RpcHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

/**
 * Auction Lifecycle Procedure
 * 
 * Orchestrates the complete auction lifecycle across services using
 * micro-procedures for atomic operations and macro-procedures for
 * complex workflows including creation, management, ending, and settlement.
 */
class AuctionLifecycleProcedure
{
    private ProcedureEngine $engine;
    private RestHandler $restHandler;
    private RpcHandler $rpcHandler;

    public function __construct(
        ProcedureEngine $engine,
        RestHandler $restHandler,
        RpcHandler $rpcHandler
    ) {
        $this->engine = $engine;
        $this->restHandler = $restHandler;
        $this->rpcHandler = $rpcHandler;
    }

    /**
     * Micro-Procedure: Create Auction
     * Atomically create auction with validation
     */
    public function createAuction(array $auctionData, array $context): array
    {
        try {
            // Validate user permissions
            $userValidation = $this->rpcHandler->call('auth-service', 'validateUserToken', [
                'token' => $context['auth_token'],
                'required_permissions' => ['create_auction']
            ]);

            if (!$userValidation['success']) {
                return [
                    'success' => false,
                    'error' => 'AUTH_INVALID',
                    'message' => 'User not authorized to create auctions'
                ];
            }

            // Validate auction data
            $validationResult = $this->validateAuctionData($auctionData);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Create auction record
            $auctionCreationResult = $this->rpcHandler->call('auction-service', 'createAuctionRecord', [
                'seller_id' => $userValidation['data']['id'],
                'title' => $auctionData['title'],
                'description' => $auctionData['description'],
                'starting_price' => $auctionData['starting_price'],
                'reserve_price' => $auctionData['reserve_price'] ?? null,
                'minimum_bid_increment' => $auctionData['minimum_bid_increment'] ?? 1.00,
                'duration_hours' => $auctionData['duration_hours'],
                'category' => $auctionData['category'],
                'images' => $auctionData['images'] ?? [],
                'metadata' => $auctionData['metadata'] ?? []
            ]);

            if (!$auctionCreationResult['success']) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_CREATION_FAILED',
                    'message' => 'Failed to create auction record'
                ];
            }

            // Schedule auction start (if not immediate)
            if (isset($auctionData['start_time']) && $auctionData['start_time'] > now()) {
                $this->scheduleAuctionStart($auctionCreationResult['data']['id'], $auctionData['start_time']);
            }

            // Schedule auction end
            $endTime = isset($auctionData['start_time']) 
                ? Carbon::parse($auctionData['start_time'])->addHours($auctionData['duration_hours'])
                : now()->addHours($auctionData['duration_hours']);
            
            $this->scheduleAuctionEnd($auctionCreationResult['data']['id'], $endTime);

            return [
                'success' => true,
                'data' => $auctionCreationResult['data']
            ];

        } catch (Exception $e) {
            Log::error('Auction creation failed', [
                'auction_data' => $auctionData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'CREATION_ERROR',
                'message' => 'Auction creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Micro-Procedure: End Auction
     * Atomically end auction and determine winner
     */
    public function endAuction(string $auctionId): array
    {
        try {
            // Get auction details
            $auctionResult = $this->rpcHandler->call('auction-service', 'getAuctionDetails', [
                'auction_id' => $auctionId
            ]);

            if (!$auctionResult['success']) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_FOUND',
                    'message' => 'Auction not found'
                ];
            }

            $auction = $auctionResult['data'];

            // Check if auction can be ended
            if ($auction['status'] !== 'active') {
                return [
                    'success' => false,
                    'error' => 'AUCTION_NOT_ACTIVE',
                    'message' => 'Auction is not active'
                ];
            }

            // Get all bids for the auction
            $bidsResult = $this->rpcHandler->call('bidding-service', 'getAuctionBids', [
                'auction_id' => $auctionId,
                'order_by' => 'amount',
                'order_direction' => 'desc'
            ]);

            if (!$bidsResult['success']) {
                Log::warning('Failed to get bids for auction ending', [
                    'auction_id' => $auctionId
                ]);
                $bids = [];
            } else {
                $bids = $bidsResult['data']['bids'] ?? [];
            }

            // Determine winner
            $winnerResult = $this->determineAuctionWinner($auction, $bids);

            // Update auction status
            $updateResult = $this->rpcHandler->call('auction-service', 'updateAuctionStatus', [
                'auction_id' => $auctionId,
                'status' => 'ended',
                'ended_at' => Carbon::now()->toISOString(),
                'winner_bid_id' => $winnerResult['winner_bid_id'] ?? null,
                'final_price' => $winnerResult['final_price'] ?? null
            ]);

            if (!$updateResult['success']) {
                return [
                    'success' => false,
                    'error' => 'UPDATE_FAILED',
                    'message' => 'Failed to update auction status'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'auction_id' => $auctionId,
                    'status' => 'ended',
                    'winner' => $winnerResult,
                    'total_bids' => count($bids),
                    'ended_at' => Carbon::now()->toISOString()
                ]
            ];

        } catch (Exception $e) {
            Log::error('Auction ending failed', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'ENDING_ERROR',
                'message' => 'Auction ending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Micro-Procedure: Process Auction Settlement
     * Atomically process payment and order creation for won auction
     */
    public function processAuctionSettlement(string $auctionId, array $winnerData): array
    {
        try {
            // Create order for the winning bid
            $orderResult = $this->rpcHandler->call('order-service', 'createAuctionOrder', [
                'auction_id' => $auctionId,
                'buyer_id' => $winnerData['user_id'],
                'seller_id' => $winnerData['seller_id'],
                'amount' => $winnerData['final_price'],
                'bid_id' => $winnerData['winner_bid_id']
            ]);

            if (!$orderResult['success']) {
                return [
                    'success' => false,
                    'error' => 'ORDER_CREATION_FAILED',
                    'message' => 'Failed to create order for auction'
                ];
            }

            // Process payment
            $paymentResult = $this->rpcHandler->call('payment-service', 'processAuctionPayment', [
                'order_id' => $orderResult['data']['order_id'],
                'buyer_id' => $winnerData['user_id'],
                'seller_id' => $winnerData['seller_id'],
                'amount' => $winnerData['final_price'],
                'payment_method' => 'wallet', // Use reserved funds from bid
                'bid_reservation_id' => $winnerData['reservation_id'] ?? null
            ]);

            if (!$paymentResult['success']) {
                // Cancel the order if payment fails
                $this->rpcHandler->call('order-service', 'cancelOrder', [
                    'order_id' => $orderResult['data']['order_id'],
                    'reason' => 'payment_failed'
                ]);

                return [
                    'success' => false,
                    'error' => 'PAYMENT_FAILED',
                    'message' => 'Payment processing failed for auction'
                ];
            }

            // Release funds for non-winning bidders
            $this->releaseNonWinningBidFunds($auctionId, $winnerData['winner_bid_id']);

            // Update auction settlement status
            $settlementResult = $this->rpcHandler->call('auction-service', 'updateAuctionSettlement', [
                'auction_id' => $auctionId,
                'order_id' => $orderResult['data']['order_id'],
                'payment_id' => $paymentResult['data']['payment_id'],
                'settlement_status' => 'completed',
                'settled_at' => Carbon::now()->toISOString()
            ]);

            return [
                'success' => true,
                'data' => [
                    'auction_id' => $auctionId,
                    'order_id' => $orderResult['data']['order_id'],
                    'payment_id' => $paymentResult['data']['payment_id'],
                    'settlement_status' => 'completed',
                    'settled_at' => Carbon::now()->toISOString()
                ]
            ];

        } catch (Exception $e) {
            Log::error('Auction settlement failed', [
                'auction_id' => $auctionId,
                'winner_data' => $winnerData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'SETTLEMENT_ERROR',
                'message' => 'Auction settlement failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Micro-Procedure: Send Auction Notifications
     * Atomically send notifications for auction events
     */
    public function sendAuctionNotifications(string $eventType, array $auctionData, array $additionalData = []): array
    {
        try {
            $notificationData = [
                'event_type' => $eventType,
                'auction_id' => $auctionData['id'],
                'auction_title' => $auctionData['title'],
                'additional_data' => $additionalData
            ];

            switch ($eventType) {
                case 'auction_started':
                    $this->sendAuctionStartedNotifications($notificationData);
                    break;
                
                case 'auction_ending_soon':
                    $this->sendAuctionEndingSoonNotifications($notificationData);
                    break;
                
                case 'auction_ended':
                    $this->sendAuctionEndedNotifications($notificationData, $additionalData);
                    break;
                
                case 'auction_won':
                    $this->sendAuctionWonNotifications($notificationData, $additionalData);
                    break;
                
                case 'auction_settlement_completed':
                    $this->sendSettlementCompletedNotifications($notificationData, $additionalData);
                    break;
            }

            return [
                'success' => true,
                'data' => [
                    'event_type' => $eventType,
                    'notifications_sent' => true
                ]
            ];

        } catch (Exception $e) {
            Log::error('Auction notification sending failed', [
                'event_type' => $eventType,
                'auction_data' => $auctionData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'NOTIFICATION_ERROR',
                'message' => 'Failed to send auction notifications: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Macro-Procedure: Complete Auction Lifecycle
     * Orchestrates the complete auction ending and settlement workflow
     */
    public function completeAuctionLifecycle(string $auctionId): array
    {
        $workflowId = uniqid('auction_lifecycle_');
        
        try {
            Log::info('Starting auction lifecycle completion', [
                'workflow_id' => $workflowId,
                'auction_id' => $auctionId
            ]);

            // Step 1: End the auction
            $endResult = $this->endAuction($auctionId);
            if (!$endResult['success']) {
                return $endResult;
            }

            $auctionData = $endResult['data'];

            // Step 2: Send auction ended notifications
            $this->sendAuctionNotifications('auction_ended', [
                'id' => $auctionId,
                'title' => $auctionData['title'] ?? 'Auction'
            ], $auctionData);

            // Step 3: Process settlement if there's a winner
            if (isset($auctionData['winner']) && $auctionData['winner']['has_winner']) {
                $settlementResult = $this->processAuctionSettlement($auctionId, $auctionData['winner']);
                
                if ($settlementResult['success']) {
                    // Step 4: Send settlement notifications
                    $this->sendAuctionNotifications('auction_settlement_completed', [
                        'id' => $auctionId,
                        'title' => $auctionData['title'] ?? 'Auction'
                    ], $settlementResult['data']);

                    $auctionData['settlement'] = $settlementResult['data'];
                } else {
                    Log::warning('Auction settlement failed', [
                        'workflow_id' => $workflowId,
                        'auction_id' => $auctionId,
                        'settlement_error' => $settlementResult['message']
                    ]);
                    
                    $auctionData['settlement_error'] = $settlementResult['message'];
                }
            } else {
                // No winner - release all reserved funds
                $this->releaseAllAuctionBidFunds($auctionId);
                $auctionData['settlement'] = ['status' => 'no_winner'];
            }

            // Step 5: Update analytics
            $this->rpcHandler->call('analytics-service', 'recordAuctionCompletion', [
                'auction_id' => $auctionId,
                'completion_data' => $auctionData
            ]);

            Log::info('Auction lifecycle completion finished', [
                'workflow_id' => $workflowId,
                'auction_id' => $auctionId,
                'has_winner' => $auctionData['winner']['has_winner'] ?? false,
                'settlement_success' => isset($auctionData['settlement']) && !isset($auctionData['settlement_error'])
            ]);

            return [
                'success' => true,
                'data' => [
                    'workflow_id' => $workflowId,
                    'auction_id' => $auctionId,
                    'completion_data' => $auctionData
                ]
            ];

        } catch (Exception $e) {
            Log::error('Auction lifecycle completion failed', [
                'workflow_id' => $workflowId,
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'LIFECYCLE_ERROR',
                'message' => 'Auction lifecycle completion failed: ' . $e->getMessage(),
                'workflow_id' => $workflowId
            ];
        }
    }

    /**
     * Helper: Validate auction data
     */
    private function validateAuctionData(array $auctionData): array
    {
        $required = ['title', 'description', 'starting_price', 'duration_hours', 'category'];
        
        foreach ($required as $field) {
            if (!isset($auctionData[$field]) || empty($auctionData[$field])) {
                return [
                    'success' => false,
                    'error' => 'VALIDATION_ERROR',
                    'message' => "Required field '{$field}' is missing or empty"
                ];
            }
        }

        if ($auctionData['starting_price'] <= 0) {
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Starting price must be greater than 0'
            ];
        }

        if ($auctionData['duration_hours'] < 1 || $auctionData['duration_hours'] > 168) { // Max 7 days
            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Duration must be between 1 and 168 hours'
            ];
        }

        return ['success' => true];
    }

    /**
     * Helper: Determine auction winner
     */
    private function determineAuctionWinner(array $auction, array $bids): array
    {
        if (empty($bids)) {
            return [
                'has_winner' => false,
                'reason' => 'no_bids'
            ];
        }

        $highestBid = $bids[0]; // Bids are ordered by amount desc
        $reservePrice = $auction['reserve_price'] ?? 0;

        if ($reservePrice > 0 && $highestBid['amount'] < $reservePrice) {
            return [
                'has_winner' => false,
                'reason' => 'reserve_not_met',
                'highest_bid' => $highestBid['amount'],
                'reserve_price' => $reservePrice
            ];
        }

        return [
            'has_winner' => true,
            'winner_bid_id' => $highestBid['id'],
            'user_id' => $highestBid['user_id'],
            'seller_id' => $auction['seller_id'],
            'final_price' => $highestBid['amount'],
            'reservation_id' => $highestBid['reservation_id'] ?? null
        ];
    }

    /**
     * Helper: Release funds for non-winning bidders
     */
    private function releaseNonWinningBidFunds(string $auctionId, string $winnerBidId): void
    {
        try {
            $this->rpcHandler->call('user-service', 'releaseNonWinningBidFunds', [
                'auction_id' => $auctionId,
                'winner_bid_id' => $winnerBidId
            ]);
        } catch (Exception $e) {
            Log::error('Failed to release non-winning bid funds', [
                'auction_id' => $auctionId,
                'winner_bid_id' => $winnerBidId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Release all auction bid funds (no winner)
     */
    private function releaseAllAuctionBidFunds(string $auctionId): void
    {
        try {
            $this->rpcHandler->call('user-service', 'releaseAllAuctionBidFunds', [
                'auction_id' => $auctionId
            ]);
        } catch (Exception $e) {
            Log::error('Failed to release all auction bid funds', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Schedule auction start
     */
    private function scheduleAuctionStart(string $auctionId, string $startTime): void
    {
        // Implementation would depend on job queue system
        // For now, just log the scheduling
        Log::info('Auction start scheduled', [
            'auction_id' => $auctionId,
            'start_time' => $startTime
        ]);
    }

    /**
     * Helper: Schedule auction end
     */
    private function scheduleAuctionEnd(string $auctionId, Carbon $endTime): void
    {
        // Implementation would depend on job queue system
        // For now, just log the scheduling
        Log::info('Auction end scheduled', [
            'auction_id' => $auctionId,
            'end_time' => $endTime->toISOString()
        ]);
    }

    /**
     * Helper notification methods
     */
    private function sendAuctionStartedNotifications(array $data): void
    {
        $this->rpcHandler->call('notification-service', 'sendAuctionStartedNotifications', $data);
    }

    private function sendAuctionEndingSoonNotifications(array $data): void
    {
        $this->rpcHandler->call('notification-service', 'sendAuctionEndingSoonNotifications', $data);
    }

    private function sendAuctionEndedNotifications(array $data, array $additionalData): void
    {
        $this->rpcHandler->call('notification-service', 'sendAuctionEndedNotifications', 
            array_merge($data, $additionalData));
    }

    private function sendAuctionWonNotifications(array $data, array $additionalData): void
    {
        $this->rpcHandler->call('notification-service', 'sendAuctionWonNotifications', 
            array_merge($data, $additionalData));
    }

    private function sendSettlementCompletedNotifications(array $data, array $additionalData): void
    {
        $this->rpcHandler->call('notification-service', 'sendSettlementCompletedNotifications', 
            array_merge($data, $additionalData));
    }
}
