<?php

namespace Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Shared\Events\DatabaseFailoverEvent;

/**
 * Eloquent Model Failover Event
 * 
 * This event is fired when a specific Eloquent model encounters database failover scenarios.
 * Provides model-specific context and failover strategy information for targeted handling.
 * 
 * Modern PHP 8.3 & Laravel 12 implementation with comprehensive event data.
 */
class EloquentModelFailoverEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new eloquent model failover event instance.
     * Modern PHP 8.3 constructor property promotion with typed parameters
     *
     * @param string $modelClass The fully qualified model class name
     * @param string $tableName The database table name
     * @param string $failoverStrategy The failover strategy being applied
     * @param DatabaseFailoverEvent $originalEvent The original database failover event
     * @param array $context Additional context information
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $tableName,
        public readonly string $failoverStrategy,
        public readonly DatabaseFailoverEvent $originalEvent,
        public readonly array $context = []
    ) {
        // Add model-specific timestamp and metadata
        $this->context['model_failover_timestamp'] = microtime(true);
        $this->context['model_failover_occurred_at'] = now()->toISOString();
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
     * Get the failover severity specific to this model
     * PHP 8.3 match expressions for model-specific severity assessment
     */
    public function getModelSpecificSeverity(): string
    {
        $modelName = $this->getModelName();
        $originalSeverity = $this->originalEvent->getSeverity();
        
        return match(true) {
            // Critical models that require immediate attention
            $modelName === 'User' && $originalSeverity === 'critical' => 'business_critical',
            $modelName === 'Auction' && $originalSeverity === 'critical' => 'revenue_critical',
            $modelName === 'Order' && $originalSeverity === 'critical' => 'transaction_critical',
            
            // Important models with high impact
            $modelName === 'User' && $originalSeverity === 'warning' => 'user_impact_high',
            $modelName === 'Auction' && $originalSeverity === 'warning' => 'business_impact_high',
            
            // Analytics models with lower immediate impact
            $modelName === 'BusinessMetric' => 'analytics_impact',
            $modelName === 'UserAnalytic' => 'tracking_impact',
            
            // Default to original severity
            default => $originalSeverity
        };
    }

    /**
     * Get the estimated recovery time for this specific model
     * Modern PHP 8.3 match expressions with model-specific timing
     */
    public function getEstimatedRecoveryTime(): string
    {
        $modelName = $this->getModelName();
        $strategy = $this->failoverStrategy;
        
        return match(true) {
            $strategy === 'emergency_mode' && in_array($modelName, ['User', 'Auction']) => '30-60 minutes',
            $strategy === 'emergency_mode' => '15-30 minutes',
            $strategy === 'degraded_mode' && in_array($modelName, ['User', 'Auction']) => '10-20 minutes',
            $strategy === 'degraded_mode' => '5-15 minutes',
            $strategy === 'recovery_mode' => '2-10 minutes',
            $strategy === 'normal_failover' => '30 seconds - 5 minutes',
            default => 'unknown'
        };
    }

    /**
     * Get the priority level for handling this model's failover
     * PHP 8.3 match expressions for priority assessment
     */
    public function getHandlingPriority(): int
    {
        $modelName = $this->getModelName();
        $severity = $this->getModelSpecificSeverity();
        
        return match(true) {
            $severity === 'business_critical' => 1,
            $severity === 'revenue_critical' => 1,
            $severity === 'transaction_critical' => 1,
            $severity === 'user_impact_high' => 2,
            $severity === 'business_impact_high' => 2,
            $modelName === 'User' => 3,
            $modelName === 'Auction' => 3,
            $severity === 'analytics_impact' => 4,
            $severity === 'tracking_impact' => 5,
            default => 6
        };
    }

    /**
     * Get affected model operations based on failover strategy
     * Modern Laravel 12 collection methods for operation mapping
     */
    public function getAffectedOperations(): array
    {
        return match($this->failoverStrategy) {
            'emergency_mode' => [
                'create' => 'blocked',
                'read' => 'cache_only',
                'update' => 'blocked',
                'delete' => 'blocked',
                'bulk_operations' => 'blocked',
                'relationships' => 'cache_only'
            ],
            'degraded_mode' => [
                'create' => 'delayed',
                'read' => 'secondary_db',
                'update' => 'delayed',
                'delete' => 'delayed',
                'bulk_operations' => 'queued',
                'relationships' => 'lazy_loading'
            ],
            'recovery_mode' => [
                'create' => 'testing',
                'read' => 'primary_db',
                'update' => 'testing',
                'delete' => 'testing',
                'bulk_operations' => 'limited',
                'relationships' => 'eager_loading'
            ],
            'normal_failover' => [
                'create' => 'normal',
                'read' => 'normal',
                'update' => 'normal',
                'delete' => 'normal',
                'bulk_operations' => 'normal',
                'relationships' => 'normal'
            ],
            default => []
        };
    }

    /**
     * Get recommended actions for this model's failover
     * PHP 8.3 match expressions with comprehensive action mapping
     */
    public function getRecommendedActions(): array
    {
        $modelName = $this->getModelName();
        $strategy = $this->failoverStrategy;
        
        $baseActions = match($strategy) {
            'emergency_mode' => [
                'enable_read_only_mode',
                'activate_cache_fallback',
                'notify_administrators',
                'escalate_to_emergency_team'
            ],
            'degraded_mode' => [
                'switch_to_secondary_db',
                'increase_cache_ttl',
                'enable_eventual_consistency',
                'monitor_performance_metrics'
            ],
            'recovery_mode' => [
                'test_connection_health',
                'gradually_restore_operations',
                'validate_data_consistency',
                'update_monitoring_dashboards'
            ],
            'normal_failover' => [
                'switch_connection',
                'update_health_checks',
                'log_failover_metrics',
                'continue_normal_operations'
            ],
            default => ['monitor_and_log']
        };

        // Add model-specific actions
        $modelSpecificActions = match($modelName) {
            'User' => [
                'preserve_user_sessions',
                'maintain_authentication_state',
                'notify_user_service'
            ],
            'Auction' => [
                'preserve_bidding_state',
                'maintain_auction_timers',
                'notify_bidding_service'
            ],
            'BusinessMetric' => [
                'enable_metric_buffering',
                'switch_to_eventual_consistency',
                'maintain_reporting_cache'
            ],
            'UserAnalytic' => [
                'enable_event_buffering',
                'maintain_analytics_queue',
                'preserve_tracking_state'
            ],
            default => []
        };

        return array_merge($baseActions, $modelSpecificActions);
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
            'failover_strategy' => $this->failoverStrategy,
            'model_specific_severity' => $this->getModelSpecificSeverity(),
            'handling_priority' => $this->getHandlingPriority(),
            'estimated_recovery_time' => $this->getEstimatedRecoveryTime(),
            'affected_operations' => $this->getAffectedOperations(),
            'recommended_actions' => $this->getRecommendedActions(),
            'original_event_data' => $this->originalEvent->getTelescopeData(),
            'context' => $this->context,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get metrics for monitoring systems
     * Modern implementation with model-specific metrics
     */
    public function getMetrics(): array
    {
        return [
            'model_failover_count' => 1,
            'model_name' => $this->getModelName(),
            'failover_strategy' => $this->failoverStrategy,
            'severity_level' => $this->getSeverityLevel(),
            'priority_level' => $this->getHandlingPriority(),
            'estimated_recovery_minutes' => $this->getRecoveryMinutes(),
            'operations_affected_count' => count($this->getAffectedOperations()),
            'actions_required_count' => count($this->getRecommendedActions()),
            'business_impact_score' => $this->getBusinessImpactScore(),
        ];
    }

    /**
     * Check if this is a critical model failover
     * Modern PHP 8.3 boolean logic
     */
    public function isCriticalModelFailover(): bool
    {
        return in_array($this->getModelSpecificSeverity(), [
            'business_critical',
            'revenue_critical',
            'transaction_critical'
        ]);
    }

    /**
     * Check if immediate action is required
     * PHP 8.3 match expressions for urgency assessment
     */
    public function requiresImmediateAction(): bool
    {
        return match(true) {
            $this->isCriticalModelFailover() => true,
            $this->getHandlingPriority() <= 2 => true,
            $this->failoverStrategy === 'emergency_mode' => true,
            default => false
        };
    }

    /**
     * Get a human-readable description of the model failover
     * Modern string interpolation and formatting
     */
    public function getDescription(): string
    {
        $modelName = $this->getModelName();
        $strategy = $this->failoverStrategy;
        $severity = $this->getModelSpecificSeverity();
        $recoveryTime = $this->getEstimatedRecoveryTime();
        
        return "Model {$modelName} (table: {$this->tableName}) experiencing {$severity} failover. " .
               "Strategy: {$strategy}. Estimated recovery: {$recoveryTime}. " .
               "Priority: {$this->getHandlingPriority()}.";
    }

    /**
     * Private helper methods
     */
    private function getSeverityLevel(): int
    {
        return match($this->getModelSpecificSeverity()) {
            'business_critical', 'revenue_critical', 'transaction_critical' => 5,
            'user_impact_high', 'business_impact_high' => 4,
            'critical' => 3,
            'warning' => 2,
            'info' => 1,
            default => 0
        };
    }

    private function getRecoveryMinutes(): int
    {
        $timeString = $this->getEstimatedRecoveryTime();
        
        return match(true) {
            str_contains($timeString, '30-60 minutes') => 45,
            str_contains($timeString, '15-30 minutes') => 22,
            str_contains($timeString, '10-20 minutes') => 15,
            str_contains($timeString, '5-15 minutes') => 10,
            str_contains($timeString, '2-10 minutes') => 6,
            str_contains($timeString, '30 seconds - 5 minutes') => 3,
            default => 0
        };
    }

    private function getBusinessImpactScore(): int
    {
        $modelName = $this->getModelName();
        $severity = $this->getModelSpecificSeverity();
        
        return match(true) {
            $modelName === 'Auction' && $severity === 'revenue_critical' => 10,
            $modelName === 'User' && $severity === 'business_critical' => 9,
            $modelName === 'Order' && $severity === 'transaction_critical' => 9,
            $severity === 'user_impact_high' => 7,
            $severity === 'business_impact_high' => 7,
            $modelName === 'Auction' => 6,
            $modelName === 'User' => 5,
            $severity === 'analytics_impact' => 3,
            $severity === 'tracking_impact' => 2,
            default => 1
        };
    }
}
