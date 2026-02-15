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

class PaymentProcessedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public array $processingDetails;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, array $processingDetails = [])
    {
        $this->payment = $payment;
        $this->processingDetails = $processingDetails;
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
                'amount' => $this->payment->amount,
                'currency' => $this->payment->currency,
                'status' => $this->payment->status,
                'payment_method' => $this->payment->payment_method,
                'customer_id' => $this->payment->customer_id,
                'merchant_id' => $this->payment->merchant_id,
                'processed_at' => $this->payment->updated_at,
            ],
            'processing' => [
                'gateway' => $this->processingDetails['gateway'] ?? 'unknown',
                'gateway_transaction_id' => $this->processingDetails['gateway_transaction_id'] ?? null,
                'processing_fee' => $this->processingDetails['processing_fee'] ?? null,
                'net_amount' => $this->processingDetails['net_amount'] ?? $this->payment->amount,
                'processed_at' => $this->processingDetails['processed_at'] ?? now()->toISOString(),
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
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'payment.processed';
    }
}
