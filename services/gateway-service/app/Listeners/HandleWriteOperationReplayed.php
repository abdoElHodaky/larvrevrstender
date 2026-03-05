<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationReplayedEvent;
use Shared\Listeners\BaseWriteOperationReplayedHandler;

class HandleWriteOperationReplayed extends BaseWriteOperationReplayedHandler
{
    /**
     * Handle gateway service specific write operation replay monitoring.
     */
    protected function handleServiceSpecificReplayMonitoring(WriteOperationReplayedEvent $event): void
    {
        // Update metrics for successfully replayed gateway operations
        $this->updateReplayMetrics($event);

        // Track operation type for gateway recovery monitoring
        $operationType = $event->operationType;

        // Calculate gateway system impact recovery
        $systemImpactRecovered = $this->calculateBusinessValueRecovered($operationType, $event);
        $requestRoutingImpactReduction = $this->assessRequestRoutingImpactReduction($event);
        $systemCoordinationStatus = $this->validateSystemCoordinationStatus($event);

        // Log for monitoring dashboard with gateway system recovery context
        Log::info('Gateway Service: System operation replay completed - gateway coordination recovery', [
            'service' => 'gateway-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'replay_duration' => $event->replayDurationSeconds,
            'system_impact_recovered' => $systemImpactRecovered,
            'request_routing_impact_reduction' => $requestRoutingImpactReduction,
            'system_coordination_status' => $systemCoordinationStatus,
            'remaining_buffer_size' => cache()->get('gateway_buffered_operations_count', 0),
            'alert_level' => 'GATEWAY_RECOVERY',
        ]);

        // Critical alert for slow gateway operation replay (stricter threshold)
        if ($this->isSlowReplay($event)) {
            Log::critical('Gateway Service: SLOW GATEWAY OPERATION REPLAY DETECTED', [
                'service' => 'gateway-service',
                'operation_type' => $operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'system_impact' => 'CRITICAL - Gateway system coordination delayed',
                'request_routing_risk' => 'HIGH - Request routing performance at risk',
                'recommended_action' => 'URGENT: Escalate to infrastructure and gateway teams',
                'escalation_level' => 'GATEWAY_PERFORMANCE_CRITICAL',
            ]);

            // Send gateway performance degradation alert
            $this->sendGatewayPerformanceDegradationAlert($event);
        }

        // Monitor gateway replay success rate and system coordination continuity
        $this->monitorReplaySuccessRate($operationType);
        $this->assessRecoveryProgress($event);

        // Update gateway service health metrics
        $this->updateServiceHealthMetrics($event);

        // Check for complete gateway buffer recovery
        $remainingBufferSize = cache()->get('gateway_buffered_operations_count', 0);
        if ($remainingBufferSize === 0) {
            $this->handleCompleteBufferRecovery();
        }

        // Notify infrastructure teams and ALL dependent services
        $this->notifyStakeholdersOfRecovery($event);
        $dependentServices = [
            'order-service', 'payment-service', 'user-service', 
            'bidding-service', 'auction-service', 'auth-service',
            'notification-service', 'vin-ocr-service', 'analytics-service'
        ];
        $this->notifyDependentServicesOfRecovery($event, $dependentServices);

        // Update gateway system coordination metrics
        $this->updateGatewaySystemCoordinationMetrics($event);
    }

    /**
     * Calculate system impact value recovered from successful operation replay.
     */
    protected function calculateBusinessValueRecovered(string $operationType, WriteOperationReplayedEvent $event): float
    {
        // Gateway system impact per operation type (system-wide impact values)
        $systemImpactMap = [
            'request_routing' => 1000.00,        // High system impact - all requests
            'load_balancing' => 800.00,          // High system impact - traffic distribution
            'api_gateway_routing' => 900.00,     // High system impact - API coordination
            'authentication_routing' => 700.00,  // High system impact - auth coordination
            'service_discovery' => 500.00,       // Medium system impact - service coordination
            'health_check_routing' => 300.00,    // Medium system impact - monitoring
            'metrics_collection' => 200.00,      // Lower system impact - analytics
        ];

        $baseSystemImpact = $systemImpactMap[$operationType] ?? 100.00;
        
        // Apply delay penalty (gateway operations have strictest requirements)
        $delayPenalty = min($event->replayDurationSeconds / 600, 0.10); // Max 10% penalty (10 min)
        $recoveredSystemImpact = $baseSystemImpact * (1 - $delayPenalty);

        return round($recoveredSystemImpact, 2);
    }

