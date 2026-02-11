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
 * Bidding Lifecycle Procedure
 * 
 * Orchestrates the complete bidding lifecycle across services using
 * micro-procedures for atomic operations and macro-procedures for
 * complex workflows.
 */
class BiddingLifecycleProcedure
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
     * Micro-Procedure: Validate Bid Placement
     * Atomic validation of bid requirements
     */
    public function validateBidPlacement(array $bidData, array $context): array
    {
        try {
            $validations = [];

            // 1. Validate auction exists and is active
            $auctionValidation = $this->rpcHandler->call('auction-service', 'validateAuctionActive', [
                'auction_id' => $bidData['auction_id']
            ]);

            if (!$auctionValidation['success']) {
                return [
                    'success' => false,
                    'error' => 'AUCTION_INVALID',
                    'message' => $auctionValidation['message'] ?? 'Auction is not active'
                ];
            }

            $validations['auction'] = $auctionValidation['data'];

            // 2. Validate user authentication and permissions
            $userValidation = $this->rpcHandler->call('auth-service', 'validateUserToken', [
                'token' => $context['auth_token'],
                'required_permissions' => ['place_bid']
            ]);

            if (!$userValidation['success']) {
                return [
                    'success' => false,
                    'error' => 'AUTH_INVALID',
                    'message' => 'User not authorized to place bids'
                ];
            }

            $validations['user'] = $userValidation['data'];

            // 3. Validate bid amount against auction rules
            $bidAmountValidation = $this->validateBidAmount(
                $bidData['amount'],
                $validations['auction'],
                $bidData['auction_id']
            );

            if (!$bidAmountValidation['success']) {
                return $bidAmountValidation;
            }

            $validations['bid_amount'] = $bidAmountValidation['data'];

            // 4. Validate user wallet balance
            $walletValidation = $this->rpcHandler->call('user-service', 'validateWalletBalance', [
                'user_id' => $validations['user']['id'],
                'required_amount' => $bidData['amount']
            ]);

            if (!$walletValidation['success']) {
                return [
                    'success' => false,
                    'error' => 'INSUFFICIENT_FUNDS',
                    'message' => 'Insufficient wallet balance for bid'
                ];
            }

            $validations['wallet'] = $walletValidation['data'];

            return [
                'success' => true,
                'data' => $validations
            ];

        } catch (Exception $e) {
            Log::error('Bid validation failed', [
                'bid_data' => $bidData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'VALIDATION_ERROR',
                'message' => 'Bid validation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Micro-Procedure: Reserve Bid Funds
     * Atomically reserve funds for bid placement
     */
    public function reserveBidFunds(array $bidData, array $validationData): array
    {
        try {
            // Reserve funds in user wallet
            $reservationResult = $this->rpcHandler->call('user-service', 'reserveWalletFunds', [
                'user_id' => $validationData['user']['id'],
                'amount' => $bidData['amount'],
                'reference_type' => 'bid',
                'reference_id' => $bidData['auction_id'],
                'expiry_minutes' => 30 // Reserve for 30 minutes
            ]);

            if (!$reservationResult['success']) {
                return [
                    'success' => false,
                    'error' => 'FUND_RESERVATION_FAILED',
                    'message' => 'Failed to reserve funds for bid'
                ];
            }

            // Store reservation details in cache for quick access
            $reservationKey = "bid_reservation:{$validationData['user']['id']}:{$bidData['auction_id']}";
            Cache::put($reservationKey, $reservationResult['data'], now()->addMinutes(35));

            return [
                'success' => true,
                'data' => [
                    'reservation_id' => $reservationResult['data']['reservation_id'],
                    'reserved_amount' => $reservationResult['data']['amount'],
                    'expires_at' => $reservationResult['data']['expires_at']
                ]
            ];

        } catch (Exception $e) {
            Log::error('Fund reservation failed', [
                'bid_data' => $bidData,
                'validation_data' => $validationData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'RESERVATION_ERROR',
                'message' => 'Fund reservation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Micro-Procedure: Place Bid Record
     * Atomically create bid record in bidding service
     */
    public function placeBidRecord(array $bidData, array $validationData, array $reservationData): array
    {
        try {
            // Create bid record
            $bidCreationResult = $this->rpcHandler->call('bidding-service', 'createBidRecord', [
                'auction_id' => $bidData['auction_id'],
                'user_id' => $validationData['user']['id'],
                'amount' => $bidData['amount'],
                'reservation_id' => $reservationData['reservation_id'],
                'bid_type' => $bidData['bid_type'] ?? 'standard',
                'attachments' => $bidData['attachments'] ?? [],
                'metadata' => [
                    'ip_address' => $bidData['ip_address'] ?? null,
                    'user_agent' => $bidData['user_agent'] ?? null,
                    'timestamp' => Carbon::now()->toISOString()
                ]
            ]);

            if (!$bidCreationResult['success']) {
                // Release reserved funds if bid creation fails
                $this->rpcHandler->call('user-service', 'releaseWalletReservation', [
                    'reservation_id' => $reservationData['reservation_id']
                ]);

                return [
                    'success' => false,
                    'error' => 'BID_CREATION_FAILED',
                    'message' => 'Failed to create bid record'
                ];
            }

            return [
                'success' => true,
                'data' => $bidCreationResult['data']
            ];

        } catch (Exception $e) {
            Log::error('Bid record creation failed', [
                'bid_data' => $bidData,
                'error' => $e->getMessage()
            ]);

            // Attempt to release reserved funds
            try {
                $this->rpcHandler->call('user-service', 'releaseWalletReservation', [
                    'reservation_id' => $reservationData['reservation_id']
                ]);
            } catch (Exception $releaseError) {
                Log::error('Failed to release funds after bid creation failure', [
                    'reservation_id' => $reservationData['reservation_id'],
                    'error' => $releaseError->getMessage()
                ]);
            }

            return [
                'success' => false,
                'error' => 'BID_RECORD_ERROR',
                'message' => 'Bid record creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Micro-Procedure: Broadcast Bid Update
     * Atomically broadcast bid update to all connected clients
     */
    public function broadcastBidUpdate(array $bidData, array $auctionData): array
    {
        try {
            // Prepare broadcast data
            $broadcastData = [
                'event' => 'bid_placed',
                'auction_id' => $bidData['auction_id'],
                'bid' => [
                    'id' => $bidData['id'],
                    'amount' => $bidData['amount'],
                    'user_id' => $bidData['user_id'],
                    'timestamp' => $bidData['created_at']
                ],
                'auction' => [
                    'id' => $auctionData['id'],
                    'current_highest_bid' => $auctionData['current_highest_bid'],
                    'bid_count' => $auctionData['bid_count'],
                    'ends_at' => $auctionData['ends_at']
                ]
            ];

            // Broadcast via WebSocket
            $websocketResult = $this->rpcHandler->call('bidding-service', 'broadcastToAuction', [
                'auction_id' => $bidData['auction_id'],
                'data' => $broadcastData
            ]);

            // Send push notifications to interested users
            $notificationResult = $this->rpcHandler->call('notification-service', 'sendBidNotifications', [
                'auction_id' => $bidData['auction_id'],
                'bid_data' => $bidData,
                'notification_types' => ['push', 'in_app']
            ]);

            return [
                'success' => true,
                'data' => [
                    'websocket_sent' => $websocketResult['success'] ?? false,
                    'notifications_sent' => $notificationResult['success'] ?? false,
                    'broadcast_data' => $broadcastData
                ]
            ];

        } catch (Exception $e) {
            Log::error('Bid broadcast failed', [
                'bid_data' => $bidData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'BROADCAST_ERROR',
                'message' => 'Bid broadcast failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Macro-Procedure: Complete Bid Placement
     * Orchestrates the complete bid placement workflow
     */
    public function completeBidPlacement(array $bidData, array $context): array
    {
        $workflowId = uniqid('bid_placement_');
        
        try {
            Log::info('Starting bid placement workflow', [
                'workflow_id' => $workflowId,
                'auction_id' => $bidData['auction_id'],
                'user_context' => $context['user_id'] ?? 'unknown'
            ]);

            // Step 1: Validate bid placement
            $validationResult = $this->validateBidPlacement($bidData, $context);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Step 2: Reserve funds
            $reservationResult = $this->reserveBidFunds($bidData, $validationResult['data']);
            if (!$reservationResult['success']) {
                return $reservationResult;
            }

            // Step 3: Place bid record
            $bidRecordResult = $this->placeBidRecord(
                $bidData, 
                $validationResult['data'], 
                $reservationResult['data']
            );
            if (!$bidRecordResult['success']) {
                return $bidRecordResult;
            }

            // Step 4: Update auction state
            $auctionUpdateResult = $this->rpcHandler->call('auction-service', 'updateAuctionWithBid', [
                'auction_id' => $bidData['auction_id'],
                'bid_data' => $bidRecordResult['data']
            ]);

            if (!$auctionUpdateResult['success']) {
                Log::warning('Auction update failed after bid placement', [
                    'workflow_id' => $workflowId,
                    'bid_id' => $bidRecordResult['data']['id']
                ]);
            }

            // Step 5: Broadcast bid update
            $broadcastResult = $this->broadcastBidUpdate(
                $bidRecordResult['data'],
                $auctionUpdateResult['data'] ?? []
            );

            // Step 6: Convert reservation to actual transaction
            $transactionResult = $this->rpcHandler->call('user-service', 'convertReservationToTransaction', [
                'reservation_id' => $reservationResult['data']['reservation_id'],
                'transaction_type' => 'bid_placement',
                'reference_id' => $bidRecordResult['data']['id']
            ]);

            Log::info('Bid placement workflow completed', [
                'workflow_id' => $workflowId,
                'bid_id' => $bidRecordResult['data']['id'],
                'success' => true
            ]);

            return [
                'success' => true,
                'data' => [
                    'workflow_id' => $workflowId,
                    'bid' => $bidRecordResult['data'],
                    'auction' => $auctionUpdateResult['data'] ?? null,
                    'broadcast_sent' => $broadcastResult['success'],
                    'transaction_id' => $transactionResult['data']['transaction_id'] ?? null
                ]
            ];

        } catch (Exception $e) {
            Log::error('Bid placement workflow failed', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'WORKFLOW_ERROR',
                'message' => 'Bid placement workflow failed: ' . $e->getMessage(),
                'workflow_id' => $workflowId
            ];
        }
    }

    /**
     * Helper: Validate bid amount against auction rules
     */
    private function validateBidAmount(float $bidAmount, array $auctionData, string $auctionId): array
    {
        try {
            // Get current highest bid
            $currentHighestBid = $auctionData['current_highest_bid'] ?? 0;
            $minimumIncrement = $auctionData['minimum_bid_increment'] ?? 1.00;
            $reservePrice = $auctionData['reserve_price'] ?? 0;

            // Check minimum bid amount
            if ($bidAmount < $reservePrice) {
                return [
                    'success' => false,
                    'error' => 'BID_BELOW_RESERVE',
                    'message' => "Bid amount must be at least {$reservePrice}"
                ];
            }

            // Check bid increment
            $requiredMinimum = $currentHighestBid + $minimumIncrement;
            if ($bidAmount < $requiredMinimum) {
                return [
                    'success' => false,
                    'error' => 'BID_INCREMENT_TOO_LOW',
                    'message' => "Bid must be at least {$requiredMinimum}"
                ];
            }

            // Check maximum bid limit (if set)
            $maxBidLimit = $auctionData['max_bid_limit'] ?? null;
            if ($maxBidLimit && $bidAmount > $maxBidLimit) {
                return [
                    'success' => false,
                    'error' => 'BID_EXCEEDS_LIMIT',
                    'message' => "Bid cannot exceed {$maxBidLimit}"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'validated_amount' => $bidAmount,
                    'current_highest' => $currentHighestBid,
                    'minimum_increment' => $minimumIncrement,
                    'is_new_highest' => $bidAmount > $currentHighestBid
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'AMOUNT_VALIDATION_ERROR',
                'message' => 'Bid amount validation failed: ' . $e->getMessage()
            ];
        }
    }
}
