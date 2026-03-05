<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationReplayedEvent;
use Shared\Services\DatabaseFailoverAlertManager;

class HandleWriteOperationReplayed implements ShouldQueue
{
    use InteractsWithQueue;

    private DatabaseFailoverAlertManager $alertManager;

    public function __construct()
    {
        $this->alertManager = new DatabaseFailoverAlertManager();
    }

    /**
     * Handle the write operation replayed event for Order Service.
     */
    public function handle(WriteOperationReplayedEvent $event): void
    {
        Log::channel('write-operations')->info('Order Service: Write operation successfully replayed', [
            'service' => 'order-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'replayed_at' => $event->replayedAt,
            'original_buffered_at' => $event->originalBufferedAt,
            'replay_duration_seconds' => $event->replayDurationSeconds,
            'correlation_id' => $event->correlationId,
        ]);

        // Order service handles revenue-critical operation replay monitoring
        $this->handleOrderReplayMonitoring($event);
    }

    /**
     * Handle order-specific write operation replay monitoring.
     */
    private function handleOrderReplayMonitoring(WriteOperationReplayedEvent $event): void
    {
        // Update metrics for successfully replayed operations
        cache()->increment('order_replayed_operations_count');
        cache()->decrement('order_buffered_operations_count'); // Reduce buffer count
        cache()->put('order_last_replayed_operation', now(), 3600);

        // Track operation type for revenue recovery monitoring
        $operationType = $event->operationType;
        cache()->increment("order_replayed_operations_{$operationType}");

        // Calculate business impact recovery
        $revenueRecovered = $this->calculateRevenueRecovered($operationType, $event);
        $businessImpactReduction = $this->assessBusinessImpactReduction($event);

        // Log for monitoring dashboard with revenue recovery context
        Log::info('Order Service: Write operation replay completed - revenue recovery', [
            'service' => 'order-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'replay_duration' => $event->replayDurationSeconds,
            'revenue_recovered' => $revenueRecovered,
            'business_impact_reduction' => $businessImpactReduction,
            'remaining_buffer_size' => cache()->get('order_buffered_operations_count', 0),
        ]);

        // Check if replay performance is degraded
        if ($event->replayDurationSeconds > 30) {
            Log::warning('Order Service: Slow write operation replay detected', [
                'service' => 'order-service',
                'operation_type' => $operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'performance_impact' => 'Revenue recovery may be delayed',
                'recommended_action' => 'Monitor database performance and consider scaling',
            ]);
        }

        // Monitor replay success rate and business continuity
        $this->monitorReplaySuccessRate($operationType);
        $this->assessBusinessContinuityRecovery($event);

        // Update order service health metrics
        $this->updateOrderServiceHealthMetrics($event);

        // Check for complete buffer recovery
        $remainingBufferSize = cache()->get('order_buffered_operations_count', 0);
        if ($remainingBufferSize === 0) {
            $this->handleCompleteBufferRecovery();
        }

        // Notify dependent services of successful order operation replay
        $this->notifyDependentServicesOfRecovery($event);
    }

    /**
     * Calculate revenue recovered from successful operation replay.
     */
    private function calculateRevenueRecovered(string $operationType, WriteOperationReplayedEvent $event): float
    {
        // Revenue impact per operation type (estimated values)
        $revenueImpactMap = [
            'order_creation' => 150.00,      // Average order value
            'payment_update' => 150.00,      // Payment completion value
            'status_update' => 0.00,         // No direct revenue impact
            'order_cancellation' => -150.00, // Revenue loss prevention
            'shipping_update' => 0.00,       // Operational, no direct revenue
            'order_modification' => 25.00,   // Additional revenue from modifications
            'refund_processing' => -75.00,   // Partial refund impact
        ];

        $baseRevenue = $revenueImpactMap[$operationType] ?? 0.00;
        
        // Apply delay penalty (longer delays reduce recovered value)
        $delayPenalty = min($event->replayDurationSeconds / 3600, 0.1); // Max 10% penalty
        $recoveredRevenue = $baseRevenue * (1 - $delayPenalty);

        return round($recoveredRevenue, 2);
    }

    /**
     * Assess business impact reduction from successful replay.
     */
    private function assessBusinessImpactReduction(WriteOperationReplayedEvent $event): string
    {
        $replayDuration = $event->replayDurationSeconds;
        
        if ($replayDuration <= 60) {
            return 'minimal_impact'; // Replayed within 1 minute
        } elseif ($replayDuration <= 300) {
            return 'low_impact'; // Replayed within 5 minutes
        } elseif ($replayDuration <= 900) {
            return 'moderate_impact'; // Replayed within 15 minutes
        } else {
            return 'high_impact'; // Took longer than 15 minutes
        }
    }

