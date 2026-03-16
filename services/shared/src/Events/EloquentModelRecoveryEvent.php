<?php

namespace Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Eloquent Model Recovery Event
 * 
 * This event is fired when an Eloquent model recovers from database failover scenarios.
 * Provides recovery-specific context and success metrics for monitoring and optimization.
 * 
 * Modern PHP 8.3 & Laravel 12 implementation with comprehensive recovery tracking.
 */
class EloquentModelRecoveryEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new eloquent model recovery event instance.
     * Modern PHP 8.3 constructor property promotion with typed parameters
     *
     * @param string $modelClass The fully qualified model class name
     * @param string $tableName The database table name
     * @param string $recoveryType The type of recovery that occurred
     * @param bool $recoverySuccess Whether the recovery was successful
     * @param float $recoveryDuration The time taken for recovery in milliseconds
     * @param array $recoveryActions The actions taken during recovery
     * @param array $context Additional context information
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $tableName,
        public readonly string $recoveryType,
        public readonly bool $recoverySuccess,
        public readonly float $recoveryDuration,
        public readonly array $recoveryActions = [],
        public readonly array $context = []
    ) {
        // Add recovery-specific timestamp and metadata
        $this->context['recovery_timestamp'] = microtime(true);
        $this->context['recovery_occurred_at'] = now()->toISOString();
        $this->context['model_name'] = class_basename($this->modelClass);
    }

    /**
     * Get the model name (short class name)
     * Modern PHP 8.3 implementation
     */
    public function getModelName(): string
    {
        return class_basename($this->modelClass);
    }

    /**
     * Get the recovery success rate as a percentage
     * Modern PHP 8.3 implementation with type safety
     */
    public function getRecoverySuccessRate(): float
    {
        return $this->recoverySuccess ? 100.0 : 0.0;
    }

    /**
     * Get the recovery performance rating
     * PHP 8.3 match expressions for performance assessment
     */
    public function getRecoveryPerformanceRating(): string
    {
        $durationSeconds = $this->recoveryDuration / 1000;
        
        return match(true) {
            $durationSeconds <= 5 => 'excellent',
            $durationSeconds <= 15 => 'good',
            $durationSeconds <= 60 => 'acceptable',
            $durationSeconds <= 300 => 'slow',
            default => 'very_slow'
        };
    }

    /**
     * Get the recovery impact assessment
     * Modern PHP 8.3 match expressions with model-specific impact analysis
     */
    public function getRecoveryImpact(): string
    {
        $modelName = $this->getModelName();
        $success = $this->recoverySuccess;
        $performance = $this->getRecoveryPerformanceRating();
        
        return match(true) {
            !$success && in_array($modelName, ['User', 'Auction']) => 'critical_failure',
            !$success => 'recovery_failure',
            $success && $performance === 'excellent' => 'minimal_impact',
            $success && $performance === 'good' => 'low_impact',
            $success && $performance === 'acceptable' => 'moderate_impact',
            $success && $performance === 'slow' => 'high_impact',
            default => 'severe_impact'
        };
    }

    /**
     * Get the recovery type priority
     * PHP 8.3 match expressions for priority assessment
     */
    public function getRecoveryPriority(): int
    {
        return match($this->recoveryType) {
            'emergency_recovery' => 1,
            'connection_restored' => 2,
            'circuit_breaker_closed' => 3,
            'failback_complete' => 4,
            'partial_recovery' => 5,
            'gradual_recovery' => 6,
            default => 7
        };
    }

    /**
     * Get recommended post-recovery actions
     * Modern PHP 8.3 match expressions with comprehensive action mapping
     */
    public function getPostRecoveryActions(): array
    {
        $modelName = $this->getModelName();
        $recoveryType = $this->recoveryType;
        $success = $this->recoverySuccess;
        
        $baseActions = match(true) {
            !$success => [
                'investigate_recovery_failure',
                'escalate_to_engineering_team',
                'implement_emergency_procedures',
                'notify_stakeholders'
            ],
            $recoveryType === 'emergency_recovery' => [
                'validate_data_integrity',
                'perform_comprehensive_health_check',
                'update_monitoring_thresholds',
                'document_incident_details'
            ],
            $recoveryType === 'connection_restored' => [
                'test_all_model_operations',
                'clear_cached_failover_state',
                'resume_normal_monitoring',
                'update_health_dashboards'
            ],
            $recoveryType === 'circuit_breaker_closed' => [
                'reset_failure_counters',
                'validate_circuit_breaker_thresholds',
                'resume_normal_operations',
                'monitor_success_rates'
            ],
            default => [
                'monitor_model_performance',
                'validate_recovery_success',
                'update_recovery_metrics',
                'continue_normal_operations'
            ]
        };

        // Add model-specific post-recovery actions
        $modelSpecificActions = match($modelName) {
            'User' => [
                'validate_user_authentication',
                'check_session_integrity',
                'verify_user_data_consistency'
            ],
            'Auction' => [
                'validate_auction_states',
                'check_bidding_integrity',
                'verify_auction_timers'
            ],
            'BusinessMetric' => [
                'validate_metric_calculations',
                'check_reporting_accuracy',
                'verify_analytics_consistency'
            ],
            'UserAnalytic' => [
                'validate_event_tracking',
                'check_analytics_pipeline',
                'verify_data_completeness'
            ],
            default => []
        };

        return array_merge($baseActions, $modelSpecificActions);
    }

    /**
     * Get recovery quality metrics
     * Modern PHP 8.3 implementation with comprehensive quality assessment
     */
    public function getRecoveryQualityMetrics(): array
    {
        return [
            'success_rate' => $this->getRecoverySuccessRate(),
            'performance_rating' => $this->getRecoveryPerformanceRating(),
            'recovery_impact' => $this->getRecoveryImpact(),
            'duration_seconds' => round($this->recoveryDuration / 1000, 2),
            'actions_executed' => count($this->recoveryActions),
            'actions_successful' => $this->countSuccessfulActions(),
            'action_success_rate' => $this->getActionSuccessRate(),
            'recovery_efficiency' => $this->calculateRecoveryEfficiency(),
        ];
    }

    /**
     * Get the event data for monitoring and logging
     * Modern PHP 8.3 implementation with comprehensive data structure
     */
    public function getMonitoringData(): array
    {
        return [
            'model_class' => $this->modelClass,
            'model_name' => $this->getModelName(),
            'table_name' => $this->tableName,
            'recovery_type' => $this->recoveryType,
            'recovery_success' => $this->recoverySuccess,
            'recovery_duration_ms' => $this->recoveryDuration,
            'recovery_duration_seconds' => round($this->recoveryDuration / 1000, 2),
            'recovery_actions' => $this->recoveryActions,
            'recovery_priority' => $this->getRecoveryPriority(),
            'recovery_impact' => $this->getRecoveryImpact(),
            'performance_rating' => $this->getRecoveryPerformanceRating(),
            'quality_metrics' => $this->getRecoveryQualityMetrics(),
            'post_recovery_actions' => $this->getPostRecoveryActions(),
            'context' => $this->context,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get metrics for monitoring systems
     * Modern implementation with recovery-specific metrics
     */
    public function getMetrics(): array
    {
        return [
            'model_recovery_count' => 1,
            'model_name' => $this->getModelName(),
            'recovery_type' => $this->recoveryType,
            'recovery_success' => $this->recoverySuccess ? 1 : 0,
            'recovery_duration_ms' => $this->recoveryDuration,
            'recovery_priority' => $this->getRecoveryPriority(),
            'performance_score' => $this->getPerformanceScore(),
            'impact_level' => $this->getImpactLevel(),
            'actions_count' => count($this->recoveryActions),
            'success_rate' => $this->getRecoverySuccessRate(),
            'efficiency_score' => $this->calculateRecoveryEfficiency(),
        ];
    }

    /**
     * Check if this is a critical recovery
     * Modern PHP 8.3 boolean logic
     */
    public function isCriticalRecovery(): bool
    {
        return match(true) {
            $this->recoveryType === 'emergency_recovery' => true,
            !$this->recoverySuccess => true,
            $this->getRecoveryPriority() <= 2 => true,
            in_array($this->getModelName(), ['User', 'Auction']) && !$this->recoverySuccess => true,
            default => false
        };
    }

    /**
     * Check if recovery requires follow-up actions
     * PHP 8.3 match expressions for follow-up assessment
     */
    public function requiresFollowUp(): bool
    {
        return match(true) {
            !$this->recoverySuccess => true,
            $this->getRecoveryImpact() === 'critical_failure' => true,
            $this->getRecoveryPerformanceRating() === 'very_slow' => true,
            $this->getActionSuccessRate() < 80.0 => true,
            count($this->getPostRecoveryActions()) > 5 => true,
            default => false
        };
    }

    /**
     * Get a human-readable description of the recovery
     * Modern string interpolation and formatting
     */
    public function getDescription(): string
    {
        $modelName = $this->getModelName();
        $type = $this->recoveryType;
        $status = $this->recoverySuccess ? 'successful' : 'failed';
        $duration = round($this->recoveryDuration / 1000, 2);
        $impact = $this->getRecoveryImpact();
        
        return "Model {$modelName} (table: {$this->tableName}) {$type} recovery {$status}. " .
               "Duration: {$duration}s. Impact: {$impact}. " .
               "Performance: {$this->getRecoveryPerformanceRating()}.";
    }

    /**
     * Get recovery lessons learned
     * Modern PHP 8.3 implementation for continuous improvement
     */
    public function getRecoveryLessons(): array
    {
        $performance = $this->getRecoveryPerformanceRating();
        $success = $this->recoverySuccess;
        $modelName = $this->getModelName();
        
        $lessons = [];
        
        if (!$success) {
            $lessons[] = "Recovery failed for {$modelName} - investigate root cause";
            $lessons[] = "Review recovery procedures for {$this->recoveryType}";
            $lessons[] = "Consider implementing additional failsafe mechanisms";
        }
        
        if ($performance === 'very_slow' || $performance === 'slow') {
            $lessons[] = "Recovery took longer than expected - optimize recovery procedures";
            $lessons[] = "Consider pre-warming connections or caches";
            $lessons[] = "Review circuit breaker thresholds and timeouts";
        }
        
        if ($this->getActionSuccessRate() < 90.0) {
            $lessons[] = "Some recovery actions failed - review action reliability";
            $lessons[] = "Consider implementing retry mechanisms for recovery actions";
        }
        
        if ($performance === 'excellent' && $success) {
            $lessons[] = "Recovery performed excellently - document best practices";
            $lessons[] = "Consider applying similar patterns to other models";
        }
        
        return $lessons;
    }

    /**
     * Private helper methods
     */
    private function countSuccessfulActions(): int
    {
        return collect($this->recoveryActions)
            ->filter(fn($action) => ($action['success'] ?? false) === true)
            ->count();
    }

    private function getActionSuccessRate(): float
    {
        $totalActions = count($this->recoveryActions);
        if ($totalActions === 0) {
            return 100.0;
        }
        
        $successfulActions = $this->countSuccessfulActions();
        return ($successfulActions / $totalActions) * 100.0;
    }

    private function calculateRecoveryEfficiency(): float
    {
        $baseScore = $this->recoverySuccess ? 50.0 : 0.0;
        $performanceScore = match($this->getRecoveryPerformanceRating()) {
            'excellent' => 30.0,
            'good' => 25.0,
            'acceptable' => 15.0,
            'slow' => 5.0,
            default => 0.0
        };
        $actionScore = ($this->getActionSuccessRate() / 100.0) * 20.0;
        
        return min(100.0, $baseScore + $performanceScore + $actionScore);
    }

    private function getPerformanceScore(): int
    {
        return match($this->getRecoveryPerformanceRating()) {
            'excellent' => 5,
            'good' => 4,
            'acceptable' => 3,
            'slow' => 2,
            'very_slow' => 1,
            default => 0
        };
    }

    private function getImpactLevel(): int
    {
        return match($this->getRecoveryImpact()) {
            'minimal_impact' => 1,
            'low_impact' => 2,
            'moderate_impact' => 3,
            'high_impact' => 4,
            'severe_impact' => 5,
            'recovery_failure' => 6,
            'critical_failure' => 7,
            default => 0
        };
    }
}
