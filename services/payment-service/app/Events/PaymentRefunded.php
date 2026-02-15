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
 * Payment Refunded Event
 * 
 * Fired when a payment is refunded during saga compensation or manual refund.
 * This event signals that funds have been returned to the customer.
 */
class PaymentRefunded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public string $refundType;
    public float $refundAmount;
    public ?string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, string $refundType, float $refundAmount, ?string $reason = null)
    {
        $this->payment = $payment;
        $this->refundType = $refundType;
        $this->refundAmount = $refundAmount;
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
                'refunded_amount' => $this->payment->refunded_amount,
                'refunded_at' => $this->payment->refunded_at,
            ],
            'refund' => [
                'type' => $this->refundType,
                'amount' => $this->refundAmount,
                'reason' => $this->reason,
                'refunded_at' => now()->toISOString(),
            ],
            'event_type' => 'payment.refunded',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'payment.refunded';
    }
}
