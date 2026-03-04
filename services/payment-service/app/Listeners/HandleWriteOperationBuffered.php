<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationBufferedEvent;

class HandleWriteOperationBuffered implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the write operation buffered event for Payment Service.
     * CRITICAL: Payment operations must be tracked meticulously.
     */
    public function handle(WriteOperationBufferedEvent $event): void
    {
        Log::channel('payment-operations')->critical('Payment Service: CRITICAL write operation buffered', [
            'service' => 'payment-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'buffered_at' => $event->bufferedAt,
            'correlation_id' => $event->correlationId,
            'severity' => 'CRITICAL',
            'financial_impact' => true,
        ]);

        // Payment service requires immediate attention for buffered operations
        $this->handleCriticalPaymentBuffering($event);
    }

    /**
     * Handle critical payment operation buffering with enhanced monitoring.
     */
    private function handleCriticalPaymentBuffering(WriteOperationBufferedEvent $event): void
    {
        // Update critical metrics for payment operations
        cache()->increment('payment_buffered_operations_count');
        cache()->put('payment_last_buffered_operation', now(), 3600);
        
        // Track financial operations separately
        if (in_array($event->operationType, ['payment_processing', 'refund_processing'])) {
            cache()->increment('payment_financial_operations_buffered');
            
            Log::critical('Payment Service: Financial operation buffered - REQUIRES IMMEDIATE ATTENTION', [
                'service' => 'payment-service',
                'operation_id' => $event->operationId,
                'operation_type' => $event->operationType,
                'financial_operation' => true,
                'alert_level' => 'IMMEDIATE',
            ]);
        }

        // Alert monitoring systems immediately
        $this->alertMonitoringSystems($event);
        
        // Send notification to payment team
        $this->notifyPaymentTeam($event);
    }

    /**
     * Alert monitoring systems for payment operations.
     */
    private function alertMonitoringSystems(WriteOperationBufferedEvent $event): void
    {
        $bufferSize = cache()->get('payment_buffered_operations_count', 0);
        $financialBuffered = cache()->get('payment_financial_operations_buffered', 0);

        Log::critical('Payment Service: Monitoring alert for buffered operations', [
            'service' => 'payment-service',
            'total_buffered' => $bufferSize,
            'financial_buffered' => $financialBuffered,
            'operation_id' => $event->operationId,
            'alert_type' => 'payment_operation_buffered',
            'severity' => 'CRITICAL',
        ]);

        // Any buffered payment operation is critical
        if ($bufferSize > 0) {
            Log::emergency('Payment Service: EMERGENCY - Payment operations are being buffered', [
                'service' => 'payment-service',
                'buffer_size' => $bufferSize,
                'financial_operations_affected' => $financialBuffered,
                'requires_immediate_action' => true,
            ]);
        }
    }

    /**
     * Notify payment team of buffered operations.
     */
    private function notifyPaymentTeam(WriteOperationBufferedEvent $event): void
    {
        // This would integrate with your notification system (Slack, email, etc.)
        Log::critical('Payment Service: Notifying payment team of buffered operation', [
            'service' => 'payment-service',
            'operation_id' => $event->operationId,
            'notification_sent' => true,
            'team' => 'payment-operations',
        ]);
    }
}
