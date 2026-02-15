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

class BidStatusChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Bid $bid;
    public string $previousStatus;
    public string $newStatus;
    public array $auction;
    public array $bidder;

    /**
     * Create a new event instance.
     */
    public function __construct(Bid $bid, string $previousStatus, string $newStatus, array $auction, array $bidder)
    {
        $this->bid = $bid;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
        $this->auction = $auction;
        $this->bidder = $bidder;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("bid.{$this->bid->id}.status"),
            new PrivateChannel("user.{$this->bidder['id']}.bids"),
            new PrivateChannel("seller.{$this->auction['created_by']}.auction-bids"),
            new Channel("auction.{$this->auction['id']}.bids"), // Public for auction updates
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
                'status' => $this->newStatus,
                'previous_status' => $this->previousStatus,
                'updated_at' => $this->bid->updated_at,
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
            'status_change' => [
                'from' => $this->previousStatus,
                'to' => $this->newStatus,
                'changed_at' => now()->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'bid.status.changed';
    }
}
