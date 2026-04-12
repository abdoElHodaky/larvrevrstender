<?php

namespace App\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Auction $auction;
    public array $bid;
    public array $bidder;
    public ?array $previousBidder;

    /**
     * Create a new event instance.
     */
    public function __construct(Auction $auction, array $bid, array $bidder, ?array $previousBidder = null)
    {
        $this->auction = $auction;
        $this->bid = $bid;
        $this->bidder = $bidder;
        $this->previousBidder = $previousBidder;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('auctions'),
            new Channel("auction.{$this->auction->id}"),
            new PrivateChannel("user.{$this->bidder['id']}")
        ];

        // Add private channel for previous bidder if exists
        if ($this->previousBidder) {
            $channels[] = new PrivateChannel("user.{$this->previousBidder['id']}");
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'auction' => [
                'id' => $this->auction->id,
                'title' => $this->auction->title,
                'current_highest_bid' => $this->bid['amount'],
                'ends_at' => $this->auction->ends_at,
                'status' => $this->auction->status,
            ],
            'bid' => [
                'id' => $this->bid['id'],
                'amount' => $this->bid['amount'],
                'currency' => $this->bid['currency'] ?? 'USD',
            ],
            'bidder' => [
                'id' => $this->bidder['id'],
                'name' => $this->bidder['name'],
            ],
            'previous_bidder' => $this->previousBidder ? [
                'id' => $this->previousBidder['id'],
                'name' => $this->previousBidder['name'],
            ] : null,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'bid.placed';
    }
}
