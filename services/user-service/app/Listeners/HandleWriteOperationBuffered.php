<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationBufferedEvent;
use Shared\Jobs\ReplayBufferedWriteOperationsJob;
use Shared\Services\DatabaseFailoverAlertManager;

class HandleWriteOperationBuffered implements ShouldQueue
{
    use InteractsWithQueue;

    private DatabaseFailoverAlertManager $alertManager;

    public function __construct()
    {
        $this->alertManager = new DatabaseFailoverAlertManager();
    }

    /**
     * Handle the write operation buffered event for User Service.
     */
    public function handle(WriteOperationBufferedEvent $event): void
    {
        Log::channel('write-operations')->info('User Service: Write operation buffered', [
            'service' => 'user-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'buffered_at' => $event->bufferedAt,
            'correlation_id' => $event->correlationId,
        ]);

        // User service handles authentication and profile operations
        $this->handleUserWriteBuffering($event);
    }

    /**
     * Handle user-specific write operation buffering.
     */
    private function handleUserWriteBuffering(WriteOperationBufferedEvent $event): void
    {
        // Update metrics for buffered operations
        cache()->increment('user_buffered_operations_count');
        cache()->put('user_last_buffered_operation', now(), 3600);

        // Track operation type for user experience monitoring
        $operationType = $event->operationType;
        cache()->increment("user_buffered_operations_{$operationType}");

        // Log for monitoring dashboard
        Log::info('User Service: Write operation buffered for replay', [
            'service' => 'user-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'buffer_size' => cache()->get('user_buffered_operations_count', 0),
            'user_impact' => $this->getUserImpactLevel($operationType),
        ]);

        // Alert for user authentication operations - these affect user experience
        $bufferSize = cache()->get('user_buffered_operations_count', 0);
        if ($bufferSize > 30) {
            Log::warning('User Service: High number of buffered write operations', [
                'service' => 'user-service',
                'buffer_size' => $bufferSize,
                'alert' => 'high_buffer_size',
                'user_impact' => 'Authentication and profile updates may be delayed',
                'business_impact' => 'User experience degradation',
            ]);

            // Send alert for high buffer size affecting user experience
            $this->sendUserExperienceAlert($bufferSize, $operationType);
        }

        // Special handling for critical authentication operations
        if ($this->isCriticalAuthOperation($operationType)) {
            Log::warning('User Service: Critical authentication operation buffered', [
                'service' => 'user-service',
                'operation_type' => $operationType,
                'impact' => 'User authentication may be affected',
                'priority' => 'high',
            ]);
        }

        // Determine priority based on operation type
        $priority = $this->getOperationPriority($operationType);
        $delay = $this->getReplayDelay($priority);
        $batchSize = $this->getBatchSize($priority);

        // Schedule replay job with appropriate priority
        ReplayBufferedWriteOperationsJob::dispatch('user-service', $batchSize)
            ->delay(now()->addMinutes($delay))
            ->onQueue('write-operation-replay');

        Log::info('User Service: Scheduled write operation replay job', [
            'service' => 'user-service',
            'operation_type' => $operationType,
            'priority' => $priority,
            'delay_minutes' => $delay,
            'batch_size' => $batchSize,
            'user_impact' => $this->getUserImpactLevel($operationType),
        ]);

        // Track replay job scheduling for monitoring
        cache()->put('user_last_replay_job_scheduled', now()->toISOString(), 3600);
        cache()->increment('user_replay_jobs_scheduled');
    }

    /**
     * Get operation priority based on type.
     */
    private function getOperationPriority(string $operationType): string
    {
        $priorityMap = [
            'user_registration' => 'critical',
            'password_change' => 'critical',
            'profile_update' => 'high',
            'email_verification' => 'high',
            'account_verification' => 'high',
            'preference_update' => 'medium',
            'session_management' => 'low',
        ];

        return $priorityMap[$operationType] ?? 'medium';
    }

    /**
     * Get replay delay based on priority.
     */
    private function getReplayDelay(string $priority): int
    {
        $delayMap = [
            'critical' => 1, // 1 minute for critical auth operations
            'high' => 3,     // 3 minutes for high priority
            'medium' => 7,   // 7 minutes for medium priority
            'low' => 15,     // 15 minutes for low priority
        ];

        return $delayMap[$priority] ?? 7;
    }

    /**
     * Get batch size based on priority.
     */
    private function getBatchSize(string $priority): int
    {
        $batchSizeMap = [
            'critical' => 15, // Small batches for critical auth operations
            'high' => 30,     // Medium batches for high priority
            'medium' => 60,   // Larger batches for medium priority
            'low' => 120,     // Large batches for low priority
        ];

        return $batchSizeMap[$priority] ?? 60;
    }

    /**
     * Check if operation is critical for authentication.
     */
    private function isCriticalAuthOperation(string $operationType): bool
    {
        $criticalOperations = [
            'user_registration',
            'password_change',
            'email_verification',
            'account_verification'
        ];

        return in_array($operationType, $criticalOperations);
    }

    /**
     * Get user impact level for operation type.
     */
    private function getUserImpactLevel(string $operationType): string
    {
        $impactMap = [
            'user_registration' => 'high',
            'password_change' => 'high',
            'email_verification' => 'high',
            'account_verification' => 'high',
            'profile_update' => 'medium',
            'preference_update' => 'low',
            'session_management' => 'low',
        ];

        return $impactMap[$operationType] ?? 'medium';
    }

    /**
     * Send alert for high buffer size affecting user experience.
     */
    private function sendUserExperienceAlert(int $bufferSize, string $operationType): void
    {
        try {
            // Create alert data for user experience impact
            $alertData = [
                'alert_id' => 'user_buffer_high_' . time(),
                'event_type' => 'high_user_buffer_size',
                'title' => '⚠️ User Service: High Write Operation Buffer Size',
                'description' => "User service has {$bufferSize} buffered write operations. This may cause delays in user authentication, registration, and profile updates.",
                'severity' => 'medium',
                'service' => 'user-service',
                'connection' => 'user-database',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'buffer_size' => $bufferSize,
                    'operation_type' => $operationType,
                    'user_impact' => 'Authentication and profile operations may be delayed',
                    'business_impact' => 'User experience degradation',
                    'recommended_action' => 'Monitor user complaints and consider manual intervention if buffer continues growing'
                ]
            ];

            Log::warning('User Service: Sending user experience alert', $alertData);

        } catch (\Exception $e) {
            Log::error('User Service: Failed to send user experience alert', [
                'error' => $e->getMessage(),
                'buffer_size' => $bufferSize,
                'operation_type' => $operationType,
            ]);
        }
    }
}
