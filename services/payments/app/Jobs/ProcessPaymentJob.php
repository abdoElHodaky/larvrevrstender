<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;

/**
 * Process Payment Job with Laravel Fuse Circuit Breaker Protection
 * 
 * This job handles payment processing with built-in circuit breaker protection
 * via Laravel Fuse integration. Critical for financial operations - protects
 * against payment gateway outages and prevents queue worker starvation.
 */
class ProcessPaymentJob extends BaseQueueJob
{
    /**
     * Payment amount in cents
     */
    public int $amount;

    /**
     * Currency code (USD, EUR, etc.)
     */
    public string $currency;

    /**
     * Customer ID
     */
    public string $customerId;

    /**
     * Payment method ID
     */
    public string $paymentMethodId;

    /**
     * Order/transaction reference
     */
    public string $orderReference;

    /**
     * Additional payment metadata
     */
    public array $metadata;

    /**
     * Create a new job instance
     *
     * @param int $amount Payment amount in cents
     * @param string $currency Currency code
     * @param string $customerId Customer identifier
     * @param string $paymentMethodId Payment method identifier
     * @param string $orderReference Order/transaction reference
     * @param array $metadata Additional payment data
     */
    public function __construct(
        int $amount,
        string $currency,
        string $customerId,
        string $paymentMethodId,
        string $orderReference,
        array $metadata = []
    ) {
        // Initialize with 'payment' service for circuit breaker configuration
        // Payment service has aggressive circuit breaker settings (30% threshold, 15s timeout)
        parent::__construct('payment');
        
        $this->amount = $amount;
        $this->currency = $currency;
        $this->customerId = $customerId;
        $this->paymentMethodId = $paymentMethodId;
        $this->orderReference = $orderReference;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job with circuit breaker protection
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Processing payment job', [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer_id' => $this->customerId,
            'order_reference' => $this->orderReference,
            'service' => $this->getServiceName()
        ]);

        try {
            // Process the payment
            $paymentResult = $this->processPayment();
            
            Log::info('Payment processed successfully', [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'customer_id' => $this->customerId,
                'order_reference' => $this->orderReference,
                'payment_id' => $paymentResult['payment_id'] ?? null,
                'service' => $this->getServiceName()
            ]);

            // Could dispatch follow-up jobs here:
            // - Send payment confirmation email
            // - Update order status
            // - Trigger fulfillment process

        } catch (\Exception $e) {
            Log::error('Failed to process payment', [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'customer_id' => $this->customerId,
                'order_reference' => $this->orderReference,
                'service' => $this->getServiceName(),
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger circuit breaker failure handling
            throw $e;
        }
    }

    /**
     * Process the payment via payment gateway
     *
     * @return array Payment result
     * @throws \Exception
     */
    private function processPayment(): array
    {
        // Example implementation - replace with actual payment gateway
        // This could be Stripe, PayPal, Square, etc.
        
        // Simulate payment processing
        $paymentData = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer' => $this->customerId,
            'payment_method' => $this->paymentMethodId,
            'metadata' => array_merge($this->metadata, [
                'order_reference' => $this->orderReference,
                'processed_at' => now()->toISOString()
            ])
        ];

        // Example: Stripe payment processing
        // $charge = \Stripe\Charge::create($paymentData);
        
        // For now, simulate successful payment
        return [
            'payment_id' => 'pay_' . uniqid(),
            'status' => 'succeeded',
            'amount' => $this->amount,
            'currency' => $this->currency,
            'processed_at' => now()->toISOString()
        ];
    }

    /**
     * Handle job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function onFailure(\Throwable $exception): void
    {
        Log::critical('Payment job failed permanently', [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer_id' => $this->customerId,
            'order_reference' => $this->orderReference,
            'service' => $this->getServiceName(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Critical: Payment failure requires immediate attention
        // Could trigger:
        // - Alert to payment team
        // - Customer notification
        // - Order status update to "payment_failed"
        // - Refund initiation if partial charge occurred
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return array_merge(parent::tags(), [
            'customer:' . $this->customerId,
            'order:' . $this->orderReference,
            'amount:' . $this->amount,
            'currency:' . $this->currency
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Payment jobs use shorter backoff for faster recovery.
     *
     * @return int
     */
    public function backoff(): int
    {
        // Shorter backoff for payment jobs: 5, 10, 20, 40 seconds...
        return min(5 * pow(2, $this->attempts()), 120); // Cap at 2 minutes
    }
}
