<?php

namespace App\Listeners;

use App\Events\WinnerSelectedEvent;
use App\Services\OrderCreationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Winner Selected Event Listener
 * 
 * Automatically creates an order when a winner is selected for an auction.
 * This bridges the gap between the bidding service and order service.
 */
class WinnerSelectedListener implements ShouldQueue
{
    use InteractsWithQueue;

    private OrderCreationService $orderCreationService;

    /**
     * Create the event listener.
     */
    public function __construct(OrderCreationService $orderCreationService)
    {
        $this->orderCreationService = $orderCreationService;
    }

    /**
     * Handle the event.
     */
    public function handle(WinnerSelectedEvent $event): void
    {
        try {
            Log::info('Processing winner selected event for order creation', [
                'auction_id' => $event->auctionId,
                'winning_bid_id' => $event->winningBidId,
                'winner_user_id' => $event->winnerUserId
            ]);

            // Create order from winning bid
            $result = $this->orderCreationService->createFromWinningBid(
                $event->winningBidId,
                $event->auctionId
            );

            if ($result->success) {
                Log::info('Order created successfully from winner selection', [
                    'auction_id' => $event->auctionId,
                    'winning_bid_id' => $event->winningBidId,
                    'order_id' => $result->order->id,
                    'order_number' => $result->order->order_number
                ]);
            } else {
                Log::error('Failed to create order from winner selection', [
                    'auction_id' => $event->auctionId,
                    'winning_bid_id' => $event->winningBidId,
                    'error' => $result->message
                ]);

                // Could implement retry logic or manual intervention notification here
                $this->fail(new \Exception($result->message));
            }

        } catch (\Exception $e) {
            Log::error('Exception in winner selected listener', [
                'auction_id' => $event->auctionId,
                'winning_bid_id' => $event->winningBidId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger queue retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(WinnerSelectedEvent $event, \Throwable $exception): void
    {
        Log::critical('Winner selected listener failed permanently', [
            'auction_id' => $event->auctionId,
            'winning_bid_id' => $event->winningBidId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could send notification to administrators about the failure
        // Could create a manual intervention task
    }
}
