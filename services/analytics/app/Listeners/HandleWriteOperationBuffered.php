<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationBufferedEvent;
use Shared\Jobs\ReplayBufferedWriteOperationsJob;

class HandleWriteOperationBuffered implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the write operation buffered event for Analytics Service.
     */
    public function handle(WriteOperationBufferedEvent $event): void
    {
        Log::channel('write-operations')->info('Analytics Service: Write operation buffered', [
            'service' => 'analytics-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'buffered_at' => $event->bufferedAt,
            'correlation_id' => $event->correlationId,
        ]);

        // Analytics service is primarily read-only, but may have some logging writes
        $this->handleAnalyticsWriteBuffering($event);
    }

    /**
     * Handle analytics-specific write operation buffering.
     */
    private function handleAnalyticsWriteBuffering(WriteOperationBufferedEvent $event): void
    {
        // Update metrics for buffered operations
        cache()->increment('analytics_buffered_operations_count');
        cache()->put('analytics_last_buffered_operation', now(), 3600);

        // Log for monitoring dashboard
        Log::info('Analytics Service: Write operation buffered for replay', [
            'service' => 'analytics-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'buffer_size' => cache()->get('analytics_buffered_operations_count', 0),
        ]);

        // Alert if buffer size is getting large (analytics shouldn't have many writes)
        $bufferSize = cache()->get('analytics_buffered_operations_count', 0);
        if ($bufferSize > 10) {
            Log::warning('Analytics Service: Unusual number of buffered write operations', [
                'service' => 'analytics-service',
                'buffer_size' => $bufferSize,
                'alert' => 'high_buffer_size',
            ]);
        }

        // Schedule replay job with delay to allow for database recovery
        ReplayBufferedWriteOperationsJob::dispatch('analytics-service', 50)
            ->delay(now()->addMinutes(2))
            ->onQueue('write-operation-replay');

        Log::info('Analytics Service: Scheduled write operation replay job', [
            'service' => 'analytics-service',
            'delay_minutes' => 2,
            'batch_size' => 50,
        ]);

        // Track replay job scheduling for monitoring
        cache()->put('last_replay_job_scheduled', now()->toISOString(), 3600);
    }
}
