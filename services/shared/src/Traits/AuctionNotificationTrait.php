<?php

namespace Shared\Traits;

/**
 * Auction Notification Trait
 * 
 * Provides auction-specific notification methods with predefined templates
 * and business logic for auction-related notifications.
 * 
 * @package Shared\Traits
 */
trait AuctionNotificationTrait
{
    use NotificationTrait;
    
    /**
     * Initialize auction notification context
     */
    public function initializeAuctionNotifications(): void
    {
        $this->setNotificationService('auction');
    }
    
    /**
     * Notify when new auction is created
     *
     * @param array $auctionData Auction information
     * @param array $interestedUsers Users who might be interested
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyAuctionCreated(
        array $auctionData,
        array $interestedUsers = [],
        string $language = 'en'
    ): bool {
        if (empty($interestedUsers)) {
            return true; // No users to notify
        }
        
        $recipients = [];
        foreach ($interestedUsers as $user) {
            $recipients[] = [
                'recipient' => $user['email'],
                'data' => array_merge($auctionData, $user)
            ];
        }
        
        return $this->sendBulkNotification([
            'channel' => 'email',
            'template' => 'auction.created',
            'recipients' => $recipients,
            'language' => $language,
            'batch_size' => 50,
            'priority' => 'normal',
            'tracking' => [
                'event' => 'auction_created',
                'auction_id' => $auctionData['id'] ?? null
            ]
        ]);
    }
    
    /**
     * Notify when new bid is placed
     *
     * @param array $auctionData Auction information
     * @param array $bidData Bid information
     * @param array $watchers Users watching this auction
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyNewBid(
        array $auctionData,
        array $bidData,
        array $watchers = [],
        string $language = 'en'
    ): bool {
        $templateData = array_merge($auctionData, [
            'bid_amount' => $bidData['amount'],
            'bidder_name' => $bidData['bidder_name'] ?? 'Anonymous',
            'current_highest_bid' => $bidData['amount'],
            'bid_time' => $bidData['created_at'] ?? now()->format('Y-m-d H:i:s')
        ]);
        
        $recipients = [];
        foreach ($watchers as $watcher) {
            // Don't notify the bidder about their own bid
            if ($watcher['id'] !== $bidData['bidder_id']) {
                $recipients[] = [
                    'recipient' => $watcher['email'],
                    'data' => array_merge($templateData, $watcher)
                ];
            }
        }
        
        if (empty($recipients)) {
            return true;
        }
        
        return $this->sendBulkNotification([
            'channel' => 'email',
            'template' => 'auction.new_bid',
            'recipients' => $recipients,
            'language' => $language,
            'batch_size' => 100,
            'priority' => 'high',
            'tracking' => [
                'event' => 'auction_new_bid',
                'auction_id' => $auctionData['id'] ?? null,
                'bid_amount' => $bidData['amount']
            ]
        ]);
    }
    
    /**
     * Notify when auction is ending soon
     *
     * @param array $auctionData Auction information
     * @param array $participants Auction participants
     * @param int $minutesLeft Minutes until auction ends
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyAuctionEndingSoon(
        array $auctionData,
        array $participants,
        int $minutesLeft,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($auctionData, [
            'minutes_left' => $minutesLeft,
            'ends_at' => $auctionData['ends_at'] ?? null,
            'current_highest_bid' => $auctionData['current_bid'] ?? 0
        ]);
        
        $recipients = [];
        foreach ($participants as $participant) {
            $recipients[] = [
                'recipient' => $participant['email'],
                'data' => array_merge($templateData, $participant)
            ];
        }
        
        return $this->sendMultiChannel([
            'channels' => ['email', 'push'],
            'template' => 'auction.ending_soon',
            'recipients' => $recipients,
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'auction_ending_soon',
                'auction_id' => $auctionData['id'] ?? null,
                'minutes_left' => $minutesLeft
            ]
        ]);
    }
    
    /**
     * Notify when auction has ended
     *
     * @param array $auctionData Auction information
     * @param array|null $winner Winner information (null if no winner)
     * @param array $participants All auction participants
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyAuctionEnded(
        array $auctionData,
        ?array $winner,
        array $participants,
        string $language = 'en'
    ): bool {
        $success = true;
        
        // Notify winner
        if ($winner) {
            $winnerSuccess = $this->sendMultiChannel([
                'channels' => ['email', 'sms'],
                'template' => 'auction.winner',
                'recipients' => [
                    ['recipient' => $winner['email'], 'channel' => 'email'],
                    ['recipient' => $winner['phone'] ?? null, 'channel' => 'sms']
                ],
                'data' => array_merge($auctionData, $winner, [
                    'winning_bid' => $winner['bid_amount'],
                    'payment_deadline' => now()->addDays(3)->format('Y-m-d H:i:s')
                ]),
                'language' => $language,
                'fallback_strategy' => 'all',
                'priority' => 'urgent',
                'tracking' => [
                    'event' => 'auction_winner',
                    'auction_id' => $auctionData['id'] ?? null,
                    'winner_id' => $winner['id']
                ]
            ]);
            
            $success = $success && $winnerSuccess;
        }
        
        // Notify other participants
        $otherParticipants = [];
        foreach ($participants as $participant) {
            if (!$winner || $participant['id'] !== $winner['id']) {
                $otherParticipants[] = [
                    'recipient' => $participant['email'],
                    'data' => array_merge($auctionData, $participant, [
                        'has_winner' => $winner !== null,
                        'winning_bid' => $winner['bid_amount'] ?? null,
                        'winner_name' => $winner['name'] ?? null
                    ])
                ];
            }
        }
        
        if (!empty($otherParticipants)) {
            $participantsSuccess = $this->sendBulkNotification([
                'channel' => 'email',
                'template' => 'auction.ended',
                'recipients' => $otherParticipants,
                'language' => $language,
                'batch_size' => 100,
                'priority' => 'normal',
                'tracking' => [
                    'event' => 'auction_ended',
                    'auction_id' => $auctionData['id'] ?? null
                ]
            ]);
            
            $success = $success && $participantsSuccess;
        }
        
        return $success;
    }
    
    /**
     * Notify when bid is outbid
     *
     * @param array $auctionData Auction information
     * @param array $outbidUser User who was outbid
     * @param array $newBidData New bid information
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyBidOutbid(
        array $auctionData,
        array $outbidUser,
        array $newBidData,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($auctionData, $outbidUser, [
            'previous_bid' => $outbidUser['bid_amount'],
            'new_highest_bid' => $newBidData['amount'],
            'time_left' => $this->calculateTimeLeft($auctionData['ends_at'] ?? null)
        ]);
        
        return $this->sendMultiChannel([
            'channels' => ['email', 'push'],
            'template' => 'auction.outbid',
            'recipients' => [
                ['recipient' => $outbidUser['email'], 'channel' => 'email'],
                ['recipient' => $outbidUser['device_tokens'] ?? [], 'channel' => 'push']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'high',
            'tracking' => [
                'event' => 'auction_outbid',
                'auction_id' => $auctionData['id'] ?? null,
                'outbid_user_id' => $outbidUser['id']
            ]
        ]);
    }
    
    /**
     * Notify about payment reminder for auction winner
     *
     * @param array $auctionData Auction information
     * @param array $winner Winner information
     * @param int $hoursLeft Hours left to pay
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyPaymentReminder(
        array $auctionData,
        array $winner,
        int $hoursLeft,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($auctionData, $winner, [
            'hours_left' => $hoursLeft,
            'payment_deadline' => $winner['payment_deadline'] ?? null,
            'payment_url' => config('app.frontend_url') . "/auctions/{$auctionData['id']}/payment"
        ]);
        
        return $this->sendMultiChannel([
            'channels' => ['email', 'sms'],
            'template' => 'auction.payment_reminder',
            'recipients' => [
                ['recipient' => $winner['email'], 'channel' => 'email'],
                ['recipient' => $winner['phone'] ?? null, 'channel' => 'sms']
            ],
            'data' => $templateData,
            'language' => $language,
            'fallback_strategy' => 'all',
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'auction_payment_reminder',
                'auction_id' => $auctionData['id'] ?? null,
                'winner_id' => $winner['id'],
                'hours_left' => $hoursLeft
            ]
        ]);
    }
    
    /**
     * Notify when auction is cancelled
     *
     * @param array $auctionData Auction information
     * @param string $reason Cancellation reason
     * @param array $participants Auction participants
     * @param string $language Language preference
     * @return bool Success status
     */
    public function notifyAuctionCancelled(
        array $auctionData,
        string $reason,
        array $participants,
        string $language = 'en'
    ): bool {
        $templateData = array_merge($auctionData, [
            'cancellation_reason' => $reason,
            'cancelled_at' => now()->format('Y-m-d H:i:s'),
            'refund_info' => 'All deposits will be refunded within 3-5 business days'
        ]);
        
        $recipients = [];
        foreach ($participants as $participant) {
            $recipients[] = [
                'recipient' => $participant['email'],
                'data' => array_merge($templateData, $participant)
            ];
        }
        
        return $this->sendBulkNotification([
            'channel' => 'email',
            'template' => 'auction.cancelled',
            'recipients' => $recipients,
            'language' => $language,
            'batch_size' => 100,
            'priority' => 'urgent',
            'tracking' => [
                'event' => 'auction_cancelled',
                'auction_id' => $auctionData['id'] ?? null,
                'reason' => $reason
            ]
        ]);
    }
    
