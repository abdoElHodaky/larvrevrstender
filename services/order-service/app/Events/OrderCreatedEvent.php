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

/**
 * OrderCreatedEvent
 * 
 * Fired when a new order is created from a winning bid.
 * Triggers payment workflow initiation and related processes.
 */
class OrderCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public array $orderData;
    public array $paymentData;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, array $orderData = [], array $paymentData = [])
    {
        $this->order = $order;
        $this->orderData = $orderData;
        $this->paymentData = $paymentData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order.' . $this->order->id),
            new PrivateChannel('user.' . $this->order->customer_id),
            new PrivateChannel('user.' . $this->order->merchant_id),
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
            'total_amount' => $this->order->total_amount,
            'currency' => $this->order->currency,
            'status' => $this->order->status,
            'payment_due_at' => $this->order->payment_due_at?->toISOString(),
            'created_at' => $this->order->created_at->toISOString(),
            'order_data' => $this->orderData,
            'payment_data' => $this->paymentData,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }
}
