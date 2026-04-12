<?php

namespace App\Events;

use App\Models\Escrow;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EscrowReleased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $escrow;

    /**
     * Create a new event instance.
     */
    public function __construct(Escrow $escrow)
    {
        $this->escrow = $escrow;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->escrow->buyer_id),
            new PrivateChannel('user.'.$this->escrow->seller_id),
            new PrivateChannel('order.'.$this->escrow->order_id),
            new PrivateChannel('escrow.'.$this->escrow->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'escrow_id' => $this->escrow->id,
            'order_id' => $this->escrow->order_id,
            'payment_id' => $this->escrow->payment_id,
            'buyer_id' => $this->escrow->buyer_id,
            'seller_id' => $this->escrow->seller_id,
            'amount' => $this->escrow->amount,
            'currency' => $this->escrow->currency,
            'status' => $this->escrow->status,
            'released_at' => $this->escrow->released_at,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'escrow.released';
    }
}