    /**
     * Schedule auction reminder notifications
     *
     * @param array $auctionData Auction information
     * @param array $watchers Users watching the auction
     * @param string $reminderTime When to send reminder (datetime string)
     * @param string $language Language preference
     * @return bool Success status
     */
    public function scheduleAuctionReminder(
        array $auctionData,
        array $watchers,
        string $reminderTime,
        string $language = 'en'
    ): bool {
        $recipients = [];
        foreach ($watchers as $watcher) {
            $recipients[] = [
                'recipient' => $watcher['email'],
                'data' => array_merge($auctionData, $watcher)
            ];
        }
        
        return $this->scheduleNotification([
            'channel' => 'email',
            'template' => 'auction.reminder',
            'recipients' => $recipients,
            'scheduled_at' => $reminderTime,
            'language' => $language,
            'schedule_id' => "auction_reminder_{$auctionData['id']}"
        ]);
    }
    
    /**
     * Calculate time left until auction ends
     *
     * @param string|null $endsAt End datetime
     * @return string Human readable time left
     */
    private function calculateTimeLeft(?string $endsAt): string
    {
        if (!$endsAt) {
            return 'Unknown';
        }
        
        $endTime = strtotime($endsAt);
        $now = time();
        $diff = $endTime - $now;
        
        if ($diff <= 0) {
            return 'Ended';
        }
        
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
}
