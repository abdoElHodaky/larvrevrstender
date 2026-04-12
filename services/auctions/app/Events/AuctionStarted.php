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

class AuctionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Auction $auction;
    public array $watchers;

    /**
     * Create a new event instance.
     */
    public function __construct(Auction $auction, array $watchers = [])
    {
        $this->auction = $auction;
        $this->watchers = $watchers;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('auctions'),
            new Channel("auction.{$this->auction->id}")
        ];

        // Add private channels for each watcher
        foreach ($this->watchers as $watcher) {
            $channels[] = new PrivateChannel("user.{$watcher['id']}");
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
                'starting_price' => $this->auction->starting_price,
                'starts_at' => $this->auction->starts_at,
                'ends_at' => $this->auction->ends_at,
                'status' => $this->auction->status,
            ],
            'watcher_count' => count($this->watchers),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'auction.started';
    }
}
