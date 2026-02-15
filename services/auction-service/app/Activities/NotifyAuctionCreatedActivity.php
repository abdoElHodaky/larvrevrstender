<?php

namespace App\Activities;

use Shared\Procedures\Micro\NotificationProcedure;
use Workflow\ActivityInterface;
use Illuminate\Support\Facades\Log;

/**
 * Notify Auction Created Activity
 * 
 * Sends notifications about the newly created auction to relevant parties:
 * - Auction creator confirmation
 * - Interested bidders (if any)
 * - System administrators (for high-value auctions)
 */
class NotifyAuctionCreatedActivity implements ActivityInterface
{
    use NotificationProcedure;

    /**
     * Execute the notification activity
     */
    public function execute(array $input): array
    {
        Log::info('Starting auction creation notifications', ['input' => $input]);

        try {
            $auctionId = $input['auction_id'];
            $auctionData = $input['auction_data'];

            $notifications = [];

            // 1. Send confirmation to auction creator
            $creatorNotification = $this->sendCreatorConfirmation($auctionId, $auctionData);
            $notifications['creator'] = $creatorNotification;

            // 2. Send notification to interested bidders (if any exist for this vehicle type)
            $bidderNotification = $this->sendBidderNotification($auctionId, $auctionData);
            $notifications['bidders'] = $bidderNotification;

            // 3. Send admin notification for high-value auctions
            if ($auctionData['starting_price'] > 100000) { // High-value threshold
                $adminNotification = $this->sendAdminNotification($auctionId, $auctionData);
                $notifications['admin'] = $adminNotification;
            }

            Log::info('Auction creation notifications sent successfully', [
                'auction_id' => $auctionId,
                'notifications' => $notifications
            ]);

            return [
                'success' => true,
                'auction_id' => $auctionId,
                'notifications' => $notifications,
                'message' => 'Auction creation notifications sent successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Auction creation notification activity failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $input
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send auction creation notifications'
            ];
        }
    }

    /**
     * Send confirmation notification to auction creator
     */
    private function sendCreatorConfirmation(int $auctionId, array $auctionData): array
    {
        try {
            $notificationData = [
                'user_id' => $auctionData['created_by'],
                'type' => 'auction_created',
                'title' => 'Auction Created Successfully',
                'message' => "Your auction '{$auctionData['title']}' has been created and scheduled to start at {$auctionData['starts_at']}.",
                'data' => [
                    'auction_id' => $auctionId,
                    'auction_title' => $auctionData['title'],
                    'starting_price' => $auctionData['starting_price'],
                    'starts_at' => $auctionData['starts_at'],
                    'ends_at' => $auctionData['ends_at']
                ],
                'channels' => ['email', 'push'],
                'priority' => 'medium'
            ];

            return $this->sendNotification($notificationData, []);

        } catch (\Exception $e) {
            Log::error('Failed to send creator confirmation', [
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
     * Send notification to interested bidders
     */
    private function sendBidderNotification(int $auctionId, array $auctionData): array
    {
        try {
            // This would typically query for users interested in this vehicle type
            // For now, we'll create a general notification structure
            $notificationData = [
                'type' => 'new_auction_available',
                'title' => 'New Auction Available',
                'message' => "A new auction '{$auctionData['title']}' is now available. Starting price: \${$auctionData['starting_price']}",
                'data' => [
                    'auction_id' => $auctionId,
                    'auction_title' => $auctionData['title'],
                    'starting_price' => $auctionData['starting_price'],
                    'starts_at' => $auctionData['starts_at'],
                    'vehicle_id' => $auctionData['vehicle_id']
                ],
                'channels' => ['push', 'email'],
                'priority' => 'low',
                'is_bulk' => true
            ];

            return $this->sendBulkNotification($notificationData, []);

        } catch (\Exception $e) {
            Log::error('Failed to send bidder notification', [
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
     * Send notification to administrators for high-value auctions
     */
    private function sendAdminNotification(int $auctionId, array $auctionData): array
    {
        try {
            $notificationData = [
                'type' => 'high_value_auction_created',
                'title' => 'High-Value Auction Created',
                'message' => "A high-value auction '{$auctionData['title']}' has been created with starting price \${$auctionData['starting_price']}. Please review.",
                'data' => [
                    'auction_id' => $auctionId,
                    'auction_title' => $auctionData['title'],
                    'starting_price' => $auctionData['starting_price'],
                    'created_by' => $auctionData['created_by'],
                    'requires_review' => true
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
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

