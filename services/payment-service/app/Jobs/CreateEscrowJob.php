<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;

/**
 * Create Escrow Job with Laravel Fuse Circuit Breaker Protection
 * 
 * This job handles escrow account creation with built-in circuit breaker
 * protection via Laravel Fuse integration. Critical for secure transactions
 * in the reverse tender platform.
 */
class CreateEscrowJob extends BaseQueueJob
{
    /**
     * Transaction amount in cents
     */
    public int $amount;

    /**
     * Currency code
     */
    public string $currency;

    /**
     * Buyer ID
     */
    public string $buyerId;

    /**
     * Seller ID
     */
    public string $sellerId;

    /**
     * Tender/Order reference
     */
    public string $tenderReference;

    /**
     * Escrow terms and conditions
     */
    public array $terms;

    /**
     * Additional escrow metadata
     */
    public array $metadata;

    /**
     * Create a new job instance
     *
     * @param int $amount
     * @param string $currency
     * @param string $buyerId
     * @param string $sellerId
     * @param string $tenderReference
     * @param array $terms
     * @param array $metadata
     */
    public function __construct(
        int $amount,
        string $currency,
        string $buyerId,
        string $sellerId,
        string $tenderReference,
        array $terms = [],
        array $metadata = []
    ) {
        // Initialize with 'escrow' service for circuit breaker configuration
        parent::__construct('escrow');
        
        $this->amount = $amount;
        $this->currency = $currency;
        $this->buyerId = $buyerId;
        $this->sellerId = $sellerId;
        $this->tenderReference = $tenderReference;
        $this->terms = $terms;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job with circuit breaker protection
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info('Processing escrow creation job', [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'tender_reference' => $this->tenderReference,
            'service' => $this->getServiceName()
        ]);

        try {
            // Create the escrow account
            $escrowResult = $this->createEscrow();
            
            Log::info('Escrow created successfully', [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'buyer_id' => $this->buyerId,
                'seller_id' => $this->sellerId,
                'tender_reference' => $this->tenderReference,
                'escrow_id' => $escrowResult['escrow_id'] ?? null,
                'service' => $this->getServiceName()
            ]);

            // Could dispatch follow-up jobs here:
            // - Send escrow confirmation to buyer
            // - Send escrow notification to seller
            // - Update tender status to "escrow_created"

        } catch (\Exception $e) {
            Log::error('Failed to create escrow', [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'buyer_id' => $this->buyerId,
                'seller_id' => $this->sellerId,
                'tender_reference' => $this->tenderReference,
                'service' => $this->getServiceName(),
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger circuit breaker failure handling
            throw $e;
        }
    }

    /**
     * Create the escrow account
     *
     * @return array Escrow creation result
     * @throws \Exception
     */
    private function createEscrow(): array
    {
        // Example implementation - replace with actual escrow service
        // This could be Escrow.com, PayPal, or custom escrow implementation
        
        $escrowData = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'reference' => $this->tenderReference,
            'terms' => array_merge([
                'release_condition' => 'delivery_confirmation',
                'dispute_resolution' => 'platform_mediation',
                'timeout_days' => 30
            ], $this->terms),
            'metadata' => array_merge($this->metadata, [
                'created_at' => now()->toISOString(),
                'platform' => 'reverse_tender'
            ])
        ];

        // Example: Custom escrow service
        // $escrowService = app(EscrowService::class);
        // $escrow = $escrowService->createEscrow($escrowData);
        
        // For now, simulate successful escrow creation
        return [
            'escrow_id' => 'escrow_' . uniqid(),
            'status' => 'created',
            'amount' => $this->amount,
            'currency' => $this->currency,
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addDays(30)->toISOString()
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
        Log::critical('Escrow creation job failed permanently', [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
            'tender_reference' => $this->tenderReference,
            'service' => $this->getServiceName(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Critical: Escrow failure requires immediate attention
        // Could trigger:
        // - Alert to finance team
        // - Buyer/seller notification
        // - Tender status update to "escrow_failed"
        // - Refund initiation if payment was already captured
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return array_merge(parent::tags(), [
            'buyer:' . $this->buyerId,
            'seller:' . $this->sellerId,
            'tender:' . $this->tenderReference,
            'amount:' . $this->amount,
            'currency:' . $this->currency
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Escrow jobs use moderate backoff.
     *
     * @return int
     */
    public function backoff(): int
    {
        // Moderate backoff for escrow jobs: 10, 20, 40, 80 seconds...
        return min(10 * pow(2, $this->attempts()), 300); // Cap at 5 minutes
    }
}
