<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Service for handling failed workflow activities and retry management
 */
class WorkflowDeadLetterQueue
{
    /**
     * Add failed activity to dead letter queue
     */
    public function addFailedActivity(
        string $workflowId,
        string $activityName,
        array $activityData,
        string $errorMessage,
        array $errorDetails = [],
        int $attemptNumber = 1
    ): string {
        try {
            $failureId = uniqid('failure_');
            $dlqKey = "dlq.activity.{$failureId}";

            $failureData = [
                'failure_id' => $failureId,
                'workflow_id' => $workflowId,
                'activity_name' => $activityName,
                'activity_data' => $activityData,
                'error_message' => $errorMessage,
                'error_details' => $errorDetails,
                'attempt_number' => $attemptNumber,
                'failed_at' => now()->toISOString(),
                'status' => 'pending_retry',
                'next_retry_at' => $this->calculateNextRetryTime($attemptNumber),
                'max_retries' => $this->getMaxRetries($activityName),
                'retry_strategy' => $this->getRetryStrategy($activityName),
            ];

            // Store in cache with extended TTL
            Cache::put($dlqKey, $failureData, now()->addDays(7));

            // Add to retry queue index
            $this->addToRetryIndex($failureId, $failureData['next_retry_at']);

            Log::warning('Activity added to dead letter queue', [
                'failure_id' => $failureId,
                'workflow_id' => $workflowId,
                'activity_name' => $activityName,
                'attempt_number' => $attemptNumber,
                'next_retry_at' => $failureData['next_retry_at'],
            ]);

            return $failureId;
        } catch (\Exception $e) {
            Log::error('Failed to add activity to dead letter queue', [
                'workflow_id' => $workflowId,
                'activity_name' => $activityName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process retry queue
     */
    public function processRetryQueue(): array
    {
        $processed = [];
        $retryIndex = Cache::get('dlq.retry_index', []);
        $now = now();

        foreach ($retryIndex as $failureId => $retryTime) {
            if ($now->gte($retryTime)) {
                $result = $this->retryFailedActivity($failureId);
                $processed[] = [
                    'failure_id' => $failureId,
                    'result' => $result,
                ];

                // Remove from retry index
                unset($retryIndex[$failureId]);
            }
        }

        // Update retry index
        Cache::put('dlq.retry_index', $retryIndex, now()->addDays(7));

        return $processed;
    }

    /**
     * Retry a failed activity
     */
    public function retryFailedActivity(string $failureId): array
    {
        try {
            $dlqKey = "dlq.activity.{$failureId}";
            $failureData = Cache::get($dlqKey);

            if (!$failureData) {
                throw new \Exception('Failed activity not found in DLQ');
            }

            if ($failureData['status'] !== 'pending_retry') {
                throw new \Exception('Activity is not in pending retry status');
            }

            // Check if max retries exceeded
            if ($failureData['attempt_number'] >= $failureData['max_retries']) {
                return $this->moveToManualIntervention($failureId, $failureData);
            }

            // Attempt retry
            $retryResult = $this->executeActivityRetry($failureData);

            if ($retryResult['success']) {
                // Mark as resolved
                $failureData['status'] = 'resolved';
                $failureData['resolved_at'] = now()->toISOString();
                $failureData['resolution_result'] = $retryResult;
                Cache::put($dlqKey, $failureData, now()->addDays(7));

                Log::info('Failed activity successfully retried', [
                    'failure_id' => $failureId,
                    'workflow_id' => $failureData['workflow_id'],
                    'activity_name' => $failureData['activity_name'],
                    'attempt_number' => $failureData['attempt_number'],
                ]);

                return [
                    'success' => true,
                    'status' => 'resolved',
                    'result' => $retryResult,
                ];
            } else {
                // Increment attempt and schedule next retry
                $failureData['attempt_number']++;
                $failureData['last_retry_at'] = now()->toISOString();
                $failureData['last_retry_error'] = $retryResult['error'] ?? 'Unknown error';
                $failureData['next_retry_at'] = $this->calculateNextRetryTime($failureData['attempt_number']);

                Cache::put($dlqKey, $failureData, now()->addDays(7));
                $this->addToRetryIndex($failureId, $failureData['next_retry_at']);

                Log::warning('Failed activity retry unsuccessful', [
                    'failure_id' => $failureId,
                    'workflow_id' => $failureData['workflow_id'],
                    'activity_name' => $failureData['activity_name'],
                    'attempt_number' => $failureData['attempt_number'],
                    'next_retry_at' => $failureData['next_retry_at'],
                ]);

                return [
                    'success' => false,
                    'status' => 'retry_scheduled',
                    'next_retry_at' => $failureData['next_retry_at'],
                    'error' => $retryResult['error'] ?? 'Retry failed',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to retry activity', [
                'failure_id' => $failureId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Move failed activity to manual intervention
     */
    public function moveToManualIntervention(string $failureId, array $failureData): array
    {
        try {
            $failureData['status'] = 'manual_intervention_required';
            $failureData['moved_to_manual_at'] = now()->toISOString();

            $dlqKey = "dlq.activity.{$failureId}";
            Cache::put($dlqKey, $failureData, now()->addDays(30)); // Extended TTL for manual intervention

            // Add to manual intervention queue
            $this->addToManualInterventionQueue($failureId, $failureData);

            Log::error('Failed activity moved to manual intervention', [
                'failure_id' => $failureId,
                'workflow_id' => $failureData['workflow_id'],
                'activity_name' => $failureData['activity_name'],
                'total_attempts' => $failureData['attempt_number'],
            ]);

            return [
                'success' => false,
                'status' => 'manual_intervention_required',
                'message' => 'Max retries exceeded, manual intervention required',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to move activity to manual intervention', [
                'failure_id' => $failureId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get failed activities requiring manual intervention
     */
    public function getManualInterventionQueue(): array
    {
        $queue = Cache::get('dlq.manual_intervention_queue', []);
        $activities = [];

        foreach ($queue as $failureId => $queuedAt) {
            $dlqKey = "dlq.activity.{$failureId}";
            $failureData = Cache::get($dlqKey);

            if ($failureData && $failureData['status'] === 'manual_intervention_required') {
                $activities[] = $failureData;
            }
        }

        return $activities;
    }

    /**
     * Resolve manual intervention
     */
    public function resolveManualIntervention(
        string $failureId,
        array $resolutionData,
        bool $success = true
    ): bool {
        try {
            $dlqKey = "dlq.activity.{$failureId}";
            $failureData = Cache::get($dlqKey);

            if (!$failureData) {
                throw new \Exception('Failed activity not found');
            }

            $failureData['status'] = $success ? 'manually_resolved' : 'manually_failed';
            $failureData['manual_resolution'] = $resolutionData;
            $failureData['resolved_at'] = now()->toISOString();
            $failureData['resolved_by'] = auth()->user()->id ?? 'system';

            Cache::put($dlqKey, $failureData, now()->addDays(30));

            // Remove from manual intervention queue
            $this->removeFromManualInterventionQueue($failureId);

            Log::info('Manual intervention resolved', [
                'failure_id' => $failureId,
                'workflow_id' => $failureData['workflow_id'],
                'success' => $success,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to resolve manual intervention', [
                'failure_id' => $failureId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get DLQ statistics
     */
    public function getStatistics(): array
    {
        $retryIndex = Cache::get('dlq.retry_index', []);
        $manualQueue = Cache::get('dlq.manual_intervention_queue', []);

        return [
            'pending_retries' => count($retryIndex),
            'manual_interventions' => count($manualQueue),
            'total_failures_today' => $this->getFailureCountForDate(now()->format('Y-m-d')),
            'resolution_rate' => $this->calculateResolutionRate(),
        ];
    }

    /**
     * Calculate next retry time using exponential backoff
     */
    private function calculateNextRetryTime(int $attemptNumber): string
    {
        // Exponential backoff: 2^attempt minutes, max 60 minutes
        $delayMinutes = min(pow(2, $attemptNumber), 60);
        return now()->addMinutes($delayMinutes)->toISOString();
    }

    /**
     * Get max retries for activity type
     */
    private function getMaxRetries(string $activityName): int
    {
        $retryConfig = [
            'ProcessPaymentActivity' => 5,
            'ReserveInventoryActivity' => 3,
            'ScheduleShippingActivity' => 3,
            'RefundPaymentActivity' => 5,
            'ReleaseInventoryActivity' => 3,
            'CancelShippingActivity' => 3,
        ];

        foreach ($retryConfig as $activity => $maxRetries) {
            if (str_contains($activityName, $activity)) {
                return $maxRetries;
            }
        }

        return 3; // Default
    }

    /**
     * Get retry strategy for activity type
     */
    private function getRetryStrategy(string $activityName): string
    {
        if (str_contains($activityName, 'Payment')) {
            return 'exponential_backoff_with_jitter';
        }

        return 'exponential_backoff';
    }

    /**
     * Execute activity retry
     */
    private function executeActivityRetry(array $failureData): array
    {
        // This is a placeholder implementation
        // In a real implementation, you would:
        // 1. Recreate the activity context
        // 2. Execute the activity with the original data
        // 3. Return the result

        // For now, we'll simulate a retry attempt
        $simulatedSuccess = rand(1, 100) <= 30; // 30% success rate for simulation

        if ($simulatedSuccess) {
            return [
                'success' => true,
                'result' => ['simulated' => true, 'retry_successful' => true],
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Simulated retry failure',
            ];
        }
    }

    /**
     * Add to retry index
     */
    private function addToRetryIndex(string $failureId, string $retryTime): void
    {
        $retryIndex = Cache::get('dlq.retry_index', []);
        $retryIndex[$failureId] = $retryTime;
        Cache::put('dlq.retry_index', $retryIndex, now()->addDays(7));
    }

    /**
     * Add to manual intervention queue
     */
    private function addToManualInterventionQueue(string $failureId, array $failureData): void
    {
        $queue = Cache::get('dlq.manual_intervention_queue', []);
        $queue[$failureId] = now()->toISOString();
        Cache::put('dlq.manual_intervention_queue', $queue, now()->addDays(30));
    }

    /**
     * Remove from manual intervention queue
     */
    private function removeFromManualInterventionQueue(string $failureId): void
    {
        $queue = Cache::get('dlq.manual_intervention_queue', []);
        unset($queue[$failureId]);
        Cache::put('dlq.manual_intervention_queue', $queue, now()->addDays(30));
    }

    /**
     * Get failure count for specific date
     */
    private function getFailureCountForDate(string $date): int
    {
        return Cache::get("dlq.failures.count.{$date}", 0);
    }

    /**
     * Calculate resolution rate
     */
    private function calculateResolutionRate(): float
    {
        $totalFailures = Cache::get('dlq.total_failures', 0);
        $resolvedFailures = Cache::get('dlq.resolved_failures', 0);

        if ($totalFailures === 0) {
            return 100.0;
        }

        return round(($resolvedFailures / $totalFailures) * 100, 2);
    }
}
