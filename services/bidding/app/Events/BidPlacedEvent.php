<?php

namespace App\Events;

use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlacedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Bid $bid;
    public array $auction;
    public array $bidder;

    /**
     * Create a new event instance.
     */
    public function __construct(Bid $bid, array $auction, array $bidder)
    {
        $this->bid = $bid;
        $this->auction = $auction;
        $this->bidder = $bidder;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("auction.{$this->auction['id']}.bids"),
            new PrivateChannel("user.{$this->bidder['id']}.bids"),
            new PrivateChannel("seller.{$this->auction['created_by']}.auction-bids"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'bid' => [
                'id' => $this->bid->id,
                'auction_id' => $this->bid->auction_id,
                'amount' => $this->bid->amount,
                'status' => $this->bid->status,
                'created_at' => $this->bid->created_at,
            ],
            'auction' => [
                'id' => $this->auction['id'],
                'title' => $this->auction['title'],
                'current_highest_bid' => $this->auction['current_highest_bid'],
            ],
            'bidder' => [
                'id' => $this->bidder['id'],
                'name' => $this->bidder['name'],
            ],
            'timestamp' => now()->toISOString(),
            'bid_count' => $this->getBidCount(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    /**
     * Get total bid count for this auction.
     */
    private function getBidCount(): int
    {
        return Bid::where('auction_id', $this->bid->auction_id)->count();
    }
}
