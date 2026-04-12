<?php

namespace App\Activities;

use Shared\Procedures\Micro\NotificationProcedure;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;

/**
 * Notify Auction Ended Activity
 * 
 * Sends notifications about the auction ending to all relevant parties:
 * - Winner notification (if there's a winner)
 * - Seller notification with results
 * - Losing bidders notification
 * - Admin notification for high-value auctions
 */
class NotifyAuctionEndedActivity implements ActivityInterface
{
    use NotificationProcedure;

    /**
     * Execute the auction ended notification activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting auction ended notifications', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];
            $winner = $input['winner'];
            $finalStatus = $input['final_status'];

            $notifications = [];

            // 1. Send winner notification (if there's a winner)
            if ($winner) {
                $winnerNotification = $this->sendWinnerNotification($auctionId, $winner);
                $notifications['winner'] = $winnerNotification;
            }

            // 2. Send seller notification with auction results
            $sellerNotification = $this->sendSellerNotification($auctionId, $winner, $finalStatus);
            $notifications['seller'] = $sellerNotification;

            // 3. Send notification to losing bidders
            $losingBiddersNotification = $this->sendLosingBiddersNotification($auctionId, $winner);
            $notifications['losing_bidders'] = $losingBiddersNotification;

            // 4. Send admin notification for high-value auctions
            if ($winner && $winner['winning_amount'] > 100000) {
                $adminNotification = $this->sendAdminNotification($auctionId, $winner);
                $notifications['admin'] = $adminNotification;
            }

            Log::info('Auction ended notifications sent successfully', [
                'auction_id' => $auctionId,
                'notifications' => $notifications
            ]);

            return [
                'success' => true,
                'auction_id' => $auctionId,
                'winner' => $winner,
                'notifications' => $notifications,
                'message' => 'Auction ended notifications sent successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Auction ended notification activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send auction ended notifications'
            ];
        }
    }

    /**
     * Send winner notification
     */
    private function sendWinnerNotification(int $auctionId, array $winner): array
    {
        try {
            $notificationData = [
                'user_id' => $winner['user_id'],
                'type' => 'auction_won',
                'title' => 'Congratulations! You Won the Auction',
                'message' => "You have won auction #{$auctionId} with a bid of \${$winner['winning_amount']}. Payment processing will begin shortly.",
                'data' => [
                    'auction_id' => $auctionId,
                    'winning_amount' => $winner['winning_amount'],
                    'bid_id' => $winner['bid_id'],
                    'next_steps' => 'payment_processing'
                ],
                'channels' => ['email', 'push', 'sms'],
                'priority' => 'high'
            ];

            return $this->sendNotification($notificationData, []);

        } catch (\Exception $e) {
            Log::error('Failed to send winner notification', [
                'auction_id' => $auctionId,
                'winner' => $winner,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send seller notification with auction results
     */
    private function sendSellerNotification(int $auctionId, ?array $winner, string $finalStatus): array
    {
        try {
            if ($winner) {
                $title = 'Your Auction Ended Successfully';
                $message = "Your auction #{$auctionId} has ended with a winning bid of \${$winner['winning_amount']}.";
            } else {
                $title = 'Your Auction Ended';
                $message = "Your auction #{$auctionId} has ended without any qualifying bids.";
            }

            $notificationData = [
                'type' => 'auction_ended_seller',
                'title' => $title,
                'message' => $message,
                'data' => [
                    'auction_id' => $auctionId,
                    'final_status' => $finalStatus,
                    'winner' => $winner,
                    'has_winner' => !is_null($winner)
                ],
                'channels' => ['email', 'push'],
                'priority' => 'medium',
                'target_role' => 'seller'
            ];

            return $this->sendRoleBasedNotification($notificationData, []);

        } catch (\Exception $e) {
            Log::error('Failed to send seller notification', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to losing bidders
     */
    private function sendLosingBiddersNotification(int $auctionId, ?array $winner): array
    {
        try {
            if (!$winner) {
                // If no winner, all bidders are "losing" but auction had no winner
                $message = "Auction #{$auctionId} has ended without a winner. The reserve price was not met.";
            } else {
                $message = "Auction #{$auctionId} has ended. Unfortunately, you were not the winning bidder.";
            }

            $notificationData = [
                'type' => 'auction_ended_losing_bidder',
                'title' => 'Auction Ended',
                'message' => $message,
                'data' => [
                    'auction_id' => $auctionId,
                    'has_winner' => !is_null($winner),
                    'winning_amount' => $winner['winning_amount'] ?? null
                ],
                'channels' => ['email', 'push'],
                'priority' => 'low',
                'is_bulk' => true,
                'exclude_user_id' => $winner['user_id'] ?? null // Don't send to winner
            ];

            return $this->sendBulkNotification($notificationData, []);

        } catch (\Exception $e) {
            Log::error('Failed to send losing bidders notification', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send admin notification for high-value auctions
     */
    private function sendAdminNotification(int $auctionId, array $winner): array
    {
        try {
            $notificationData = [
                'type' => 'high_value_auction_ended',
                'title' => 'High-Value Auction Completed',
                'message' => "High-value auction #{$auctionId} has ended with a winning bid of \${$winner['winning_amount']}. Payment processing initiated.",
                'data' => [
                    'auction_id' => $auctionId,
                    'winner' => $winner,
                    'winning_amount' => $winner['winning_amount'],
                    'requires_monitoring' => true
                ],
                'channels' => ['email', 'slack'],
                'priority' => 'high',
                'is_bulk' => true,
                'target_role' => 'admin'
            ];

            return $this->sendRoleBasedNotification($notificationData, []);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notification', [
                'auction_id' => $auctionId,
                'winner' => $winner,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

