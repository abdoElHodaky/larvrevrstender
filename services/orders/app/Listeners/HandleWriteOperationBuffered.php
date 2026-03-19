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
     * Handle the write operation buffered event for Order Service.
     */
    public function handle(WriteOperationBufferedEvent $event): void
    {
        Log::channel('write-operations')->info('Order Service: Write operation buffered', [
            'service' => 'order-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'buffered_at' => $event->bufferedAt,
            'correlation_id' => $event->correlationId,
        ]);

        // Order service handles critical business operations
        $this->handleOrderWriteBuffering($event);
    }

    /**
     * Handle order-specific write operation buffering.
     */
    private function handleOrderWriteBuffering(WriteOperationBufferedEvent $event): void
    {
        // Update metrics for buffered operations
        cache()->increment('order_buffered_operations_count');
        cache()->put('order_last_buffered_operation', now(), 3600);

        // Track operation type for business intelligence
        $operationType = $event->operationType;
        cache()->increment("order_buffered_operations_{$operationType}");

        // Log for monitoring dashboard
        Log::info('Order Service: Write operation buffered for replay', [
            'service' => 'order-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'buffer_size' => cache()->get('order_buffered_operations_count', 0),
        ]);

        // Critical alert for order operations - these are business critical
        $bufferSize = cache()->get('order_buffered_operations_count', 0);
        if ($bufferSize > 50) {
            Log::critical('Order Service: High number of buffered write operations', [
                'service' => 'order-service',
                'buffer_size' => $bufferSize,
                'alert' => 'critical_buffer_size',
                'business_impact' => 'Orders may be delayed',
            ]);

            // Send alert for high buffer size
            $this->sendBufferSizeAlert($bufferSize, $operationType);
        }

        // Determine priority based on operation type
        $priority = $this->getOperationPriority($operationType);
        $delay = $this->getReplayDelay($priority);
        $batchSize = $this->getBatchSize($priority);

        // Schedule replay job with appropriate priority
        ReplayBufferedWriteOperationsJob::dispatch('order-service', $batchSize)
            ->delay(now()->addMinutes($delay))
            ->onQueue('write-operation-replay');

        Log::info('Order Service: Scheduled write operation replay job', [
            'service' => 'order-service',
            'operation_type' => $operationType,
            'priority' => $priority,
            'delay_minutes' => $delay,
            'batch_size' => $batchSize,
        ]);

        // Track replay job scheduling for monitoring
        cache()->put('order_last_replay_job_scheduled', now()->toISOString(), 3600);
        cache()->increment('order_replay_jobs_scheduled');
    }

    /**
     * Get operation priority based on type.
     */
    private function getOperationPriority(string $operationType): string
    {
        $priorityMap = [
            'order_creation' => 'critical',
            'status_update' => 'high',
            'order_cancellation' => 'high',
            'payment_update' => 'critical',
            'shipping_update' => 'medium',
            'order_modification' => 'high',
            'refund_processing' => 'critical',
        ];

        return $priorityMap[$operationType] ?? 'medium';
    }

    /**
     * Get replay delay based on priority.
     */
    private function getReplayDelay(string $priority): int
    {
        $delayMap = [
            'critical' => 1, // 1 minute for critical operations
            'high' => 2,     // 2 minutes for high priority
            'medium' => 5,   // 5 minutes for medium priority
            'low' => 10,     // 10 minutes for low priority
        ];

        return $delayMap[$priority] ?? 5;
    }

    /**
     * Get batch size based on priority.
     */
    private function getBatchSize(string $priority): int
    {
        $batchSizeMap = [
            'critical' => 10, // Small batches for critical operations
            'high' => 25,     // Medium batches for high priority
            'medium' => 50,   // Larger batches for medium priority
            'low' => 100,     // Large batches for low priority
        ];

        return $batchSizeMap[$priority] ?? 50;
    }

    /**
     * Send alert for high buffer size.
     */
    private function sendBufferSizeAlert(int $bufferSize, string $operationType): void
    {
        try {
            // Create a mock database failover event for alerting
            $alertData = [
                'alert_id' => 'order_buffer_high_' . time(),
                'event_type' => 'high_buffer_size',
                'title' => '🚨 Order Service: High Write Operation Buffer Size',
                'description' => "Order service has {$bufferSize} buffered write operations. This may indicate prolonged database issues affecting order processing.",
                'severity' => 'high',
                'service' => 'order-service',
                'connection' => 'order-database',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'buffer_size' => $bufferSize,
                    'operation_type' => $operationType,
                    'business_impact' => 'Orders may be delayed or lost',
                    'recommended_action' => 'Check database connectivity and consider manual intervention'
                ]
            ];

            Log::warning('Order Service: Sending high buffer size alert', $alertData);

        } catch (\Exception $e) {
            Log::error('Order Service: Failed to send buffer size alert', [
                'error' => $e->getMessage(),
                'buffer_size' => $bufferSize,
                'operation_type' => $operationType,
            ]);
        }
    }
}
