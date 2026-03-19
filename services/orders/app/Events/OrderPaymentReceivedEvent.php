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

class OrderPaymentReceivedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public array $paymentDetails;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, array $paymentDetails)
    {
        $this->order = $order;
        $this->paymentDetails = $paymentDetails;
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
            'payment' => [
                'payment_id' => $this->paymentDetails['payment_id'] ?? null,
                'amount' => $this->paymentDetails['amount'] ?? $this->order->total_amount,
                'payment_method' => $this->paymentDetails['payment_method'] ?? 'unknown',
                'transaction_id' => $this->paymentDetails['transaction_id'] ?? null,
                'payment_status' => $this->paymentDetails['status'] ?? 'completed',
                'received_at' => $this->paymentDetails['received_at'] ?? now()->toISOString(),
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
        return 'order.payment.received';
    }
}
