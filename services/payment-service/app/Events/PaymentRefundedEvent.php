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

class PaymentRefundedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public array $refundDetails;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, array $refundDetails = [])
    {
        $this->payment = $payment;
        $this->refundDetails = $refundDetails;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("payment.{$this->payment->id}"),
            new PrivateChannel("user.{$this->payment->customer_id}.payments"),
            new PrivateChannel("merchant.{$this->payment->merchant_id}.payments"),
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
                'original_amount' => $this->payment->amount,
                'currency' => $this->payment->currency,
                'status' => $this->payment->status,
                'payment_method' => $this->payment->payment_method,
                'customer_id' => $this->payment->customer_id,
                'merchant_id' => $this->payment->merchant_id,
            ],
            'refund' => [
                'refund_id' => $this->refundDetails['refund_id'] ?? null,
                'refund_reference' => $this->refundDetails['refund_reference'] ?? null,
                'refund_amount' => $this->refundDetails['refund_amount'] ?? $this->payment->amount,
                'refund_reason' => $this->refundDetails['refund_reason'] ?? 'Customer request',
                'refund_type' => $this->refundDetails['refund_type'] ?? 'full', // full, partial
                'gateway_refund_id' => $this->refundDetails['gateway_refund_id'] ?? null,
                'processing_fee_refunded' => $this->refundDetails['processing_fee_refunded'] ?? false,
                'refunded_at' => $this->refundDetails['refunded_at'] ?? now()->toISOString(),
                'estimated_arrival' => $this->refundDetails['estimated_arrival'] ?? null,
            ],
            'invoice' => [
                'id' => $this->payment->invoice_id ?? null,
                'invoice_number' => $this->payment->invoice->invoice_number ?? null,
            ],
            // Sanitized customer/merchant info (no sensitive data)
            'customer' => [
                'id' => $this->payment->customer_id,
            ],
            'merchant' => [
                'id' => $this->payment->merchant_id,
            ],
            'refund_details' => [
                'refund_method' => $this->refundDetails['refund_method'] ?? 'original_payment_method',
                'business_days_to_process' => $this->refundDetails['business_days_to_process'] ?? '3-5',
                'refund_policy_url' => $this->refundDetails['refund_policy_url'] ?? null,
            ],
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
