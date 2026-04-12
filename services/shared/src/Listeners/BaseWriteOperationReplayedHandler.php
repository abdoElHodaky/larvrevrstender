<?php

namespace Shared\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationReplayedEvent;
use Shared\Services\DatabaseFailoverAlertManager;

abstract class BaseWriteOperationReplayedHandler implements ShouldQueue
{
    use InteractsWithQueue;

    protected DatabaseFailoverAlertManager $alertManager;
    protected string $serviceName;
    protected array $serviceConfig;

    public function __construct()
    {
        $this->alertManager = new DatabaseFailoverAlertManager();
        $this->serviceName = $this->getServiceName();
        $this->serviceConfig = $this->getServiceConfig();
    }

    /**
     * Handle the write operation replayed event.
     */
    public function handle(WriteOperationReplayedEvent $event): void
    {
        Log::channel('write-operations')->info("{$this->serviceName}: Operation successfully replayed", [
            'service' => $this->serviceName,
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'replayed_at' => $event->replayedAt,
            'original_buffered_at' => $event->originalBufferedAt,
            'replay_duration_seconds' => $event->replayDurationSeconds,
            'correlation_id' => $event->correlationId,
            'business_impact' => $this->getBusinessImpactDescription(),
        ]);

        // Execute service-specific replay monitoring logic
        $this->handleServiceSpecificReplayMonitoring($event);
    }

    /**
     * Handle service-specific write operation replay monitoring.
     */
    abstract protected function handleServiceSpecificReplayMonitoring(WriteOperationReplayedEvent $event): void;

    /**
     * Get the service name for logging and identification.
     */
    abstract protected function getServiceName(): string;

    /**
     * Get service-specific configuration.
     */
    abstract protected function getServiceConfig(): array;

    /**
     * Get business impact description for this service.
     */
    abstract protected function getBusinessImpactDescription(): string;

    /**
     * Get service-specific stakeholders to notify.
     */
    abstract protected function getStakeholders(): array;

    /**
     * Calculate business value recovered from successful operation replay.
     */
    abstract protected function calculateBusinessValueRecovered(string $operationType, WriteOperationReplayedEvent $event): float;

    /**
     * Update metrics for successfully replayed operations with common patterns.
     */
    protected function updateReplayMetrics(WriteOperationReplayedEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        // Update common replay metrics
        cache()->increment("{$serviceName}_replayed_operations_count");
        cache()->decrement("{$serviceName}_buffered_operations_count");
        cache()->put("{$serviceName}_last_replayed_operation", now(), 3600);

        // Track operation type for recovery monitoring
        $operationType = $event->operationType;
        cache()->increment("{$serviceName}_replayed_operations_{$operationType}");
    }

    /**
     * Monitor replay success rate with common patterns.
     */
    protected function monitorReplaySuccessRate(string $operationType): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        $totalReplayed = cache()->get("{$serviceName}_replayed_operations_count", 0);
        $totalBuffered = cache()->get("{$serviceName}_total_buffered_operations", 0);
        
