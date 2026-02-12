<?php

namespace App\Jobs;

use App\Services\AuctionCompletionService;
use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;

/**
 * Complete Expired Auctions Job with Laravel Fuse Circuit Breaker Protection
 *
 * Scheduled job that runs periodically to complete expired auctions
 * and trigger the order creation workflow. Protected against auction service
 * outages and prevents queue worker starvation during high-load periods.
 */
class CompleteExpiredAuctionsJob extends BaseQueueJob
{

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        // Configure circuit breaker for auction completion service
        $this->configureCircuitBreaker([
            'service_name' => 'auction_completion',
            'failure_threshold' => 40, // 40% failure rate triggers circuit breaker
            'timeout' => 45, // 45 seconds timeout for auction processing
            'recovery_timeout' => 120, // 2 minutes before attempting recovery
            'tags' => [
                'service' => 'auction-service',
                'job_type' => 'scheduled_completion',
                'priority' => 'high'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(AuctionCompletionService $completionService): void
    {
        Log::info('Starting expired auctions completion job with circuit breaker protection', [
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'auction_completion'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() use ($completionService) {
            $results = $completionService->processExpiredAuctions();

            Log::info('Expired auctions completion job completed successfully', [
                'processed' => $results['processed'],
                'completed' => $results['completed'],
                'failed' => $results['failed'],
                'errors' => $results['errors'],
                'job_id' => $this->job?->getJobId()
            ]);

            // If there were failures, log them but don't fail the job
            if ($results['failed'] > 0) {
                Log::warning("Some auctions failed to complete", [
                    'failed_count' => $results['failed'],
                    'errors' => $results['errors'],
                    'job_id' => $this->job?->getJobId()
                ]);
            }

            return $results;
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Expired auctions completion job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