    /**
     * Assess request routing impact reduction from successful replay.
     */
    private function assessRequestRoutingImpactReduction(WriteOperationReplayedEvent $event): string
    {
        $replayDuration = $event->replayDurationSeconds;
        
        // Gateway operations have strictest system coordination requirements
        if ($replayDuration <= 5) {
            return 'minimal_routing_impact'; // Replayed within 5 seconds
        } elseif ($replayDuration <= 30) {
            return 'low_routing_impact'; // Replayed within 30 seconds
        } elseif ($replayDuration <= 120) {
            return 'moderate_routing_impact'; // Replayed within 2 minutes
        } else {
            return 'high_routing_impact'; // Took longer than 2 minutes
        }
    }

    /**
     * Validate system coordination status for replayed operation.
     */
    private function validateSystemCoordinationStatus(WriteOperationReplayedEvent $event): string
    {
        // Check if operation maintains system coordination requirements
        $criticalSystemOperations = [
            'request_routing',
            'load_balancing',
            'api_gateway_routing',
            'authentication_routing'
        ];

        if (in_array($event->operationType, $criticalSystemOperations)) {
            // Validate system coordination requirements
            if ($event->replayDurationSeconds <= 60) { // 1 minute
                return 'system_coordination_maintained';
            } else {
                return 'system_coordination_degraded';
            }
        }

        return 'non_critical_system_operation';
    }

    /**
     * Update gateway system coordination metrics.
     */
    private function updateGatewaySystemCoordinationMetrics(WriteOperationReplayedEvent $event): void
    {
        // Track gateway system operations that might be affected
        $activeConnections = cache()->get('gateway_service_active_connections', 0);
        $requestThroughput = cache()->get('gateway_service_request_throughput', 0);
        $loadBalancerHealth = cache()->get('gateway_service_load_balancer_health', 'unknown');
        
        // Update gateway system coordination health metrics
        cache()->put('gateway_service_system_coordination_health', 'recovering', 3600);
        cache()->put('gateway_service_last_system_operation_replayed', $event->operationType, 3600);
        
        Log::info('Gateway Service: Updated gateway system coordination metrics', [
            'service' => 'gateway-service',
            'active_connections' => $activeConnections,
            'request_throughput' => $requestThroughput,
            'load_balancer_health' => $loadBalancerHealth,
            'operation_type' => $event->operationType,
            'system_coordination_health' => 'recovering',
        ]);
    }

    /**
     * Send gateway performance degradation alert.
     */
    private function sendGatewayPerformanceDegradationAlert(WriteOperationReplayedEvent $event): void
    {
        try {
            Log::critical('Gateway Service: GATEWAY PERFORMANCE DEGRADATION ALERT', [
                'alert_type' => 'gateway_performance_degradation',
                'service' => 'gateway-service',
                'operation_type' => $event->operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'system_impact' => 'CRITICAL - Gateway system coordination delayed',
                'request_routing_risk' => 'HIGH - Request routing performance at risk',
                'escalation_level' => 'GATEWAY_PERFORMANCE_CRITICAL',
                'recommended_action' => 'URGENT: Escalate to infrastructure and gateway teams',
            ]);
        } catch (\Exception $e) {
            Log::error('Gateway Service: Failed to send gateway performance degradation alert', [
                'error' => $e->getMessage(),
                'operation_type' => $event->operationType,
            ]);
        }
    }

    /**
     * Get the service name for logging and identification.
     */
    protected function getServiceName(): string
    {
        return 'Gateway Service';
    }

    /**
     * Get service-specific configuration.
     */
    protected function getServiceConfig(): array
    {
        return [
            'buffer_alert_threshold' => 25, // Lower threshold for gateway
            'success_rate_threshold' => 99.0, // Higher threshold for gateway
            'slow_replay_threshold' => 10, // Faster threshold for gateway
            'operation_specific_rules' => [
                'request_routing' => [
                    'priority' => 'critical',
                    'time_sensitive' => true,
                    'max_delay_seconds' => 5,
                ],
                'load_balancing' => [
                    'priority' => 'critical',
                    'time_sensitive' => true,
                    'max_delay_seconds' => 5,
                ],
                'api_gateway_routing' => [
                    'priority' => 'critical',
                    'time_sensitive' => true,
                    'max_delay_seconds' => 3,
                ],
                'authentication_routing' => [
                    'priority' => 'critical',
                    'time_sensitive' => true,
                    'max_delay_seconds' => 3,
                ],
            ],
        ];
    }

    /**
     * Get business impact description for this service.
     */
    protected function getBusinessImpactDescription(): string
    {
        return 'CRITICAL - Gateway Service recovery affects ALL system coordination and request routing';
    }

    /**
     * Get service-specific stakeholders to notify.
     */
    protected function getStakeholders(): array
    {
        return [
            'CTO',
            'VP Engineering',
            'Infrastructure Team Lead',
            'DevOps Team Lead',
            'Site Reliability Engineer',
            'Network Operations Center',
            'System Architecture Team',
            'Security Team Lead',
            'Operations Director'
        ];
    }
}
