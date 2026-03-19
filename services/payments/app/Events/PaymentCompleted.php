<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment Completed Event
 * 
 * Fired when a payment is successfully completed during saga execution.
 * This event signals successful payment processing and order fulfillment readiness.
 */
class PaymentCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public array $orderData;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, array $orderData = [])
    {
        $this->payment = $payment;
        $this->orderData = $orderData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->payment->customer_id}.payments"),
            new PrivateChannel("payment.{$this->payment->id}.status"),
            new Channel("order.{$this->payment->order_id}.payments"), // Public for order updates
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment' => [
                'id' => $this->payment->id,
                'payment_reference' => $this->payment->payment_reference,
                'order_id' => $this->payment->order_id,
                'customer_id' => $this->payment->customer_id,
                'amount' => $this->payment->amount,
                'currency' => $this->payment->currency,
                'payment_method' => $this->payment->payment_method,
                'status' => $this->payment->status,
                'net_amount' => $this->payment->net_amount,
                'gateway_fee' => $this->payment->gateway_fee,
                'platform_fee' => $this->payment->platform_fee,
                'completed_at' => $this->payment->completed_at,
            ],
            'order' => $this->orderData,
            'event_type' => 'payment.completed',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'payment.completed';
    }
}
