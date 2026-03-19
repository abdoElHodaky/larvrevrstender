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

class OrderDeliveredEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public array $deliveryDetails;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, array $deliveryDetails)
    {
        $this->order = $order;
        $this->deliveryDetails = $deliveryDetails;
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
            'delivery' => [
                'delivered_at' => $this->deliveryDetails['delivered_at'] ?? now()->toISOString(),
                'delivered_to' => $this->deliveryDetails['delivered_to'] ?? null,
                'delivery_method' => $this->deliveryDetails['delivery_method'] ?? 'Standard',
                'signature_required' => $this->deliveryDetails['signature_required'] ?? false,
                'signature_obtained' => $this->deliveryDetails['signature_obtained'] ?? false,
                'delivery_notes' => $this->deliveryDetails['delivery_notes'] ?? null,
                'delivery_photo_url' => $this->deliveryDetails['delivery_photo_url'] ?? null,
            ],
            'delivery_address' => [
                'name' => $this->deliveryDetails['address']['name'] ?? null,
                'street' => $this->deliveryDetails['address']['street'] ?? null,
                'city' => $this->deliveryDetails['address']['city'] ?? null,
                'state' => $this->deliveryDetails['address']['state'] ?? null,
                'postal_code' => $this->deliveryDetails['address']['postal_code'] ?? null,
                'country' => $this->deliveryDetails['address']['country'] ?? null,
            ],
            'customer' => [
                'id' => $this->order->customer_id,
                'name' => $this->order->customer->name ?? 'Unknown',
            ],
            'merchant' => [
                'id' => $this->order->merchant_id,
                'name' => $this->order->merchant->name ?? 'Unknown',
            ],
            'completion' => [
                'order_completed' => true,
                'completion_time' => now()->toISOString(),
                'review_requested' => $this->deliveryDetails['review_requested'] ?? true,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'order.delivered';
    }
}
