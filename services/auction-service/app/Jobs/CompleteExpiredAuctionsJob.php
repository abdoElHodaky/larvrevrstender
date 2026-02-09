<?php

namespace App\Jobs;

use App\Services\AuctionCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Complete Expired Auctions Job
 *
 * Scheduled job that runs periodically to complete expired auctions
 * and trigger the order creation workflow.
 */
class CompleteExpiredAuctionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        // Job can be created without parameters
    }

    /**
     * Execute the job.
     */
    public function handle(AuctionCompletionService $completionService): void
    {
        Log::info('Starting expired auctions completion job');

        try {
            $results = $completionService->processExpiredAuctions();

            Log::info('Expired auctions completion job completed', [
                'processed' => $results['processed'],
                'completed' => $results['completed'],
                'failed' => $results['failed'],
                'errors' => $results['errors']
            ]);

            // If there were failures, log them but don't fail the job
            if ($results['failed'] > 0) {
                Log::warning("Some auctions failed to complete", [
                    'failed_count' => $results['failed'],
                    'errors' => $results['errors']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Expired auctions completion job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
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
