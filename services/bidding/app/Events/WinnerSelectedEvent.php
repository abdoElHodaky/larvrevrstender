<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Winner Selected Event
 * 
 * Fired when a winner is selected for an auction.
 * This event triggers order creation and other downstream processes.
 */
class WinnerSelectedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $auctionId;
    public int $winningBidId;
    public int $winnerUserId;
    public float $winningAmount;
    public float $compositeScore;
    public array $evaluationSummary;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $auctionId,
        int $winningBidId,
        int $winnerUserId,
        float $winningAmount,
        float $compositeScore,
        array $evaluationSummary = []
    ) {
        $this->auctionId = $auctionId;
        $this->winningBidId = $winningBidId;
        $this->winnerUserId = $winnerUserId;
        $this->winningAmount = $winningAmount;
        $this->compositeScore = $compositeScore;
        $this->evaluationSummary = $evaluationSummary;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('auction.' . $this->auctionId),
            new PrivateChannel('user.' . $this->winnerUserId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'winning_bid_id' => $this->winningBidId,
            'winner_user_id' => $this->winnerUserId,
            'winning_amount' => $this->winningAmount,
            'composite_score' => $this->compositeScore,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'winner.selected';
    }
}
