<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $previousStatus;
    public string $newStatus;
    public ?string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, string $previousStatus, string $newStatus, ?string $reason = null)
    {
        $this->order = $order;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->order->id}"),
            new PrivateChannel("user.{$this->order->customer_id}.orders"),
            new PrivateChannel("merchant.{$this->order->merchant_id}.orders"),
            new PrivateChannel("orders.status.{$this->newStatus}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->newStatus,
                'previous_status' => $this->previousStatus,
                'total_amount' => $this->order->total_amount,
                'customer_id' => $this->order->customer_id,
                'merchant_id' => $this->order->merchant_id,
                'updated_at' => $this->order->updated_at,
            ],
            'status_change' => [
                'from' => $this->previousStatus,
                'to' => $this->newStatus,
                'reason' => $this->reason,
                'changed_at' => now()->toISOString(),
            ],
            'customer' => [
                'id' => $this->order->customer_id,
                'name' => $this->order->customer->name ?? 'Unknown',
            ],
            'merchant' => [
                'id' => $this->order->merchant_id,
                'name' => $this->order->merchant->name ?? 'Unknown',
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }
}
