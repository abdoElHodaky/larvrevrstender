<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $fromState;
    public $toState;
    public $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, string $fromState, string $toState, ?string $reason = null)
    {
        $this->order = $order;
        $this->fromState = $fromState;
        $this->toState = $toState;
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->order->customer_id),
            new PrivateChannel('user.'.$this->order->merchant_id),
            new PrivateChannel('order.'.$this->order->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_id' => $this->order->customer_id,
            'merchant_id' => $this->order->merchant_id,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'state_label' => $this->order->state->label(),
            'state_color' => $this->order->state->color(),
            'state_description' => $this->order->state->description(),
            'reason' => $this->reason,
            'changed_at' => now()->toISOString(),
            'available_transitions' => $this->order->getAvailableTransitions(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.state.changed';
    }
}
