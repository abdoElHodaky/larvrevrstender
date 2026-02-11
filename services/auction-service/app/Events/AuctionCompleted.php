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

class AuctionCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Auction $auction;
    public ?array $winner;
    public array $seller;

    /**
     * Create a new event instance.
     */
    public function __construct(Auction $auction, ?array $winner, array $seller)
    {
        $this->auction = $auction;
        $this->winner = $winner;
        $this->seller = $seller;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('auctions'),
            new Channel("auction.{$this->auction->id}"),
            new PrivateChannel("user.{$this->seller['id']}")
        ];

        // Add private channel for winner if exists
        if ($this->winner) {
            $channels[] = new PrivateChannel("user.{$this->winner['id']}");
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
                'final_price' => $this->auction->current_highest_bid,
                'starts_at' => $this->auction->starts_at,
                'ends_at' => $this->auction->ends_at,
                'status' => $this->auction->status,
            ],
            'winner' => $this->winner ? [
                'id' => $this->winner['id'],
                'name' => $this->winner['name'],
                'winning_bid' => $this->winner['bid_amount'],
            ] : null,
            'seller' => [
                'id' => $this->seller['id'],
                'name' => $this->seller['name'],
            ],
            'has_winner' => $this->winner !== null,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'auction.completed';
    }
}