        if ($totalBuffered > 0) {
            $successRate = ($totalReplayed / $totalBuffered) * 100;
            
            cache()->put("{$serviceName}_replay_success_rate", $successRate, 3600);
            
            Log::info("{$this->serviceName}: Replay success rate updated", [
                'service' => $this->serviceName,
                'operation_type' => $operationType,
                'success_rate_percentage' => round($successRate, 2),
                'total_replayed' => $totalReplayed,
                'total_buffered' => $totalBuffered,
            ]);

            // Alert if success rate is low
            $threshold = $this->getSuccessRateThreshold();
            if ($successRate < $threshold && $totalBuffered > 5) {
                $this->sendLowSuccessRateAlert($successRate, $totalBuffered, $threshold);
            }
        }
    }

    /**
     * Assess recovery progress with common patterns.
     */
    protected function assessRecoveryProgress(WriteOperationReplayedEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        $remainingBuffer = cache()->get("{$serviceName}_buffered_operations_count", 0);
        $totalBuffered = cache()->get("{$serviceName}_total_buffered_operations", 1);
        
        $recoveryProgress = (($totalBuffered - $remainingBuffer) / $totalBuffered) * 100;
        
        cache()->put("{$serviceName}_recovery_progress", $recoveryProgress, 3600);
        
        Log::info("{$this->serviceName}: Recovery progress assessment", [
            'service' => $this->serviceName,
            'recovery_progress_percentage' => round($recoveryProgress, 2),
            'remaining_operations' => $remainingBuffer,
            'recovery_status' => $this->getRecoveryStatus($recoveryProgress),
        ]);
    }

    /**
     * Update service health metrics based on replay success with common patterns.
     */
    protected function updateServiceHealthMetrics(WriteOperationReplayedEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        $remainingBuffer = cache()->get("{$serviceName}_buffered_operations_count", 0);
        $threshold = $this->getBufferAlertThreshold();
        
        // Update service health based on buffer status
        if ($remainingBuffer === 0) {
            cache()->put("{$serviceName}_service_health", 'healthy', 3600);
            cache()->put("{$serviceName}_service_mode", 'normal_operations', 3600);
        } elseif ($remainingBuffer < ($threshold / 2)) {
            cache()->put("{$serviceName}_service_health", 'recovering', 3600);
            cache()->put("{$serviceName}_service_mode", 'recovery', 3600);
        } else {
            cache()->put("{$serviceName}_service_health", 'degraded', 3600);
            cache()->put("{$serviceName}_service_mode", 'failover_recovery', 3600);
        }

        Log::info("{$this->serviceName}: Health metrics updated after replay", [
            'service' => $this->serviceName,
            'health_status' => cache()->get("{$serviceName}_service_health"),
            'service_mode' => cache()->get("{$serviceName}_service_mode"),
            'remaining_buffer' => $remainingBuffer,
        ]);
    }

    /**
     * Handle complete buffer recovery with common patterns.
     */
    protected function handleCompleteBufferRecovery(): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        Log::info("{$this->serviceName}: COMPLETE BUFFER RECOVERY ACHIEVED", [
            'service' => $this->serviceName,
            'status' => 'all_operations_replayed',
            'business_impact' => $this->getBusinessImpactDescription(),
            'service_health' => 'healthy',
        ]);

        // Reset failover-related cache entries
        cache()->forget("{$serviceName}_service_failover_started");
        cache()->forget("{$serviceName}_service_buffer_all_writes");
        cache()->put("{$serviceName}_service_recovery_completed", now()->toISOString(), 86400);

        // Send recovery completion alert
        $this->sendRecoveryCompletionAlert();
    }

    /**
     * Notify stakeholders of recovery progress with common patterns.
     */
    protected function notifyStakeholdersOfRecovery(WriteOperationReplayedEvent $event): void
    {
        $stakeholders = $this->getStakeholders();
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));

        foreach ($stakeholders as $stakeholder) {
            cache()->put("{$serviceName}_service_recovery_notification_{$stakeholder}", [
                'status' => 'operation_replayed',
                'operation_type' => $event->operationType,
                'timestamp' => now()->toISOString(),
                'recovery_progress' => cache()->get("{$serviceName}_recovery_progress", 0),
                'message' => "{$this->serviceName} operation successfully replayed - recovery in progress",
            ], 1800); // 30 minutes
        }
    }

    /**
     * Notify dependent services of recovery with common patterns.
     */
    protected function notifyDependentServicesOfRecovery(WriteOperationReplayedEvent $event, array $dependentServices): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));

        foreach ($dependentServices as $service) {
            cache()->put("{$serviceName}_service_recovery_notification_{$service}", [
                'status' => 'operation_replayed',
                'operation_type' => $event->operationType,
                'timestamp' => now()->toISOString(),
                'recovery_progress' => cache()->get("{$serviceName}_recovery_progress", 0),
                'message' => "{$this->serviceName} operation replayed - processing recovering",
            ], 1800); // 30 minutes
        }
    }

    /**
     * Get recovery status based on progress.
     */
    protected function getRecoveryStatus(float $recoveryProgress): string
    {
        if ($recoveryProgress >= 100) {
            return 'fully_recovered';
        } elseif ($recoveryProgress >= 90) {
            return 'mostly_recovered';
        } elseif ($recoveryProgress >= 70) {
            return 'partially_recovered';
        } elseif ($recoveryProgress >= 50) {
            return 'limited_recovery';
        } else {
            return 'minimal_recovery';
        }
    }

    /**
     * Check if replay duration is slow based on service configuration.
     */
    protected function isSlowReplay(WriteOperationReplayedEvent $event): bool
    {
        $slowThreshold = $this->serviceConfig['slow_replay_threshold'] ?? 30;
        return $event->replayDurationSeconds > $slowThreshold;
    }

    /**
     * Send slow replay alert.
     */
    protected function sendSlowReplayAlert(WriteOperationReplayedEvent $event): void
    {
        try {
            Log::warning("{$this->serviceName}: SLOW OPERATION REPLAY DETECTED", [
                'alert_type' => 'slow_replay',
                'service' => $this->serviceName,
                'operation_type' => $event->operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'business_impact' => $this->getBusinessImpactDescription(),
                'recommended_action' => 'Monitor performance and database teams',
            ]);
        } catch (\Exception $e) {
            Log::error("{$this->serviceName}: Failed to send slow replay alert", [
                'error' => $e->getMessage(),
                'operation_type' => $event->operationType,
            ]);
        }
    }

    /**
     * Send low success rate alert.
     */
    protected function sendLowSuccessRateAlert(float $successRate, int $totalBuffered, float $threshold): void
    {
        try {
            Log::warning("{$this->serviceName}: LOW REPLAY SUCCESS RATE", [
                'alert_type' => 'low_success_rate',
                'service' => $this->serviceName,
                'success_rate' => $successRate,
                'threshold' => $threshold,
                'total_buffered' => $totalBuffered,
                'business_impact' => $this->getBusinessImpactDescription(),
                'recommended_action' => 'Escalate to operations and database teams',
            ]);
        } catch (\Exception $e) {
            Log::error("{$this->serviceName}: Failed to send low success rate alert", [
                'error' => $e->getMessage(),
                'success_rate' => $successRate,
            ]);
        }
    }

    /**
     * Send recovery completion alert.
     */
    protected function sendRecoveryCompletionAlert(): void
    {
        try {
            Log::info("{$this->serviceName}: RECOVERY COMPLETION ALERT", [
                'alert_type' => 'recovery_complete',
                'service' => $this->serviceName,
                'status' => 'fully_recovered',
                'business_impact' => $this->getBusinessImpactDescription(),
                'service_health' => 'healthy',
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error("{$this->serviceName}: Failed to send recovery completion alert", [
                'error' => $e->getMessage(),
                'service' => $this->serviceName,
            ]);
        }
    }

    /**
     * Get operation-specific rules for the service.
     */
    protected function getOperationSpecificRules(): array
    {
        return $this->serviceConfig['operation_specific_rules'] ?? [];
    }

    /**
     * Get buffer alert threshold for the service.
     */
    protected function getBufferAlertThreshold(): int
    {
        return $this->serviceConfig['buffer_alert_threshold'] ?? 50;
    }

    /**
     * Get success rate threshold for the service.
     */
    protected function getSuccessRateThreshold(): float
    {
        return $this->serviceConfig['success_rate_threshold'] ?? 95.0;
    }
}
