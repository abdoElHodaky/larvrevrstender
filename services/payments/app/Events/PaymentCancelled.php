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
 * Payment Cancelled Event
 * 
 * Fired when a payment is cancelled during saga compensation or manual cancellation.
 * This event signals that the payment has been cancelled and will not be processed.
 */
class PaymentCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public string $previousStatus;
    public ?string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, string $previousStatus, ?string $reason = null)
    {
        $this->payment = $payment;
        $this->previousStatus = $previousStatus;
        $this->reason = $reason;
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
                'previous_status' => $this->previousStatus,
            ],
            'cancellation' => [
                'reason' => $this->reason,
                'cancelled_at' => now()->toISOString(),
                'previous_status' => $this->previousStatus,
            ],
            'event_type' => 'payment.cancelled',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'payment.cancelled';
    }
}
