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

class AuctionCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Auction $auction;
    public array $seller;

    /**
     * Create a new event instance.
     */
    public function __construct(Auction $auction, array $seller)
    {
        $this->auction = $auction;
        $this->seller = $seller;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('auctions'),
            new PrivateChannel("user.{$this->seller['id']}")
        ];
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
                'starting_price' => $this->auction->starting_price,
                'starts_at' => $this->auction->starts_at,
                'ends_at' => $this->auction->ends_at,
                'status' => $this->auction->status,
            ],
            'seller' => [
                'id' => $this->seller['id'],
                'name' => $this->seller['name'],
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'auction.created';
    }
}
