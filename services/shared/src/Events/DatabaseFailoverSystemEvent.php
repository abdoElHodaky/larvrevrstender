<?php

namespace Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * DatabaseFailoverSystemEvent
 * 
 * Comprehensive event class for database failover system events.
 * This event is dispatched for all types of database failover events,
 * allowing for real-time coordination and response across the system.
 */
class DatabaseFailoverSystemEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Event type
     */
    public string $eventType;

    /**
     * Event context data
     */
    public array $context;

    /**
     * Event timestamp
     */
    public string $timestamp;

    /**
     * Event severity level
     */
    public string $severity;

    /**
     * Service name that triggered the event
     */
    public string $serviceName;

    /**
     * Create a new event instance
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @param string $severity Event severity
     * @param string $serviceName Service name
     */
    public function __construct(string $eventType, array $context, string $severity = 'medium', string $serviceName = 'unknown')
    {
        $this->eventType = $eventType;
        $this->context = $context;
        $this->severity = $severity;
        $this->serviceName = $serviceName;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get event data as array
     *
     * @return array Event data
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'context' => $this->context,
            'severity' => $this->severity,
            'service_name' => $this->serviceName,
            'timestamp' => $this->timestamp
        ];
    }

    /**
     * Check if event is critical
     *
     * @return bool Whether event is critical
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    /**
     * Check if event requires immediate attention
     *
     * @return bool Whether event requires immediate attention
     */
    public function requiresImmediateAttention(): bool
    {
        return in_array($this->severity, ['critical', 'high']);
    }

    /**
     * Get event priority for processing
     *
     * @return int Priority level (1 = highest, 5 = lowest)
     */
    public function getPriority(): int
    {
        return match($this->severity) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            'info' => 5,
            default => 3
        };
    }

    /**
     * Check if event should trigger email notifications
     *
     * @return bool Whether to send email notifications
     */
    public function shouldNotifyByEmail(): bool
    {
        $emailEvents = [
            'split_brain_detected',
            'graceful_degradation_unavailable',
            'failover_attempt_failed',
            'query_circuit_breaker_open',
            'transaction_circuit_breaker_open',
            'connection_health_check_failed',
            'data_consistency_issues_detected',
            'database_topology_mapping_completed'
        ];

        return in_array($this->eventType, $emailEvents);
    }

    /**
     * Check if event should trigger circuit breaker actions
     *
     * @return bool Whether to trigger circuit breaker actions
     */
    public function shouldTriggerCircuitBreakerActions(): bool
    {
        $circuitBreakerEvents = [
            'split_brain_detected',
            'connection_health_check_failed',
            'failover_attempt_failed',
            'query_circuit_breaker_open',
            'transaction_circuit_breaker_open'
        ];

        return in_array($this->eventType, $circuitBreakerEvents);
    }

    /**
     * Check if event should trigger parameter tuning
     *
     * @return bool Whether to trigger parameter tuning
     */
    public function shouldTriggerParameterTuning(): bool
    {
        $tuningEvents = [
            'query_circuit_breaker_open',
            'transaction_circuit_breaker_open',
            'connection_health_check_failed',
            'database_topology_mapping_completed'
        ];

        return in_array($this->eventType, $tuningEvents);
    }

    /**
     * Get affected connections from context
     *
     * @return array Affected connections
     */
    public function getAffectedConnections(): array
    {
        $connections = [];

        // Extract connections from various context fields
        if (isset($this->context['connection'])) {
            $connections[] = $this->context['connection'];
        }

        if (isset($this->context['current_connection'])) {
            $connections[] = $this->context['current_connection'];
        }

        if (isset($this->context['target_connection'])) {
            $connections[] = $this->context['target_connection'];
        }

        if (isset($this->context['multiple_writers'])) {
            $connections = array_merge($connections, $this->context['multiple_writers']);
        }

        if (isset($this->context['connections_checked'])) {
            $connections = array_merge($connections, $this->context['connections_checked']);
        }

        return array_unique(array_filter($connections));
    }

    /**
     * Get event description for logging
     *
     * @return string Event description
     */
    public function getDescription(): string
    {
        return match($this->eventType) {
            'split_brain_detected' => 'Split-brain condition detected - multiple database writers active',
            'graceful_degradation_unavailable' => 'Database service completely unavailable - no graceful degradation possible',
            'failover_attempt_failed' => 'Database failover attempt failed - manual intervention required',
            'query_circuit_breaker_open' => 'Database query circuit breaker opened due to failures',
            'transaction_circuit_breaker_open' => 'Database transaction circuit breaker opened due to failures',
            'connection_health_check_failed' => 'Database connection health check failed',
            'data_consistency_issues_detected' => 'Data consistency issues detected between connections',
            'database_topology_mapping_completed' => 'Database topology mapping completed successfully',
            'circuit_breaker_parameter_tuning_completed' => 'Circuit breaker parameter tuning completed',
            'email_notification_sent' => 'Email notification sent successfully',
            default => "Database failover event: {$this->eventType}"
        };
    }

    /**
     * Get recommended actions for this event
     *
     * @return array Recommended actions
     */
    public function getRecommendedActions(): array
    {
        return match($this->eventType) {
            'split_brain_detected' => [
                'Stop all write operations immediately',
                'Identify conflicting database connections',
                'Resolve data conflicts manually',
                'Restart services with proper configuration'
            ],
            'graceful_degradation_unavailable' => [
                'Check database server status',
                'Verify network connectivity',
                'Review database logs',
                'Consider manual failover'
            ],
            'failover_attempt_failed' => [
                'Check backup database status',
                'Verify failover configuration',
                'Test manual connection',
                'Review failover logs'
            ],
            'query_circuit_breaker_open' => [
                'Check database performance',
                'Verify network connectivity',
                'Review recent queries',
                'Monitor for recovery'
            ],
            'transaction_circuit_breaker_open' => [
                'Check database performance',
                'Review transaction timeouts',
                'Check for deadlocks',
                'Monitor for recovery'
            ],
            default => [
                'Review event details',
                'Check system logs',
                'Monitor system performance',
                'Escalate if issues persist'
            ]
        };
    }
}
