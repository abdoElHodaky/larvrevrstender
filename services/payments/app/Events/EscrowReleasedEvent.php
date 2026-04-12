<?php

namespace App\Events;

use App\Models\Escrow;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EscrowReleasedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Escrow $escrow;
    public array $releaseDetails;

    /**
     * Create a new event instance.
     */
    public function __construct(Escrow $escrow, array $releaseDetails = [])
    {
        $this->escrow = $escrow;
        $this->releaseDetails = $releaseDetails;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("escrow.{$this->escrow->id}"),
            new PrivateChannel("user.{$this->escrow->buyer_id}.payments"),
            new PrivateChannel("user.{$this->escrow->seller_id}.payments"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'escrow' => [
                'id' => $this->escrow->id,
                'escrow_reference' => $this->escrow->escrow_reference ?? null,
                'amount' => $this->escrow->amount,
                'currency' => $this->escrow->currency ?? 'USD',
                'status' => $this->escrow->status,
                'buyer_id' => $this->escrow->buyer_id,
                'seller_id' => $this->escrow->seller_id,
                'created_at' => $this->escrow->created_at,
                'released_at' => $this->escrow->updated_at,
            ],
            'release' => [
                'release_type' => $this->releaseDetails['release_type'] ?? 'full', // full, partial, disputed
                'released_amount' => $this->releaseDetails['released_amount'] ?? $this->escrow->amount,
                'release_reason' => $this->releaseDetails['release_reason'] ?? 'Transaction completed',
                'release_conditions_met' => $this->releaseDetails['conditions_met'] ?? [],
                'released_to' => $this->releaseDetails['released_to'] ?? 'seller',
                'release_method' => $this->releaseDetails['release_method'] ?? 'automatic',
                'released_at' => $this->releaseDetails['released_at'] ?? now()->toISOString(),
                'processing_fee' => $this->releaseDetails['processing_fee'] ?? null,
                'net_amount' => $this->releaseDetails['net_amount'] ?? $this->escrow->amount,
            ],
            'transaction' => [
                'transaction_id' => $this->releaseDetails['transaction_id'] ?? null,
                'order_id' => $this->releaseDetails['order_id'] ?? null,
                'auction_id' => $this->releaseDetails['auction_id'] ?? null,
            ],
            'buyer' => [
                'id' => $this->escrow->buyer_id,
            ],
            'seller' => [
                'id' => $this->escrow->seller_id,
            ],
            'dispute_info' => [
                'was_disputed' => $this->releaseDetails['was_disputed'] ?? false,
                'dispute_resolution' => $this->releaseDetails['dispute_resolution'] ?? null,
                'mediator_decision' => $this->releaseDetails['mediator_decision'] ?? null,
            ],
            'next_steps' => [
                'funds_available' => $this->releaseDetails['funds_available'] ?? true,
                'withdrawal_available' => $this->releaseDetails['withdrawal_available'] ?? true,
                'estimated_arrival' => $this->releaseDetails['estimated_arrival'] ?? null,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'escrow.released';
    }
}
