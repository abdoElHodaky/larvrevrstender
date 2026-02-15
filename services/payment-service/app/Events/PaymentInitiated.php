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
 * Payment Initiated Event
 * 
 * Fired when a payment record is created in the database during saga execution.
 * This event signals that payment processing has begun.
 */
class PaymentInitiated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->payment->customer_id}.payments"),
            new PrivateChannel("payment.{$this->payment->id}.status"),
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
                'created_at' => $this->payment->created_at,
            ],
            'event_type' => 'payment.initiated',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'payment.initiated';
    }
}
