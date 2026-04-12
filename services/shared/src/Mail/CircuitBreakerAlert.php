<?php

namespace Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * CircuitBreakerAlert
 * 
 * Mailable class for circuit breaker alert notifications.
 * Handles circuit breaker events like circuit opens, state changes,
 * and performance degradation alerts.
 */
class CircuitBreakerAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Notification data
     */
    public array $notificationData;

    /**
     * Notification ID for tracking
     */
    public string $notificationId;

    /**
     * Create a new message instance
     *
     * @param array $notificationData Notification data
     * @param string $notificationId Notification ID
     */
    public function __construct(array $notificationData, string $notificationId)
    {
        $this->notificationData = $notificationData;
        $this->notificationId = $notificationId;
        
        // Set queue for async processing
        $this->onQueue('emails');
    }

    /**
     * Build the message
     *
     * @return $this
     */
    public function build()
    {
        $eventType = $this->notificationData['event_type'];
        $severity = $this->notificationData['severity'];
        $context = $this->notificationData['context'];
        
        return $this->subject($this->notificationData['subject'])
                    ->view('emails.circuit-breaker.alert')
                    ->with([
                        'eventType' => $eventType,
                        'severity' => $severity,
                        'context' => $context,
                        'notificationId' => $this->notificationId,
                        'timestamp' => $this->notificationData['timestamp'],
                        'environment' => $this->notificationData['environment'],
                        'serviceName' => $this->notificationData['service_name'],
                        'circuitDetails' => $this->formatCircuitDetails($eventType, $context),
                        'performanceMetrics' => $this->extractPerformanceMetrics($context),
                        'recommendations' => $this->generateRecommendations($eventType, $context),
                        'severityColor' => $this->getSeverityColor($severity),
                        'severityIcon' => $this->getSeverityIcon($severity),
                        'circuitHealthStatus' => $this->determineCircuitHealth($context)
                    ]);
    }

    /**
     * Format circuit breaker details based on event type
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return array Formatted circuit details
     */
    private function formatCircuitDetails(string $eventType, array $context): array
    {
        $circuitName = $context['circuit_name'] ?? 'Unknown Circuit';
        $connection = $context['connection'] ?? 'Unknown Connection';
        $serviceName = $context['service_name'] ?? 'Unknown Service';

        switch ($eventType) {
            case 'query_circuit_breaker_open':
                return [
                    'title' => 'Database Query Circuit Breaker Opened',
                    'description' => 'A database query circuit breaker has opened due to repeated failures.',
                    'circuit_name' => $circuitName,
                    'connection' => $connection,
                    'service' => $serviceName,
                    'failure_count' => $context['failure_count'] ?? 0,
                    'circuit_state' => $context['circuit_state'] ?? 'open',
                    'risk_level' => 'HIGH - Database queries being blocked',
                    'impact' => 'Database operations for this service are currently blocked'
                ];

            case 'transaction_circuit_breaker_open':
                return [
                    'title' => 'Database Transaction Circuit Breaker Opened',
                    'description' => 'A database transaction circuit breaker has opened due to repeated failures.',
                    'circuit_name' => $circuitName,
                    'connection' => $connection,
                    'service' => $serviceName,
                    'failure_count' => $context['failure_count'] ?? 0,
                    'circuit_state' => $context['circuit_state'] ?? 'open',
                    'risk_level' => 'CRITICAL - Database transactions being blocked',
                    'impact' => 'All database transactions for this service are currently blocked'
                ];

            case 'circuit_breaker_half_open':
                return [
                    'title' => 'Circuit Breaker Testing Recovery',
                    'description' => 'Circuit breaker is in half-open state, testing if the service has recovered.',
                    'circuit_name' => $circuitName,
                    'connection' => $connection,
                    'service' => $serviceName,
                    'circuit_state' => 'half-open',
                    'risk_level' => 'MEDIUM - Recovery testing in progress',
                    'impact' => 'Limited operations allowed while testing recovery'
                ];

            case 'circuit_breaker_closed':
                return [
                    'title' => 'Circuit Breaker Recovered',
                    'description' => 'Circuit breaker has successfully recovered and is now closed.',
                    'circuit_name' => $circuitName,
                    'connection' => $connection,
                    'service' => $serviceName,
                    'circuit_state' => 'closed',
                    'risk_level' => 'INFO - Normal operations resumed',
                    'impact' => 'Normal database operations have been restored'
                ];

            default:
                return [
                    'title' => 'Circuit Breaker Event',
                    'description' => 'A circuit breaker event has occurred.',
                    'circuit_name' => $circuitName,
                    'connection' => $connection,
                    'service' => $serviceName,
                    'event_type' => $eventType,
                    'risk_level' => 'MEDIUM - Review required',
                    'impact' => 'Potential impact on database operations'
                ];
        }
    }

    /**
     * Extract performance metrics from context
     *
     * @param array $context Event context
     * @return array Performance metrics
     */
    private function extractPerformanceMetrics(array $context): array
    {
        return [
            'failure_count' => $context['failure_count'] ?? 0,
            'success_count' => $context['success_count'] ?? 0,
            'failure_rate' => $this->calculateFailureRate($context),
            'average_response_time' => $context['average_response_time_ms'] ?? 'N/A',
            'last_failure_time' => $context['last_failure_time'] ?? 'N/A',
            'circuit_open_duration' => $this->calculateOpenDuration($context),
            'recovery_attempts' => $context['recovery_attempts'] ?? 0
        ];
    }

    /**
     * Calculate failure rate
     *
     * @param array $context Event context
     * @return string Failure rate percentage
     */
    private function calculateFailureRate(array $context): string
    {
        $failures = $context['failure_count'] ?? 0;
        $successes = $context['success_count'] ?? 0;
        $total = $failures + $successes;
        
        if ($total === 0) {
            return 'N/A';
        }
        
        $rate = ($failures / $total) * 100;
        return number_format($rate, 1) . '%';
    }

    /**
     * Calculate circuit open duration
     *
     * @param array $context Event context
     * @return string Duration
     */
    private function calculateOpenDuration(array $context): string
    {
        if (!isset($context['circuit_opened_at'])) {
            return 'N/A';
        }
        
        $openedAt = new \DateTime($context['circuit_opened_at']);
        $now = new \DateTime();
        $interval = $now->diff($openedAt);
        
        if ($interval->h > 0) {
            return $interval->format('%h hours, %i minutes');
        } elseif ($interval->i > 0) {
            return $interval->format('%i minutes, %s seconds');
        } else {
            return $interval->format('%s seconds');
        }
    }

    /**
     * Generate recommendations based on event type
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return array Recommendations
     */
    private function generateRecommendations(string $eventType, array $context): array
    {
        $failureCount = $context['failure_count'] ?? 0;
        $circuitName = $context['circuit_name'] ?? 'Unknown';

        switch ($eventType) {
            case 'query_circuit_breaker_open':
            case 'transaction_circuit_breaker_open':
                $recommendations = [
                    '1. Check database server health and performance',
                    '2. Verify network connectivity to the database',
                    '3. Review recent database queries for performance issues',
                    '4. Check database server logs for errors or warnings'
                ];

                if ($failureCount > 10) {
                    $recommendations[] = '5. Consider increasing circuit breaker failure threshold';
                    $recommendations[] = '6. Investigate potential database server overload';
                }

                if (str_contains($circuitName, 'transaction')) {
                    $recommendations[] = '7. Review transaction timeout settings';
                    $recommendations[] = '8. Check for database deadlocks or long-running transactions';
                }

                return $recommendations;

            case 'circuit_breaker_half_open':
                return [
                    '1. Monitor the recovery testing closely',
                    '2. Be prepared for potential re-opening if tests fail',
                    '3. Check database performance during recovery testing',
                    '4. Verify that underlying issues have been resolved'
                ];

            case 'circuit_breaker_closed':
                return [
                    '1. Monitor system performance to ensure stability',
                    '2. Review what caused the original circuit opening',
                    '3. Consider implementing preventive measures',
                    '4. Update monitoring thresholds if necessary'
                ];

            default:
                return [
                    '1. Review circuit breaker configuration',
                    '2. Monitor system performance and stability',
                    '3. Check for any underlying issues',
                    '4. Consider adjusting circuit breaker parameters if needed'
                ];
        }
    }

    /**
     * Determine circuit health status
     *
     * @param array $context Event context
     * @return array Health status
     */
    private function determineCircuitHealth(array $context): array
    {
        $failureCount = $context['failure_count'] ?? 0;
        $circuitState = $context['circuit_state'] ?? 'unknown';
        
        if ($circuitState === 'open') {
            $health = 'unhealthy';
            $status = 'Circuit is open - blocking requests';
        } elseif ($circuitState === 'half-open') {
            $health = 'recovering';
            $status = 'Circuit is testing recovery';
        } elseif ($circuitState === 'closed' && $failureCount === 0) {
            $health = 'healthy';
            $status = 'Circuit is operating normally';
        } elseif ($circuitState === 'closed' && $failureCount > 0) {
            $health = 'degraded';
            $status = 'Circuit is closed but has recent failures';
        } else {
            $health = 'unknown';
            $status = 'Circuit health status unknown';
        }

        return [
            'health' => $health,
            'status' => $status,
            'state' => $circuitState,
            'failure_count' => $failureCount
        ];
    }

    /**
     * Get severity color for styling
     *
     * @param string $severity Severity level
     * @return string Color code
     */
    private function getSeverityColor(string $severity): string
    {
        return match($severity) {
            'critical' => '#dc3545', // Red
            'high' => '#fd7e14',      // Orange
            'medium' => '#ffc107',    // Yellow
            'low' => '#20c997',       // Teal
            'info' => '#0dcaf0',      // Cyan
            default => '#6c757d'      // Gray
        };
    }

    /**
     * Get severity icon
     *
     * @param string $severity Severity level
     * @return string Icon
     */
    private function getSeverityIcon(string $severity): string
    {
        return match($severity) {
            'critical' => '🚨',
            'high' => '⚠️',
            'medium' => '⚡',
            'low' => 'ℹ️',
            'info' => '📋',
            default => '🔧'
        };
    }
}
