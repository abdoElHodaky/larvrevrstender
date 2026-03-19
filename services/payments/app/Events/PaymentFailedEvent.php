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

class PaymentFailedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public array $failureDetails;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, array $failureDetails = [])
    {
        $this->payment = $payment;
        $this->failureDetails = $failureDetails;
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
                'failed_at' => $this->payment->updated_at,
            ],
            'failure' => [
                'reason' => $this->failureDetails['reason'] ?? 'Unknown error',
                'error_code' => $this->failureDetails['error_code'] ?? null,
                'gateway_error' => $this->failureDetails['gateway_error'] ?? null,
                'gateway_transaction_id' => $this->failureDetails['gateway_transaction_id'] ?? null,
                'retry_possible' => $this->failureDetails['retry_possible'] ?? true,
                'retry_count' => $this->failureDetails['retry_count'] ?? 0,
                'failed_at' => $this->failureDetails['failed_at'] ?? now()->toISOString(),
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
            'next_steps' => [
                'retry_available' => $this->failureDetails['retry_possible'] ?? true,
                'alternative_methods' => $this->failureDetails['alternative_methods'] ?? [],
                'support_contact' => $this->failureDetails['support_contact'] ?? null,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'payment.failed';
    }
}
