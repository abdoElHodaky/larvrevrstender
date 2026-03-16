<?php

namespace Shared\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\DatabaseFailoverEvent;
use Shared\Services\DatabaseFailoverAlertManager;

abstract class BaseDatabaseFailoverHandler implements ShouldQueue
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
     * Handle the database failover event.
     */
    public function handle(DatabaseFailoverEvent $event): void
    {
        Log::channel('database-failover')->critical("{$this->serviceName}: DATABASE FAILOVER INITIATED", [
            'service' => $this->serviceName,
            'from_connection' => $event->fromConnection,
            'to_connection' => $event->toConnection,
            'failover_type' => $event->failoverType,
            'timestamp' => $event->timestamp,
            'correlation_id' => $event->correlationId,
            'business_impact' => $this->getBusinessImpactDescription(),
        ]);

        // Execute service-specific failover logic
        $this->handleServiceSpecificFailover($event);
        
        // Send alerts using shared alert manager
        $this->alertManager->handleFailoverEvent($event);
        
        // Notify stakeholders
        $this->notifyStakeholders($event);
    }

    /**
     * Handle service-specific database failover logic.
     */
    abstract protected function handleServiceSpecificFailover(DatabaseFailoverEvent $event): void;

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
     * Set service to failover mode with common patterns.
     */
    protected function setFailoverMode(DatabaseFailoverEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        // Common failover mode settings
        cache()->put("{$serviceName}_service_mode", 'database_failover', 3600);
        cache()->put("{$serviceName}_service_failover_started", now()->toISOString(), 3600);
        cache()->put("{$serviceName}_service_primary_connection", $event->fromConnection, 3600);
        cache()->put("{$serviceName}_service_failover_connection", $event->toConnection, 3600);
        
        // Track failover metrics
        cache()->increment("{$serviceName}_service_failover_count");
        cache()->put("{$serviceName}_service_last_failover", now()->toISOString(), 86400);
    }

    /**
     * Handle different failover scenarios with common patterns.
     */
    protected function handleFailoverScenario(DatabaseFailoverEvent $event): void
    {
        switch ($event->failoverType) {
            case 'mongodb_fallback':
                $this->handleMongoDbFallback($event);
                break;
            case 'read_replica_promotion':
                $this->handleReadReplicaPromotion($event);
                break;
            case 'complete_failover':
                $this->handleCompleteFailover($event);
                break;
            default:
                $this->handleDefaultFailover($event);
        }
    }

    /**
     * Handle MongoDB fallback scenario.
     */
    protected function handleMongoDbFallback(DatabaseFailoverEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        Log::info("{$this->serviceName}: MongoDB fallback initiated", [
            'service' => $this->serviceName,
            'fallback_connection' => $event->toConnection,
            'business_impact' => 'Limited functionality - MongoDB fallback active',
        ]);

        // Enable MongoDB fallback mode
        cache()->put("{$serviceName}_service_mongodb_fallback_active", true, 3600);
        cache()->put("{$serviceName}_service_readonly_mode", true, 3600);
    }

    /**
     * Handle read replica promotion scenario.
     */
    protected function handleReadReplicaPromotion(DatabaseFailoverEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        Log::info("{$this->serviceName}: Read replica promotion initiated", [
            'service' => $this->serviceName,
            'promoted_replica' => $event->toConnection,
            'business_impact' => 'Read operations maintained - Write operations may be limited',
        ]);

        // Enable read replica promotion mode
        cache()->put("{$serviceName}_service_read_replica_promoted", true, 3600);
        cache()->put("{$serviceName}_service_write_operations_limited", true, 3600);
    }

    /**
     * Handle complete failover scenario.
     */
    protected function handleCompleteFailover(DatabaseFailoverEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        Log::critical("{$this->serviceName}: Complete database failover initiated", [
            'service' => $this->serviceName,
            'failover_connection' => $event->toConnection,
            'business_impact' => 'CRITICAL - Complete database failover active',
        ]);

        // Enable complete failover mode
        cache()->put("{$serviceName}_service_complete_failover_active", true, 3600);
        cache()->put("{$serviceName}_service_buffer_all_writes", true, 3600);
    }

    /**
     * Handle default failover scenario.
     */
    protected function handleDefaultFailover(DatabaseFailoverEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        Log::warning("{$this->serviceName}: Default failover scenario", [
            'service' => $this->serviceName,
            'failover_type' => $event->failoverType,
            'business_impact' => 'Unknown failover scenario - Default handling applied',
        ]);

        // Enable default failover mode
        cache()->put("{$serviceName}_service_default_failover_active", true, 3600);
    }

    /**
     * Coordinate with dependent services using common patterns.
     */
    protected function coordinateWithDependentServices(DatabaseFailoverEvent $event, array $dependentServices): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        foreach ($dependentServices as $service) {
            Log::info("{$this->serviceName}: Coordinating failover with {$service}", [
                'service' => $this->serviceName,
                'coordinating_with' => $service,
                'failover_type' => $event->failoverType,
                'message' => "{$this->serviceName} database failover in progress - coordination required",
            ]);

            // Set coordination flags for dependent services
            cache()->put("{$serviceName}_service_failover_coordination_{$service}", [
                'status' => 'database_failover_in_progress',
                'failover_type' => $event->failoverType,
                'timestamp' => now()->toISOString(),
                'coordination_required' => true,
                'estimated_completion' => now()->addMinutes(10)->toISOString(),
            ], 3600);
        }
    }

    /**
     * Update service health metrics with common patterns.
     */
    protected function updateServiceHealthMetrics(DatabaseFailoverEvent $event): void
    {
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));
        
        // Update service health based on failover type
        if ($event->failoverType === 'complete_failover') {
            cache()->put("{$serviceName}_service_health", 'critical', 3600);
        } elseif ($event->failoverType === 'mongodb_fallback') {
            cache()->put("{$serviceName}_service_health", 'degraded', 3600);
        } else {
            cache()->put("{$serviceName}_service_health", 'warning', 3600);
        }

        Log::info("{$this->serviceName}: Health metrics updated after failover", [
            'service' => $this->serviceName,
            'health_status' => cache()->get("{$serviceName}_service_health"),
            'failover_type' => $event->failoverType,
        ]);
    }

    /**
     * Notify stakeholders using common notification patterns.
     */
    protected function notifyStakeholders(DatabaseFailoverEvent $event): void
    {
        $stakeholders = $this->getStakeholders();
        $serviceName = strtolower(str_replace('-service', '', $this->serviceName));

        foreach ($stakeholders as $stakeholder) {
            cache()->put("{$serviceName}_service_failover_notification_{$stakeholder}", [
                'status' => 'database_failover_initiated',
                'failover_type' => $event->failoverType,
                'timestamp' => now()->toISOString(),
                'business_impact' => $this->getBusinessImpactDescription(),
                'estimated_completion' => now()->addMinutes(10)->toISOString(),
            ], 1800); // 30 minutes
        }

        Log::info("{$this->serviceName}: Stakeholders notified of failover", [
            'service' => $this->serviceName,
            'stakeholders' => $stakeholders,
            'failover_type' => $event->failoverType,
        ]);
    }

    /**
     * Get operation-specific rules for the service.
     */
    protected function getOperationSpecificRules(): array
    {
        return $this->serviceConfig['operation_specific_rules'] ?? [];
    }

    /**
     * Check if operation is time-sensitive based on service configuration.
     */
    protected function isTimeSensitiveOperation(string $operationType): bool
    {
        $rules = $this->getOperationSpecificRules();
        return isset($rules[$operationType]) && 
               ($rules[$operationType]['priority'] === 'critical' || 
                $rules[$operationType]['time_sensitive'] === true);
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
