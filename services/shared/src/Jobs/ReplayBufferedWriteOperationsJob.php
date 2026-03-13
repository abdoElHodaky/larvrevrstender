<?php

namespace Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shared\Services\WriteOperationBufferService;

/**
 * Replay Buffered Write Operations Job
 * 
 * This job handles the replay of buffered write operations when the database
 * recovers from a failover scenario. It processes operations in batches to
 * avoid overwhelming the recovered database.
 */
class ReplayBufferedWriteOperationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param string $serviceName The service name to replay operations for
     * @param int|null $batchSize Number of operations to replay in this batch
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly ?int $batchSize = null
    ) {
        // Set queue based on service priority
        $this->onQueue('write-operation-replay');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info("Starting replay of buffered write operations", [
            'service' => $this->serviceName,
            'batch_size' => $this->batchSize,
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            $bufferService = new WriteOperationBufferService($this->serviceName);
            
            // Check if there are operations to replay
            $bufferStatus = $bufferService->getBufferStatus();
            if ($bufferStatus['total_buffered'] === 0) {
                Log::info("No buffered operations to replay", [
                    'service' => $this->serviceName,
                ]);
                return;
            }

            Log::info("Found buffered operations to replay", [
                'service' => $this->serviceName,
                'total_buffered' => $bufferStatus['total_buffered'],
                'critical_operations' => $bufferStatus['critical_operations'],
                'regular_operations' => $bufferStatus['regular_operations'],
            ]);

            // Clear expired operations first
            $expiredCount = $bufferService->clearExpiredOperations();
            if ($expiredCount > 0) {
                Log::warning("Cleared expired operations before replay", [
                    'service' => $this->serviceName,
                    'expired_count' => $expiredCount,
                ]);
            }

            // Replay operations in batches
            $results = $bufferService->replayBufferedOperations($this->batchSize);
            
            // Analyze results
            $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
            $failedCount = count(array_filter($results, fn($r) => $r['status'] === 'failed'));
            $expiredCount = count(array_filter($results, fn($r) => $r['status'] === 'expired'));

            Log::info("Completed replay batch", [
                'service' => $this->serviceName,
                'total_processed' => count($results),
                'successful' => $successCount,
                'failed' => $failedCount,
                'expired' => $expiredCount,
                'job_id' => $this->job->getJobId(),
            ]);

            // If there were failures, log details for debugging
            if ($failedCount > 0) {
                $failedOperations = array_filter($results, fn($r) => $r['status'] === 'failed');
                Log::error("Some write operations failed during replay", [
                    'service' => $this->serviceName,
                    'failed_count' => $failedCount,
                    'failed_operations' => array_map(fn($op) => [
                        'buffer_id' => $op['buffer_id'],
                        'error' => $op['error'] ?? 'Unknown error',
                    ], $failedOperations),
                ]);
            }

            // Check if more operations need to be processed
            $updatedStatus = $bufferService->getBufferStatus();
            if ($updatedStatus['total_buffered'] > 0) {
                Log::info("More operations remain in buffer, scheduling next batch", [
                    'service' => $this->serviceName,
                    'remaining_operations' => $updatedStatus['total_buffered'],
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Failed to replay buffered write operations", [
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Write operation replay job failed permanently", [
            'service' => $this->serviceName,
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'job_id' => $this->job->getJobId(),
        ]);

        // You might want to send an alert here for manual intervention
        // or implement a dead letter queue for failed replay operations
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return int
     */
    public function backoff(): int
    {
        // Exponential backoff: 30s, 60s, 120s
        return 30 * pow(2, $this->attempts() - 1);
    }

    /**
     * Determine if the job should be retried based on the exception.
     *
     * @param \Throwable $exception
     * @return bool
     */
    public function retryUntil(): \DateTime
    {
        // Retry for up to 1 hour
        return now()->addHour();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'write-operation-replay',
            'service:' . $this->serviceName,
            'database-failover',
        ];
    }
}
