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

class OrderShippedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public array $shippingDetails;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, array $shippingDetails)
    {
        $this->order = $order;
        $this->shippingDetails = $shippingDetails;
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
                'status' => $this->order->status,
                'total_amount' => $this->order->total_amount,
                'customer_id' => $this->order->customer_id,
                'merchant_id' => $this->order->merchant_id,
            ],
            'shipping' => [
                'tracking_number' => $this->shippingDetails['tracking_number'] ?? null,
                'carrier' => $this->shippingDetails['carrier'] ?? 'Unknown',
                'shipping_method' => $this->shippingDetails['shipping_method'] ?? 'Standard',
                'estimated_delivery' => $this->shippingDetails['estimated_delivery'] ?? null,
                'shipped_at' => $this->shippingDetails['shipped_at'] ?? now()->toISOString(),
                'tracking_url' => $this->shippingDetails['tracking_url'] ?? null,
            ],
            'shipping_address' => [
                'name' => $this->shippingDetails['address']['name'] ?? null,
                'street' => $this->shippingDetails['address']['street'] ?? null,
                'city' => $this->shippingDetails['address']['city'] ?? null,
                'state' => $this->shippingDetails['address']['state'] ?? null,
                'postal_code' => $this->shippingDetails['address']['postal_code'] ?? null,
                'country' => $this->shippingDetails['address']['country'] ?? null,
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
        return 'order.shipped';
    }
}
