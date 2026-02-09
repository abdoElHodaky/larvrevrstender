<?php

namespace App\Listeners;

use App\Events\AuctionCreated;
use App\Events\AuctionStarted;
use App\Events\BidPlaced;
use App\Events\AuctionCompleted;
use App\Http\Clients\NotificationServiceClient;
use App\Http\Clients\AuthServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAuctionNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationServiceClient $notificationService;
    protected AuthServiceClient $authService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationServiceClient $notificationService, AuthServiceClient $authService)
    {
        $this->notificationService = $notificationService;
        $this->authService = $authService;
    }

    /**
     * Handle auction created event.
     */
    public function handleAuctionCreated(AuctionCreated $event): void
    {
        try {
            $auctionData = [
                'id' => $event->auction->id,
                'title' => $event->auction->title,
                'starting_price' => $event->auction->starting_price,
                'starts_at' => $event->auction->starts_at,
                'ends_at' => $event->auction->ends_at,
            ];

            // Send email notification to seller
            $this->notificationService->sendAuctionCreatedNotification($auctionData, $event->seller);

            // Send real-time event and webhooks
            $this->sendRealTimeAndWebhook('auction.created', [
                'auction' => $auctionData,
                'seller' => $event->seller
            ], ['auctions', "user.{$event->seller['id']}"]);

        } catch (\Exception $e) {
            \Log::error('Failed to send auction created notifications', [
                'auction_id' => $event->auction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle auction started event.
     */
    public function handleAuctionStarted(AuctionStarted $event): void
    {
        try {
            $auctionData = [
                'id' => $event->auction->id,
                'title' => $event->auction->title,
                'starting_price' => $event->auction->starting_price,
                'ends_at' => $event->auction->ends_at,
            ];

            // Send email notifications to watchers
            if (!empty($event->watchers)) {
                $this->notificationService->sendAuctionStartedNotification($auctionData, $event->watchers);
            }

            // Send real-time event and webhooks
            $channels = ['auctions', "auction.{$event->auction->id}"];
            foreach ($event->watchers as $watcher) {
                $channels[] = "user.{$watcher['id']}";
            }

            $this->sendRealTimeAndWebhook('auction.started', [
                'auction' => $auctionData,
                'watcher_count' => count($event->watchers)
            ], $channels);

        } catch (\Exception $e) {
            \Log::error('Failed to send auction started notifications', [
                'auction_id' => $event->auction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle bid placed event.
     */
    public function handleBidPlaced(BidPlaced $event): void
    {
        try {
            $auctionData = [
                'id' => $event->auction->id,
                'title' => $event->auction->title,
                'ends_at' => $event->auction->ends_at,
            ];

            // Send email notification to previous highest bidder
            if ($event->previousBidder) {
                $this->notificationService->sendBidPlacedNotification(
                    $auctionData,
                    $event->previousBidder,
                    $event->bid
                );
            }

            // Send real-time event and webhooks
            $channels = ['auctions', "auction.{$event->auction->id}", "user.{$event->bidder['id']}"];
            if ($event->previousBidder) {
                $channels[] = "user.{$event->previousBidder['id']}";
            }

            $this->sendRealTimeAndWebhook('bid.placed', [
                'auction' => $auctionData,
                'bid' => $event->bid,
                'bidder' => $event->bidder,
                'previous_bidder' => $event->previousBidder
            ], $channels);

        } catch (\Exception $e) {
            \Log::error('Failed to send bid placed notifications', [
                'auction_id' => $event->auction->id,
                'bid_id' => $event->bid['id'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle auction completed event.
     */
    public function handleAuctionCompleted(AuctionCompleted $event): void
    {
        try {
            $auctionData = [
                'id' => $event->auction->id,
                'title' => $event->auction->title,
                'final_price' => $event->auction->current_highest_bid,
                'ends_at' => $event->auction->ends_at,
            ];

            // Send email notifications to winner and seller
            if ($event->winner) {
                $this->notificationService->sendAuctionCompletedNotifications(
                    $auctionData,
                    $event->winner,
                    $event->seller
                );
            }

            // Send real-time event and webhooks
            $channels = ['auctions', "auction.{$event->auction->id}", "user.{$event->seller['id']}"];
            if ($event->winner) {
                $channels[] = "user.{$event->winner['id']}";
            }

            $this->sendRealTimeAndWebhook('auction.completed', [
                'auction' => $auctionData,
                'winner' => $event->winner,
                'seller' => $event->seller,
                'has_winner' => $event->winner !== null
            ], $channels);

        } catch (\Exception $e) {
            \Log::error('Failed to send auction completed notifications', [
                'auction_id' => $event->auction->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send real-time event and webhook notifications.
     */
    protected function sendRealTimeAndWebhook(string $event, array $data, array $channels): void
    {
        // Send real-time event
        $this->notificationService->sendRealTimeEvent($event, $data, $channels);

        // Send webhook notifications if configured
        $webhookUrls = config("auction.webhook_urls.{$event}", []);
        if (!empty($webhookUrls)) {
            $this->notificationService->sendWebhook($event, $data, $webhookUrls);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed($event, \Throwable $exception): void
    {
        $eventClass = get_class($event);
        $auctionId = $event->auction->id ?? 'unknown';

        \Log::error('Auction notification job failed', [
            'event_class' => $eventClass,
            'auction_id' => $auctionId,
            'error' => $exception->getMessage()
        ]);
    }
}
