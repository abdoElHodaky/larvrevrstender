<?php

namespace App\Listeners;

use App\Events\AuctionCreated;
use App\RPC\Adapters\NotificationServiceAdapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAuctionCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationServiceAdapter $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationServiceAdapter $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(AuctionCreated $event): void
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
            $emailSent = $this->notificationService->sendAuctionCreatedNotification(
                $auctionData,
                $event->seller
            );

            // Send real-time event
            $this->notificationService->sendRealTimeEvent(
                'auction.created',
                [
                    'auction' => $auctionData,
                    'seller' => $event->seller
                ],
                ['auctions', "user.{$event->seller['id']}"]
            );

            // Send webhook notifications if configured
            $webhookUrls = config('auction.webhook_urls.auction_created', []);
            if (!empty($webhookUrls)) {
                $this->notificationService->sendWebhook(
                    'auction.created',
                    [
                        'auction' => $auctionData,
                        'seller' => $event->seller
                    ],
                    $webhookUrls
                );
            }

            \Log::info('Auction created notifications sent', [
                'auction_id' => $event->auction->id,
                'seller_id' => $event->seller['id'],
                'email_sent' => $emailSent
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send auction created notifications', [
                'auction_id' => $event->auction->id,
                'seller_id' => $event->seller['id'],
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger retry if using queues
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(AuctionCreated $event, \Throwable $exception): void
    {
        \Log::error('Auction created notification job failed', [
            'auction_id' => $event->auction->id,
            'seller_id' => $event->seller['id'],
            'error' => $exception->getMessage()
        ]);
    }
}
