<?php

namespace Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * DatabaseFailoverAlert
 * 
 * Mailable class for database failover alert notifications.
 * Handles critical database events like split-brain detection,
 * failover failures, and service unavailability.
 */
class DatabaseFailoverAlert extends Mailable implements ShouldQueue
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
                    ->view('emails.database-failover.alert')
                    ->with([
                        'eventType' => $eventType,
                        'severity' => $severity,
                        'context' => $context,
                        'notificationId' => $this->notificationId,
                        'timestamp' => $this->notificationData['timestamp'],
                        'environment' => $this->notificationData['environment'],
                        'serviceName' => $this->notificationData['service_name'],
                        'alertDetails' => $this->formatAlertDetails($eventType, $context),
                        'actionItems' => $this->generateActionItems($eventType, $context),
                        'severityColor' => $this->getSeverityColor($severity),
                        'severityIcon' => $this->getSeverityIcon($severity)
                    ]);
    }

    /**
     * Format alert details based on event type
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return array Formatted alert details
     */
    private function formatAlertDetails(string $eventType, array $context): array
    {
        switch ($eventType) {
            case 'split_brain_detected':
                return [
                    'title' => 'Split-Brain Condition Detected',
                    'description' => 'Multiple database writers detected simultaneously, which can lead to data corruption.',
                    'affected_connections' => $context['multiple_writers'] ?? [],
                    'risk_level' => 'CRITICAL - Immediate action required',
                    'impact' => 'Data consistency at risk, potential data corruption'
                ];

            case 'graceful_degradation_unavailable':
                return [
                    'title' => 'Database Service Completely Unavailable',
                    'description' => 'All database connections have failed and graceful degradation is not possible.',
                    'affected_service' => $context['service_name'] ?? 'Unknown',
                    'risk_level' => 'CRITICAL - Service outage',
                    'impact' => 'Complete service unavailability'
                ];

            case 'failover_attempt_failed':
                return [
                    'title' => 'Database Failover Attempt Failed',
                    'description' => 'Automatic failover to backup database connection has failed.',
                    'failed_connection' => $context['target_connection'] ?? 'Unknown',
                    'error_details' => $context['error_message'] ?? 'No details available',
                    'risk_level' => 'CRITICAL - Manual intervention required',
                    'impact' => 'Failover mechanism compromised'
                ];

            case 'connection_health_check_failed':
                return [
                    'title' => 'Database Health Check Failed',
                    'description' => 'Database connection health check has failed multiple times.',
                    'connection' => $context['current_connection'] ?? 'Unknown',
                    'error_details' => $context['error_message'] ?? 'No details available',
                    'risk_level' => 'HIGH - Monitor closely',
                    'impact' => 'Potential service degradation'
                ];

            case 'data_consistency_issues_detected':
                return [
                    'title' => 'Data Consistency Issues Detected',
                    'description' => 'Data inconsistencies found between database connections.',
                    'inconsistencies' => $context['inconsistencies'] ?? [],
                    'affected_connections' => $context['connections_checked'] ?? [],
                    'risk_level' => 'HIGH - Data integrity concern',
                    'impact' => 'Data accuracy may be compromised'
                ];

            default:
                return [
                    'title' => 'Database Failover Event',
                    'description' => 'A database failover event has occurred.',
                    'event_type' => $eventType,
                    'risk_level' => 'MEDIUM - Review required',
                    'impact' => 'Potential service impact'
                ];
        }
    }

    /**
     * Generate action items based on event type
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return array Action items
     */
    private function generateActionItems(string $eventType, array $context): array
    {
        switch ($eventType) {
            case 'split_brain_detected':
                return [
                    '1. IMMEDIATE: Stop all write operations to affected databases',
                    '2. Identify and isolate the conflicting database connections',
                    '3. Determine the authoritative data source',
                    '4. Resolve data conflicts manually if necessary',
                    '5. Restart services with proper database configuration'
                ];

            case 'graceful_degradation_unavailable':
                return [
                    '1. Check database server status and connectivity',
                    '2. Verify network connectivity to database servers',
                    '3. Check database server logs for errors',
                    '4. Consider manual failover to backup systems',
                    '5. Communicate service status to stakeholders'
                ];

            case 'failover_attempt_failed':
                return [
                    '1. Check backup database server status',
                    '2. Verify failover configuration settings',
                    '3. Test manual connection to backup database',
                    '4. Review failover logs for detailed error information',
                    '5. Consider alternative backup connections'
                ];

            case 'connection_health_check_failed':
                return [
                    '1. Check database server performance metrics',
                    '2. Verify network connectivity and latency',
                    '3. Review database server logs',
                    '4. Monitor for service degradation',
                    '5. Prepare for potential failover if issues persist'
                ];

            case 'data_consistency_issues_detected':
                return [
                    '1. Stop write operations to affected connections',
                    '2. Compare data between inconsistent connections',
                    '3. Identify the source of inconsistency',
                    '4. Implement data reconciliation procedures',
                    '5. Verify data integrity before resuming operations'
                ];

            default:
                return [
                    '1. Review the event details and context',
                    '2. Check system logs for additional information',
                    '3. Monitor system performance and stability',
                    '4. Escalate if issues persist or worsen'
                ];
        }
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
            default => '📊'
        };
    }
}
