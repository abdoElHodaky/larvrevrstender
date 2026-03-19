<?php

namespace App\Jobs;

use App\Services\WorkflowDeadLetterQueue;
use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;

/**
 * Dead Letter Queue Retry Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Processes failed workflow activities with circuit breaker protection to prevent
 * cascade failures and queue worker starvation during DLQ processing issues.
 */
class ProcessDlqRetry extends BaseQueueJob
{

    public string $failureId;
    public array $retryData;
    public int $tries = 5;
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(string $failureId, array $retryData = [])
    {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        $this->failureId = $failureId;
        $this->retryData = $retryData;
        
        // Set queue based on activity type priority
        $activityType = $retryData['activity_type'] ?? 'unknown';
        $this->onQueue($this->getQueueForActivityType($activityType));
        
        // Configure circuit breaker for DLQ retry processing
        $this->configureCircuitBreaker([
            'service_name' => 'dlq_retry',
            'failure_threshold' => 50, // 50% failure rate triggers circuit breaker
            'timeout' => 60, // 60 seconds timeout for DLQ retry operations
            'recovery_timeout' => 180, // 3 minutes before attempting recovery
            'tags' => [
                'service' => 'order-service',
                'job_type' => 'dlq_retry',
                'activity_type' => $activityType,
                'priority' => 'critical'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(WorkflowDeadLetterQueue $dlqService): void
    {
        Log::info('Processing DLQ retry with circuit breaker protection', [
            'failure_id' => $this->failureId,
            'retry_data' => $this->retryData,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'dlq_retry'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() use ($dlqService) {
            // Attempt to retry the failed activity
            $result = $dlqService->retryFailedActivity($this->failureId);

            if ($result) {
                Log::info('DLQ retry successful', [
                    'failure_id' => $this->failureId,
                    'result' => $result,
                    'job_id' => $this->job?->getJobId(),
                ]);

                // Broadcast success event for monitoring
                broadcast(new \App\Events\Workflow\DlqRetrySuccessful(
                    $this->failureId,
                    $result
                ));

                // Update metrics
                $this->updateRetryMetrics('success');

            } else {
                Log::warning('DLQ retry returned false - activity may not be eligible', [
                    'failure_id' => $this->failureId,
                    'job_id' => $this->job?->getJobId(),
                ]);

                // Check if this needs manual intervention
                $this->checkForManualIntervention($dlqService);
            }

            return $result;
        }, function(\Exception $e) use ($dlqService) {
            // Circuit breaker failure handler
            Log::error('DLQ retry failed with circuit breaker protection', [
                'failure_id' => $this->failureId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            // Update metrics
            $this->updateRetryMetrics('failure');

            // Check if we should move to manual intervention
            $this->handleRetryFailure($dlqService, $e);

            throw $e;
        });
    }

    /**
     * Check if activity needs manual intervention
     */
    private function checkForManualIntervention(WorkflowDeadLetterQueue $dlqService): void
    {
        // Get failure details to check retry count
        $statistics = $dlqService->getStatistics();
        
        // If this was the final retry attempt, it should have been moved to manual intervention
        Log::info('Checking manual intervention status', [
            'failure_id' => $this->failureId,
            'dlq_statistics' => $statistics,
        ]);

        // Broadcast event for monitoring
        broadcast(new \App\Events\Workflow\DlqRetryExhausted(
            $this->failureId,
            $this->retryData
        ));
    }

    /**
     * Handle retry failure
     */
    private function handleRetryFailure(WorkflowDeadLetterQueue $dlqService, \Exception $exception): void
    {
        $attemptNumber = $this->attempts();
        $maxAttempts = $this->tries;

        Log::warning('DLQ retry attempt failed', [
            'failure_id' => $this->failureId,
            'attempt' => $attemptNumber,
            'max_attempts' => $maxAttempts,
            'error' => $exception->getMessage(),
        ]);

        // If this is the final attempt, move to manual intervention
        if ($attemptNumber >= $maxAttempts) {
            try {
                $dlqService->moveToManualIntervention(
                    $this->failureId,
                    "Max retry attempts ({$maxAttempts}) exceeded: " . $exception->getMessage()
                );

                Log::alert('Activity moved to manual intervention after retry exhaustion', [
                    'failure_id' => $this->failureId,
                    'final_error' => $exception->getMessage(),
                    'total_attempts' => $attemptNumber,
                ]);

                // Broadcast critical alert
                broadcast(new \App\Events\Workflow\ManualInterventionRequired(
                    $this->failureId,
                    $this->retryData,
                    $exception->getMessage()
                ));

            } catch (\Exception $moveException) {
                Log::critical('Failed to move activity to manual intervention', [
                    'failure_id' => $this->failureId,
                    'original_error' => $exception->getMessage(),
                    'move_error' => $moveException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update retry metrics
     */
    private function updateRetryMetrics(string $outcome): void
    {
        $activityType = $this->retryData['activity_type'] ?? 'unknown';
        $today = now()->format('Y-m-d');

        try {
            // Update overall retry metrics
            cache()->increment("dlq.metrics.retries.{$outcome}", 1);
            cache()->increment("dlq.metrics.daily.{$today}.retries.{$outcome}", 1);

            // Update activity-specific metrics
            cache()->increment("dlq.metrics.retries.{$activityType}.{$outcome}", 1);
            cache()->increment("dlq.metrics.daily.{$today}.retries.{$activityType}.{$outcome}", 1);

            Log::debug('Updated DLQ retry metrics', [
                'failure_id' => $this->failureId,
                'activity_type' => $activityType,
                'outcome' => $outcome,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to update DLQ retry metrics', [
                'failure_id' => $this->failureId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get queue name based on activity type
     */
    private function getQueueForActivityType(string $activityType): string
    {
        return match ($activityType) {
            'payment' => 'dlq-payment',
            'inventory' => 'dlq-inventory', 
            'shipping' => 'dlq-shipping',
            'compensation' => 'dlq-compensation',
            default => 'dlq-default',
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DLQ retry job failed permanently', [
            'failure_id' => $this->failureId,
            'retry_data' => $this->retryData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Update failure metrics
        $this->updateRetryMetrics('job_failure');

        // Broadcast failure event for monitoring
        broadcast(new \App\Events\Workflow\DlqRetryJobFailed(
            $this->failureId,
            $this->retryData,
            $exception->getMessage()
        ));

        // Try to move to manual intervention as last resort
        try {
            $dlqService = app(WorkflowDeadLetterQueue::class);
            $dlqService->moveToManualIntervention(
                $this->failureId,
                "DLQ retry job failed permanently: " . $exception->getMessage()
            );

            Log::alert('Activity moved to manual intervention after job failure', [
                'failure_id' => $this->failureId,
                'error' => $exception->getMessage(),
            ]);

        } catch (\Exception $moveException) {
            Log::critical('Failed to move activity to manual intervention after job failure', [
                'failure_id' => $this->failureId,
                'original_error' => $exception->getMessage(),
                'move_error' => $moveException->getMessage(),
            ]);
        }
    }
}
