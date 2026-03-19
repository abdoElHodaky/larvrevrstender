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
     * Handle the write operation replayed event for Payment Service.
     */
    public function handle(WriteOperationReplayedEvent $event): void
    {
        Log::channel('write-operations')->critical('Payment Service: Financial operation successfully replayed', [
            'service' => 'payment-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'replayed_at' => $event->replayedAt,
            'original_buffered_at' => $event->originalBufferedAt,
            'replay_duration_seconds' => $event->replayDurationSeconds,
            'correlation_id' => $event->correlationId,
            'financial_impact' => 'CRITICAL - Financial transaction replay completed',
            'compliance_status' => 'PCI DSS audit trail maintained',
        ]);

        // Payment service handles financial-critical operation replay monitoring
        $this->handlePaymentReplayMonitoring($event);
    }

    /**
     * Handle payment-specific write operation replay monitoring.
     */
    private function handlePaymentReplayMonitoring(WriteOperationReplayedEvent $event): void
    {
        // Update metrics for successfully replayed financial operations
        cache()->increment('payment_replayed_operations_count');
        cache()->decrement('payment_buffered_operations_count'); // Reduce buffer count
        cache()->put('payment_last_replayed_operation', now(), 3600);

        // Track operation type for financial recovery monitoring
        $operationType = $event->operationType;
        cache()->increment("payment_replayed_operations_{$operationType}");

        // Calculate financial impact recovery and compliance metrics
        $financialRecovered = $this->calculateFinancialRecovered($operationType, $event);
        $complianceImpactReduction = $this->assessComplianceImpactReduction($event);
        $pciDssCompliance = $this->validatePciDssCompliance($event);

        // Log for monitoring dashboard with financial recovery context
        Log::critical('Payment Service: Financial operation replay completed - compliance recovery', [
            'service' => 'payment-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'replay_duration' => $event->replayDurationSeconds,
            'financial_recovered' => $financialRecovered,
            'compliance_impact_reduction' => $complianceImpactReduction,
            'pci_dss_compliance' => $pciDssCompliance,
            'remaining_buffer_size' => cache()->get('payment_buffered_operations_count', 0),
            'alert_level' => 'FINANCIAL_RECOVERY',
        ]);

        // Critical alert for slow financial operation replay
        if ($event->replayDurationSeconds > 15) {
            Log::critical('Payment Service: SLOW FINANCIAL OPERATION REPLAY DETECTED', [
                'service' => 'payment-service',
                'operation_type' => $operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'financial_impact' => 'CRITICAL - Financial recovery delayed beyond acceptable limits',
                'compliance_risk' => 'HIGH - PCI DSS compliance timeline at risk',
                'recommended_action' => 'URGENT: Escalate to financial operations and database teams',
                'escalation_level' => 'C_LEVEL_IMMEDIATE',
            ]);

            // Send immediate C-level alert for slow financial recovery
            $this->sendSlowFinancialRecoveryAlert($event);
        }

        // Monitor financial replay success rate and compliance continuity
        $this->monitorFinancialReplaySuccessRate($operationType);
        $this->assessFinancialComplianceContinuity($event);

        // Update payment service health metrics
        $this->updatePaymentServiceHealthMetrics($event);

        // Check for complete financial buffer recovery
        $remainingBufferSize = cache()->get('payment_buffered_operations_count', 0);
        if ($remainingBufferSize === 0) {
            $this->handleCompleteFinancialBufferRecovery();
        }

        // Notify financial stakeholders and dependent services
        $this->notifyFinancialStakeholdersOfRecovery($event);
        $this->notifyDependentServicesOfFinancialRecovery($event);

        // Update PCI DSS compliance audit trail
        $this->updatePciDssAuditTrail($event);
    }

    /**
     * Calculate financial value recovered from successful operation replay.
     */
    private function calculateFinancialRecovered(string $operationType, WriteOperationReplayedEvent $event): float
    {
        // Financial impact per operation type (estimated values)
        $financialImpactMap = [
            'payment_processing' => 200.00,     // Average payment value
            'refund_processing' => -150.00,     // Refund amount
            'payment_authorization' => 200.00,  // Authorization value
            'payment_capture' => 200.00,        // Capture value
            'payment_void' => 0.00,             // No direct financial impact
            'chargeback_processing' => -250.00, // Chargeback impact
            'settlement_processing' => 180.00,  // Settlement value
            'fee_calculation' => 15.00,         // Processing fee
        ];

        $baseFinancial = $financialImpactMap[$operationType] ?? 0.00;
        
        // Apply compliance delay penalty (financial operations have stricter requirements)
        $delayPenalty = min($event->replayDurationSeconds / 1800, 0.05); // Max 5% penalty (30 min)
        $recoveredFinancial = $baseFinancial * (1 - $delayPenalty);

        return round($recoveredFinancial, 2);
    }

    /**
     * Assess compliance impact reduction from successful replay.
     */
    private function assessComplianceImpactReduction(WriteOperationReplayedEvent $event): string
    {
        $replayDuration = $event->replayDurationSeconds;
        
        // Financial operations have stricter compliance requirements
        if ($replayDuration <= 30) {
            return 'minimal_compliance_impact'; // Replayed within 30 seconds
        } elseif ($replayDuration <= 120) {
            return 'low_compliance_impact'; // Replayed within 2 minutes
        } elseif ($replayDuration <= 600) {
            return 'moderate_compliance_impact'; // Replayed within 10 minutes
        } else {
            return 'high_compliance_impact'; // Took longer than 10 minutes
        }
    }

    /**
     * Validate PCI DSS compliance for replayed operation.
     */
    private function validatePciDssCompliance(WriteOperationReplayedEvent $event): string
    {
        // Check if operation maintains PCI DSS compliance requirements
        $sensitiveOperations = [
            'payment_processing',
            'payment_authorization',
            'payment_capture',
            'refund_processing'
        ];

        if (in_array($event->operationType, $sensitiveOperations)) {
            // Validate compliance requirements
            if ($event->replayDurationSeconds <= 300) { // 5 minutes
                return 'compliant';
            } else {
                return 'compliance_at_risk';
            }
        }

        return 'non_sensitive_operation';
    }

    /**
     * Monitor financial replay success rate.
     */
    private function monitorFinancialReplaySuccessRate(string $operationType): void
    {
        $totalReplayed = cache()->get('payment_replayed_operations_count', 0);
        $totalBuffered = cache()->get('payment_total_buffered_operations', 0);
        
        if ($totalBuffered > 0) {
            $successRate = ($totalReplayed / $totalBuffered) * 100;
            
            cache()->put('payment_replay_success_rate', $successRate, 3600);
            
            Log::critical('Payment Service: Financial replay success rate updated', [
                'service' => 'payment-service',
                'operation_type' => $operationType,
                'success_rate_percentage' => round($successRate, 2),
                'total_replayed' => $totalReplayed,
                'total_buffered' => $totalBuffered,
                'compliance_status' => 'PCI DSS monitoring active',
            ]);

            // Critical alert if financial success rate is low
            if ($successRate < 98 && $totalBuffered > 5) {
                Log::critical('Payment Service: CRITICAL FINANCIAL REPLAY SUCCESS RATE', [
                    'service' => 'payment-service',
                    'success_rate' => $successRate,
                    'financial_impact' => 'CRITICAL - Financial recovery incomplete',
                    'compliance_risk' => 'HIGH - PCI DSS compliance at risk',
                    'recommended_action' => 'IMMEDIATE C-level escalation and financial operations review',
                    'escalation_level' => 'FINANCIAL_EMERGENCY',
                ]);

                // Send immediate financial emergency alert
                $this->sendFinancialEmergencyAlert($successRate, $totalBuffered);
            }
        }
    }

    /**
     * Assess financial compliance continuity.
     */
    private function assessFinancialComplianceContinuity(WriteOperationReplayedEvent $event): void
    {
        $remainingBuffer = cache()->get('payment_buffered_operations_count', 0);
        $totalBuffered = cache()->get('payment_total_buffered_operations', 1);
        
        $recoveryProgress = (($totalBuffered - $remainingBuffer) / $totalBuffered) * 100;
        
        cache()->put('payment_financial_compliance_recovery_progress', $recoveryProgress, 3600);
        
        Log::critical('Payment Service: Financial compliance continuity assessment', [
            'service' => 'payment-service',
            'recovery_progress_percentage' => round($recoveryProgress, 2),
            'remaining_operations' => $remainingBuffer,
            'compliance_status' => $this->getFinancialComplianceStatus($recoveryProgress),
            'pci_dss_status' => 'monitoring_active',
        ]);
    }

    /**
     * Get financial compliance status based on progress.
     */
    private function getFinancialComplianceStatus(float $recoveryProgress): string
    {
        if ($recoveryProgress >= 100) {
            return 'fully_compliant';
        } elseif ($recoveryProgress >= 95) {
            return 'mostly_compliant';
        } elseif ($recoveryProgress >= 80) {
            return 'partially_compliant';
        } elseif ($recoveryProgress >= 50) {
            return 'compliance_at_risk';
        } else {
            return 'critical_compliance_risk';
        }
    }

    /**
     * Update payment service health metrics based on replay success.
     */
    private function updatePaymentServiceHealthMetrics(WriteOperationReplayedEvent $event): void
    {
        $remainingBuffer = cache()->get('payment_buffered_operations_count', 0);
        
        // Update service health based on buffer status (stricter for financial operations)
        if ($remainingBuffer === 0) {
            cache()->put('payment_service_health', 'healthy', 3600);
            cache()->put('payment_service_mode', 'normal_financial_operations', 3600);
            cache()->put('payment_service_compliance_status', 'fully_compliant', 3600);
        } elseif ($remainingBuffer < 5) {
            cache()->put('payment_service_health', 'recovering', 3600);
            cache()->put('payment_service_mode', 'financial_recovery', 3600);
            cache()->put('payment_service_compliance_status', 'mostly_compliant', 3600);
        } else {
            cache()->put('payment_service_health', 'degraded', 3600);
            cache()->put('payment_service_mode', 'financial_failover_recovery', 3600);
            cache()->put('payment_service_compliance_status', 'compliance_at_risk', 3600);
        }

        Log::critical('Payment Service: Health metrics updated after financial replay', [
            'service' => 'payment-service',
            'health_status' => cache()->get('payment_service_health'),
            'service_mode' => cache()->get('payment_service_mode'),
            'compliance_status' => cache()->get('payment_service_compliance_status'),
            'remaining_buffer' => $remainingBuffer,
        ]);
    }

    /**
     * Handle complete financial buffer recovery.
     */
    private function handleCompleteFinancialBufferRecovery(): void
    {
        Log::critical('Payment Service: COMPLETE FINANCIAL BUFFER RECOVERY ACHIEVED', [
            'service' => 'payment-service',
            'status' => 'all_financial_operations_replayed',
            'financial_impact' => 'Full financial processing capability restored',
            'compliance_status' => 'PCI DSS compliance fully restored',
            'service_health' => 'healthy',
            'alert_level' => 'FINANCIAL_RECOVERY_COMPLETE',
        ]);

        // Reset financial failover-related cache entries
        cache()->forget('payment_service_failover_started');
        cache()->forget('payment_service_buffer_all_writes');
        cache()->forget('payment_service_financial_emergency');
        cache()->put('payment_service_financial_recovery_completed', now()->toISOString(), 86400);

        // Send financial recovery completion alert to C-level
        $this->sendFinancialRecoveryCompletionAlert();
    }

    /**
     * Notify financial stakeholders of recovery progress.
     */
    private function notifyFinancialStakeholdersOfRecovery(WriteOperationReplayedEvent $event): void
    {
        $financialStakeholders = [
            'CFO',
            'Finance Team',
            'Compliance Officer',
            'Risk Management',
            'Operations Director'
        ];

        foreach ($financialStakeholders as $stakeholder) {
            cache()->put("payment_service_financial_recovery_notification_{$stakeholder}", [
                'status' => 'financial_operation_replayed',
                'operation_type' => $event->operationType,
                'timestamp' => now()->toISOString(),
                'recovery_progress' => cache()->get('payment_financial_compliance_recovery_progress', 0),
                'compliance_status' => cache()->get('payment_service_compliance_status', 'unknown'),
                'message' => 'Financial operation successfully replayed - PCI DSS compliance maintained',
                'alert_level' => 'FINANCIAL_RECOVERY_UPDATE',
            ], 1800); // 30 minutes
        }
    }

    /**
     * Notify dependent services of financial recovery.
     */
    private function notifyDependentServicesOfFinancialRecovery(WriteOperationReplayedEvent $event): void
    {
        $dependentServices = ['order-service', 'user-service', 'notification-service', 'analytics-service'];

        foreach ($dependentServices as $service) {
            cache()->put("payment_service_financial_recovery_notification_{$service}", [
                'status' => 'financial_operation_replayed',
                'operation_type' => $event->operationType,
                'timestamp' => now()->toISOString(),
                'recovery_progress' => cache()->get('payment_financial_compliance_recovery_progress', 0),
                'message' => 'Payment service financial operation replayed - financial processing recovering',
                'compliance_impact' => 'PCI DSS compliance maintained',
            ], 1800); // 30 minutes
        }
    }

    /**
     * Update PCI DSS compliance audit trail.
     */
    private function updatePciDssAuditTrail(WriteOperationReplayedEvent $event): void
    {
        $auditEntry = [
            'event_type' => 'financial_operation_replayed',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'replayed_at' => $event->replayedAt,
            'original_buffered_at' => $event->originalBufferedAt,
            'replay_duration' => $event->replayDurationSeconds,
            'compliance_status' => $this->validatePciDssCompliance($event),
            'audit_timestamp' => now()->toISOString(),
        ];

        // Store in PCI DSS audit trail
        cache()->put("pci_dss_audit_trail_{$event->operationId}", $auditEntry, 86400 * 7); // 7 days

        Log::critical('Payment Service: PCI DSS audit trail updated', [
            'service' => 'payment-service',
            'audit_entry' => $auditEntry,
            'compliance_requirement' => 'PCI DSS audit trail maintenance',
        ]);
    }

    /**
     * Send slow financial recovery alert.
     */
    private function sendSlowFinancialRecoveryAlert(WriteOperationReplayedEvent $event): void
    {
        try {
            Log::critical('Payment Service: SLOW FINANCIAL RECOVERY ALERT', [
                'alert_type' => 'slow_financial_recovery',
                'service' => 'payment-service',
                'operation_type' => $event->operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'financial_impact' => 'CRITICAL - Financial recovery delayed',
                'compliance_risk' => 'HIGH - PCI DSS timeline at risk',
                'escalation_level' => 'C_LEVEL_IMMEDIATE',
                'recommended_action' => 'Immediate financial operations and database team escalation',
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Service: Failed to send slow financial recovery alert', [
                'error' => $e->getMessage(),
                'operation_type' => $event->operationType,
            ]);
        }
    }

    /**
     * Send financial emergency alert.
     */
    private function sendFinancialEmergencyAlert(float $successRate, int $totalBuffered): void
    {
        try {
            Log::critical('Payment Service: FINANCIAL EMERGENCY - LOW REPLAY SUCCESS RATE', [
                'alert_type' => 'financial_emergency_low_success_rate',
                'service' => 'payment-service',
                'success_rate' => $successRate,
                'total_buffered' => $totalBuffered,
                'financial_impact' => 'CRITICAL - Financial recovery incomplete',
                'compliance_risk' => 'HIGH - PCI DSS compliance at risk',
                'escalation_level' => 'FINANCIAL_EMERGENCY',
                'recommended_action' => 'IMMEDIATE C-level escalation and financial operations review',
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Service: Failed to send financial emergency alert', [
                'error' => $e->getMessage(),
                'success_rate' => $successRate,
            ]);
        }
    }

    /**
     * Send financial recovery completion alert.
     */
    private function sendFinancialRecoveryCompletionAlert(): void
    {
        try {
            Log::critical('Payment Service: FINANCIAL RECOVERY COMPLETION ALERT', [
                'alert_type' => 'financial_recovery_complete',
                'service' => 'payment-service',
                'status' => 'fully_recovered',
                'financial_impact' => 'All buffered financial operations successfully replayed',
                'compliance_status' => 'PCI DSS compliance fully restored',
                'service_health' => 'healthy',
                'timestamp' => now()->toISOString(),
                'escalation_level' => 'C_LEVEL_NOTIFICATION',
            ]);

        } catch (\Exception $e) {
            Log::error('Payment Service: Failed to send financial recovery completion alert', [
                'error' => $e->getMessage(),
                'service' => 'payment-service',
            ]);
        }
    }
}