    /**
     * Monitor replay success rate for order operations.
     */
    private function monitorReplaySuccessRate(string $operationType): void
    {
        $totalReplayed = cache()->get('order_replayed_operations_count', 0);
        $totalBuffered = cache()->get('order_total_buffered_operations', 0);
        
        if ($totalBuffered > 0) {
            $successRate = ($totalReplayed / $totalBuffered) * 100;
            
            cache()->put('order_replay_success_rate', $successRate, 3600);
            
            Log::info('Order Service: Replay success rate updated', [
                'service' => 'order-service',
                'operation_type' => $operationType,
                'success_rate_percentage' => round($successRate, 2),
                'total_replayed' => $totalReplayed,
                'total_buffered' => $totalBuffered,
            ]);

            // Alert if success rate is low
            if ($successRate < 95 && $totalBuffered > 10) {
                Log::warning('Order Service: Low replay success rate detected', [
                    'service' => 'order-service',
                    'success_rate' => $successRate,
                    'business_impact' => 'Revenue recovery may be incomplete',
                    'recommended_action' => 'Investigate replay failures and database performance',
                ]);
            }
        }
    }

    /**
     * Assess business continuity recovery progress.
     */
    private function assessBusinessContinuityRecovery(WriteOperationReplayedEvent $event): void
    {
        $remainingBuffer = cache()->get('order_buffered_operations_count', 0);
        $totalBuffered = cache()->get('order_total_buffered_operations', 1);
        
        $recoveryProgress = (($totalBuffered - $remainingBuffer) / $totalBuffered) * 100;
        
        cache()->put('order_business_continuity_recovery_progress', $recoveryProgress, 3600);
        
        Log::info('Order Service: Business continuity recovery progress', [
            'service' => 'order-service',
            'recovery_progress_percentage' => round($recoveryProgress, 2),
            'remaining_operations' => $remainingBuffer,
            'business_status' => $this->getBusinessRecoveryStatus($recoveryProgress),
        ]);
    }

    /**
     * Get business recovery status based on progress.
     */
    private function getBusinessRecoveryStatus(float $recoveryProgress): string
    {
        if ($recoveryProgress >= 100) {
            return 'fully_recovered';
        } elseif ($recoveryProgress >= 80) {
            return 'mostly_recovered';
        } elseif ($recoveryProgress >= 50) {
            return 'partially_recovered';
        } elseif ($recoveryProgress >= 20) {
            return 'early_recovery';
        } else {
            return 'minimal_recovery';
        }
    }

    /**
     * Update order service health metrics based on replay success.
     */
    private function updateOrderServiceHealthMetrics(WriteOperationReplayedEvent $event): void
    {
        $remainingBuffer = cache()->get('order_buffered_operations_count', 0);
        
        // Update service health based on buffer status
        if ($remainingBuffer === 0) {
            cache()->put('order_service_health', 'healthy', 3600);
            cache()->put('order_service_mode', 'normal', 3600);
        } elseif ($remainingBuffer < 10) {
            cache()->put('order_service_health', 'recovering', 3600);
            cache()->put('order_service_mode', 'recovery', 3600);
        } else {
            cache()->put('order_service_health', 'degraded', 3600);
            cache()->put('order_service_mode', 'failover_recovery', 3600);
        }

        Log::info('Order Service: Health metrics updated after replay', [
            'service' => 'order-service',
            'health_status' => cache()->get('order_service_health'),
            'service_mode' => cache()->get('order_service_mode'),
            'remaining_buffer' => $remainingBuffer,
        ]);
    }

    /**
     * Handle complete buffer recovery for order service.
     */
    private function handleCompleteBufferRecovery(): void
    {
        Log::info('Order Service: COMPLETE BUFFER RECOVERY ACHIEVED', [
            'service' => 'order-service',
            'status' => 'all_operations_replayed',
            'business_impact' => 'Full revenue processing capability restored',
            'service_health' => 'healthy',
        ]);

        // Reset failover-related cache entries
        cache()->forget('order_service_failover_started');
        cache()->forget('order_service_buffer_all_writes');
        cache()->put('order_service_recovery_completed', now()->toISOString(), 86400);

        // Send recovery completion alert
        $this->sendRecoveryCompletionAlert();
    }

    /**
     * Notify dependent services of successful order operation recovery.
     */
    private function notifyDependentServicesOfRecovery(WriteOperationReplayedEvent $event): void
    {
        $dependentServices = ['payment-service', 'notification-service', 'user-service', 'analytics-service'];

        foreach ($dependentServices as $service) {
            cache()->put("order_service_recovery_notification_{$service}", [
                'status' => 'operation_replayed',
                'operation_type' => $event->operationType,
                'timestamp' => now()->toISOString(),
                'recovery_progress' => cache()->get('order_business_continuity_recovery_progress', 0),
                'message' => 'Order operation successfully replayed - service recovery in progress',
            ], 1800); // 30 minutes
        }
    }

    /**
     * Send recovery completion alert.
     */
    private function sendRecoveryCompletionAlert(): void
    {
        try {
            Log::info('Order Service: RECOVERY COMPLETION ALERT', [
                'alert_type' => 'order_service_recovery_complete',
                'service' => 'order-service',
                'status' => 'fully_recovered',
                'business_impact' => 'All buffered order operations successfully replayed',
                'revenue_impact' => 'Full revenue processing capability restored',
                'service_health' => 'healthy',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Order Service: Failed to send recovery completion alert', [
                'error' => $e->getMessage(),
                'service' => 'order-service',
            ]);
        }
    }
}
