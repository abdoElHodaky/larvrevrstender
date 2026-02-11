<?php

namespace App\Http\Clients;

class NotificationServiceClient extends BaseServiceClient
{
    public function __construct()
    {
        parent::__construct(config('services.notification_service.url'));
    }

    /**
     * Send email notification.
     */
    public function sendEmail(array $emailData): bool
    {
        try {
            $response = $this->post('/notifications/email', $emailData);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to send email notification', [
                'email_data' => $emailData,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send auction created notification.
     */
    public function sendAuctionCreatedNotification(array $auctionData, array $sellerData): bool
    {
        try {
            $emailData = [
                'template' => 'auction.created',
                'to' => $sellerData['email'],
                'to_name' => $sellerData['name'],
                'subject' => 'Your auction has been created successfully',
                'data' => [
                    'seller_name' => $sellerData['name'],
                    'auction_title' => $auctionData['title'],
                    'auction_id' => $auctionData['id'],
                    'starting_price' => $auctionData['starting_price'],
                    'starts_at' => $auctionData['starts_at'],
                    'ends_at' => $auctionData['ends_at'],
                    'auction_url' => config('app.frontend_url') . "/auctions/{$auctionData['id']}"
                ]
            ];

            return $this->sendEmail($emailData);
        } catch (\Exception $e) {
            \Log::error('Failed to send auction created notification', [
                'auction_id' => $auctionData['id'] ?? null,
                'seller_email' => $sellerData['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send auction started notification to watchers.
     */
    public function sendAuctionStartedNotification(array $auctionData, array $watchers): bool
    {
        try {
            $notifications = [];

            foreach ($watchers as $watcher) {
                $notifications[] = [
                    'template' => 'auction.started',
                    'to' => $watcher['email'],
                    'to_name' => $watcher['name'],
                    'subject' => "Auction '{$auctionData['title']}' has started!",
                    'data' => [
                        'watcher_name' => $watcher['name'],
                        'auction_title' => $auctionData['title'],
                        'auction_id' => $auctionData['id'],
                        'starting_price' => $auctionData['starting_price'],
                        'ends_at' => $auctionData['ends_at'],
                        'auction_url' => config('app.frontend_url') . "/auctions/{$auctionData['id']}"
                    ]
                ];
            }

            $response = $this->post('/notifications/email/batch', [
                'notifications' => $notifications
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to send auction started notifications', [
                'auction_id' => $auctionData['id'] ?? null,
                'watcher_count' => count($watchers),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send bid placed notification to previous highest bidder.
     */
    public function sendBidPlacedNotification(array $auctionData, array $previousBidderData, array $newBidData): bool
    {
        try {
            $emailData = [
                'template' => 'auction.bid_placed',
                'to' => $previousBidderData['email'],
                'to_name' => $previousBidderData['name'],
                'subject' => "You've been outbid on '{$auctionData['title']}'",
                'data' => [
                    'bidder_name' => $previousBidderData['name'],
                    'auction_title' => $auctionData['title'],
                    'auction_id' => $auctionData['id'],
                    'previous_bid_amount' => $previousBidderData['bid_amount'],
                    'new_bid_amount' => $newBidData['amount'],
                    'ends_at' => $auctionData['ends_at'],
                    'auction_url' => config('app.frontend_url') . "/auctions/{$auctionData['id']}"
                ]
            ];

            return $this->sendEmail($emailData);
        } catch (\Exception $e) {
            \Log::error('Failed to send bid placed notification', [
                'auction_id' => $auctionData['id'] ?? null,
                'previous_bidder_email' => $previousBidderData['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send auction completed notification to winner and seller.
     */
    public function sendAuctionCompletedNotifications(array $auctionData, array $winnerData, array $sellerData): bool
    {
        try {
            $notifications = [];

            // Notification to winner
            $notifications[] = [
                'template' => 'auction.completed.winner',
                'to' => $winnerData['email'],
                'to_name' => $winnerData['name'],
                'subject' => "Congratulations! You won '{$auctionData['title']}'",
                'data' => [
                    'winner_name' => $winnerData['name'],
                    'auction_title' => $auctionData['title'],
                    'auction_id' => $auctionData['id'],
                    'winning_bid_amount' => $winnerData['bid_amount'],
                    'seller_name' => $sellerData['name'],
                    'auction_url' => config('app.frontend_url') . "/auctions/{$auctionData['id']}"
                ]
            ];

            // Notification to seller
            $notifications[] = [
                'template' => 'auction.completed.seller',
                'to' => $sellerData['email'],
                'to_name' => $sellerData['name'],
                'subject' => "Your auction '{$auctionData['title']}' has ended",
                'data' => [
                    'seller_name' => $sellerData['name'],
                    'auction_title' => $auctionData['title'],
                    'auction_id' => $auctionData['id'],
                    'final_bid_amount' => $winnerData['bid_amount'],
                    'winner_name' => $winnerData['name'],
                    'auction_url' => config('app.frontend_url') . "/auctions/{$auctionData['id']}"
                ]
            ];

            $response = $this->post('/notifications/email/batch', [
                'notifications' => $notifications
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to send auction completed notifications', [
                'auction_id' => $auctionData['id'] ?? null,
                'winner_email' => $winnerData['email'] ?? null,
                'seller_email' => $sellerData['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send real-time event notification.
     */
    public function sendRealTimeEvent(string $event, array $data, array $channels = []): bool
    {
        try {
            $response = $this->post('/notifications/realtime', [
                'event' => $event,
                'data' => $data,
                'channels' => $channels,
                'timestamp' => now()->toISOString()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to send real-time event', [
                'event' => $event,
                'channels' => $channels,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send webhook notification to external integrations.
     */
    public function sendWebhook(string $event, array $data, array $webhookUrls = []): bool
    {
        try {
            $response = $this->post('/notifications/webhook', [
                'event' => $event,
                'data' => $data,
                'webhook_urls' => $webhookUrls,
                'timestamp' => now()->toISOString()
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to send webhook notification', [
                'event' => $event,
                'webhook_urls' => $webhookUrls,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user notification preferences.
     */
    public function getUserNotificationPreferences(int $userId): array
    {
        try {
            $response = $this->get("/notifications/users/{$userId}/preferences");

            return $response->successful() ? $response->json('preferences', []) : [];
        } catch (\Exception $e) {
            \Log::error('Failed to get user notification preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Update user notification preferences.
     */
    public function updateUserNotificationPreferences(int $userId, array $preferences): bool
    {
        try {
            $response = $this->put("/notifications/users/{$userId}/preferences", [
                'preferences' => $preferences
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Failed to update user notification preferences', [
                'user_id' => $userId,
                'preferences' => $preferences,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
