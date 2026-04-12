<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Shared\Facades\SharedLog;
use Shared\Mail\DatabaseFailoverAlert;
use Shared\Mail\CircuitBreakerAlert;
use Shared\Mail\DatabaseTopologyReport;

/**
 * DatabaseFailoverEmailNotificationService
 * 
 * Handles email notifications for database failover events using SMTP2Go.
 * Provides intelligent alerting with severity-based routing, rate limiting,
 * and comprehensive reporting capabilities.
 */
class DatabaseFailoverEmailNotificationService
{
    /**
     * Notification severity levels
     */
    private const SEVERITY_LEVELS = [
        'critical' => 1,
        'high' => 2,
        'medium' => 3,
        'low' => 4,
        'info' => 5
    ];

    /**
     * Rate limiting configuration (minutes)
     */
    private const RATE_LIMITS = [
        'critical' => 5,    // Max 1 critical alert per 5 minutes
        'high' => 15,       // Max 1 high alert per 15 minutes
        'medium' => 30,     // Max 1 medium alert per 30 minutes
        'low' => 60,        // Max 1 low alert per hour
        'info' => 120       // Max 1 info alert per 2 hours
    ];

    /**
     * Email templates for different event types
     */
    private const EMAIL_TEMPLATES = [
        'split_brain_detected' => [
            'template' => 'database-failover.split-brain-alert',
            'severity' => 'critical',
            'subject' => '🚨 CRITICAL: Split-Brain Condition Detected'
        ],
        'graceful_degradation_unavailable' => [
            'template' => 'database-failover.service-unavailable',
            'severity' => 'critical',
            'subject' => '🚨 CRITICAL: Database Service Unavailable'
        ],
        'failover_attempt_failed' => [
            'template' => 'database-failover.failover-failed',
            'severity' => 'critical',
            'subject' => '🚨 CRITICAL: Database Failover Failed'
        ],
        'query_circuit_breaker_open' => [
            'template' => 'circuit-breaker.circuit-open',
            'severity' => 'high',
            'subject' => '⚠️ HIGH: Database Circuit Breaker Opened'
        ],
        'transaction_circuit_breaker_open' => [
            'template' => 'circuit-breaker.transaction-circuit-open',
            'severity' => 'critical',
            'subject' => '🚨 CRITICAL: Transaction Circuit Breaker Opened'
        ],
        'connection_health_check_failed' => [
            'template' => 'database-failover.health-check-failed',
            'severity' => 'high',
            'subject' => '⚠️ HIGH: Database Health Check Failed'
        ],
        'data_consistency_issues_detected' => [
            'template' => 'database-failover.consistency-issues',
            'severity' => 'high',
            'subject' => '⚠️ HIGH: Data Consistency Issues Detected'
        ],
        'database_topology_mapping_completed' => [
            'template' => 'database-failover.topology-report',
            'severity' => 'info',
            'subject' => 'ℹ️ Database Topology Mapping Report'
        ]
    ];

    /**
     * Recipient groups for different severity levels
     */
    private const RECIPIENT_GROUPS = [
        'critical' => ['ops-team', 'engineering-leads', 'on-call'],
        'high' => ['ops-team', 'engineering-leads'],
        'medium' => ['ops-team'],
        'low' => ['ops-team'],
        'info' => ['ops-team']
    ];

    /**
     * Process database failover event for email notification
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return bool Success status
     */
    public function processFailoverEvent(string $eventType, array $context): bool
    {
        $notificationId = $this->generateNotificationId();
        
        SharedLog::databaseFailover('email_notification_processing_started', [
            'notification_id' => $notificationId,
            'event_type' => $eventType,
            'timestamp' => now()->toISOString()
        ]);

        try {
            // Check if this event type should trigger email notifications
            if (!$this->shouldSendNotification($eventType, $context)) {
                SharedLog::databaseFailover('email_notification_skipped', [
                    'notification_id' => $notificationId,
                    'event_type' => $eventType,
                    'reason' => 'notification_not_required'
                ]);
                return true;
            }

            // Check rate limiting
            if (!$this->checkRateLimit($eventType, $context)) {
                SharedLog::databaseFailover('email_notification_rate_limited', [
                    'notification_id' => $notificationId,
                    'event_type' => $eventType,
                    'reason' => 'rate_limit_exceeded'
                ]);
                return true;
            }

            // Get email configuration for this event type
            $emailConfig = $this->getEmailConfiguration($eventType);
            
            // Prepare notification data
            $notificationData = $this->prepareNotificationData($eventType, $context, $emailConfig);
            
            // Send notifications
            $result = $this->sendNotifications($notificationData, $notificationId);
            
            SharedLog::databaseFailover('email_notification_processing_completed', [
                'notification_id' => $notificationId,
                'event_type' => $eventType,
                'emails_sent' => $result['emails_sent'],
                'success' => $result['success']
            ]);
            
            return $result['success'];
            
        } catch (\Exception $e) {
            SharedLog::databaseFailover('email_notification_processing_failed', [
                'notification_id' => $notificationId,
                'event_type' => $eventType,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);
            
            return false;
        }
    }

    /**
     * Check if notification should be sent for this event
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return bool Whether to send notification
     */
    private function shouldSendNotification(string $eventType, array $context): bool
    {
        // Check if email notifications are enabled
        if (!config('mail.notifications.database_failover.enabled', true)) {
            return false;
        }

        // Check if this event type has email configuration
        if (!isset(self::EMAIL_TEMPLATES[$eventType])) {
            return false;
        }

        // Check severity threshold
        $eventSeverity = self::EMAIL_TEMPLATES[$eventType]['severity'];
        $minSeverity = config('mail.notifications.database_failover.min_severity', 'medium');
        
        if (self::SEVERITY_LEVELS[$eventSeverity] > self::SEVERITY_LEVELS[$minSeverity]) {
            return false;
        }

        // Check for specific context-based conditions
        return $this->checkContextConditions($eventType, $context);
    }

    /**
     * Check context-specific conditions for notifications
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return bool Whether conditions are met
     */
    private function checkContextConditions(string $eventType, array $context): bool
    {
        switch ($eventType) {
            case 'split_brain_detected':
                // Always send for split-brain - this is critical
                return true;
                
            case 'query_circuit_breaker_open':
                // Only send if failure count is significant
                return ($context['failure_count'] ?? 0) >= 5;
                
            case 'connection_health_check_failed':
                // Only send if it's not a transient error
                return !$this->isTransientError($context['error_message'] ?? '');
                
            case 'data_consistency_issues_detected':
                // Only send if inconsistencies are significant
                return count($context['inconsistencies'] ?? []) > 0;
                
            default:
                return true;
        }
    }

    /**
     * Check if error is transient (temporary network issues, etc.)
     *
     * @param string $errorMessage Error message
     * @return bool Whether error is transient
     */
    private function isTransientError(string $errorMessage): bool
    {
        $transientPatterns = [
            'connection timeout',
            'network unreachable',
            'temporary failure',
            'connection refused',
            'host unreachable'
        ];
        
        $lowerMessage = strtolower($errorMessage);
        
        foreach ($transientPatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check rate limiting for notifications
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @return bool Whether rate limit allows sending
     */
    private function checkRateLimit(string $eventType, array $context): bool
    {
        $severity = self::EMAIL_TEMPLATES[$eventType]['severity'];
        $rateLimitMinutes = self::RATE_LIMITS[$severity];
        
        // Create rate limit key
        $rateLimitKey = "email_notification_rate_limit_{$eventType}_{$severity}";
        
        // Check if we're within rate limit
        $lastSent = cache()->get($rateLimitKey);
        
        if ($lastSent && now()->diffInMinutes($lastSent) < $rateLimitMinutes) {
            return false;
        }
        
        // Update rate limit timestamp
        cache()->put($rateLimitKey, now(), now()->addMinutes($rateLimitMinutes * 2));
        
        return true;
    }

    /**
     * Get email configuration for event type
     *
     * @param string $eventType Event type
     * @return array Email configuration
     */
    private function getEmailConfiguration(string $eventType): array
    {
        $config = self::EMAIL_TEMPLATES[$eventType];
        
        // Add recipient information
        $severity = $config['severity'];
        $config['recipients'] = $this->getRecipients($severity);
        
        return $config;
    }

    /**
     * Get recipients for severity level
     *
     * @param string $severity Severity level
     * @return array Recipients
     */
    private function getRecipients(string $severity): array
    {
        $recipients = [];
        $groups = self::RECIPIENT_GROUPS[$severity] ?? ['ops-team'];
        
        foreach ($groups as $group) {
            $groupEmails = config("mail.notifications.recipient_groups.{$group}", []);
            $recipients = array_merge($recipients, $groupEmails);
        }
        
        // Remove duplicates and add fallback
        $recipients = array_unique($recipients);
        
        if (empty($recipients)) {
            $recipients = [config('mail.notifications.fallback_email', 'ops@company.com')];
        }
        
        return $recipients;
    }

    /**
     * Prepare notification data
     *
     * @param string $eventType Event type
     * @param array $context Event context
     * @param array $emailConfig Email configuration
     * @return array Notification data
     */
    private function prepareNotificationData(string $eventType, array $context, array $emailConfig): array
    {
        return [
            'event_type' => $eventType,
            'severity' => $emailConfig['severity'],
            'subject' => $emailConfig['subject'],
            'template' => $emailConfig['template'],
            'recipients' => $emailConfig['recipients'],
            'context' => $this->enrichContext($context),
            'timestamp' => now()->toISOString(),
            'environment' => config('app.env', 'production'),
            'service_name' => config('app.name', 'Database Failover System')
        ];
    }

    /**
     * Enrich context with additional information
     *
     * @param array $context Original context
     * @return array Enriched context
     */
    private function enrichContext(array $context): array
    {
        $enriched = $context;
        
        // Add system information
        $enriched['system_info'] = [
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        // Add database connection information
        if (isset($context['connection'])) {
            $enriched['connection_info'] = $this->getConnectionInfo($context['connection']);
        }
        
        // Add formatted timestamps
        if (isset($context['timestamp'])) {
            $enriched['formatted_timestamp'] = now()->parse($context['timestamp'])->format('Y-m-d H:i:s T');
        }
        
        return $enriched;
    }

    /**
     * Get connection information
     *
     * @param string $connectionName Connection name
     * @return array Connection information
     */
    private function getConnectionInfo(string $connectionName): array
    {
        $config = config("database.connections.{$connectionName}", []);
        
        return [
            'name' => $connectionName,
            'driver' => $config['driver'] ?? 'unknown',
            'host' => $config['host'] ?? 'unknown',
            'database' => $config['database'] ?? 'unknown',
            'port' => $config['port'] ?? 'unknown'
        ];
    }

    /**
     * Send notifications
     *
     * @param array $notificationData Notification data
     * @param string $notificationId Notification ID
     * @return array Send result
     */
    private function sendNotifications(array $notificationData, string $notificationId): array
    {
        $emailsSent = 0;
        $errors = [];
        
        foreach ($notificationData['recipients'] as $recipient) {
            try {
                $this->sendSingleNotification($recipient, $notificationData, $notificationId);
                $emailsSent++;
                
                SharedLog::databaseFailover('email_notification_sent', [
                    'notification_id' => $notificationId,
                    'recipient' => $recipient,
                    'event_type' => $notificationData['event_type'],
                    'severity' => $notificationData['severity']
                ]);
                
            } catch (\Exception $e) {
                $errors[] = [
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ];
                
                SharedLog::databaseFailover('email_notification_send_failed', [
                    'notification_id' => $notificationId,
                    'recipient' => $recipient,
                    'error_message' => $e->getMessage(),
                    'error_class' => get_class($e)
                ]);
            }
        }
        
        return [
            'emails_sent' => $emailsSent,
            'total_recipients' => count($notificationData['recipients']),
            'errors' => $errors,
            'success' => $emailsSent > 0
        ];
    }

    /**
     * Send single notification
     *
     * @param string $recipient Recipient email
     * @param array $notificationData Notification data
     * @param string $notificationId Notification ID
     */
    private function sendSingleNotification(string $recipient, array $notificationData, string $notificationId): void
    {
        // Configure SMTP2Go settings
        $this->configureSmtp2Go();
        
        // Select appropriate mailable class based on event type
        $mailable = $this->createMailable($notificationData, $notificationId);
        
        // Send email
        Mail::to($recipient)->send($mailable);
    }

    /**
     * Configure SMTP2Go settings
     */
    private function configureSmtp2Go(): void
    {
        Config::set([
            'mail.default' => 'smtp2go',
            'mail.mailers.smtp2go' => [
                'transport' => 'smtp',
                'host' => config('mail.smtp2go.host', 'mail.smtp2go.com'),
                'port' => config('mail.smtp2go.port', 587),
                'encryption' => config('mail.smtp2go.encryption', 'tls'),
                'username' => config('mail.smtp2go.username'),
                'password' => config('mail.smtp2go.password'),
                'timeout' => config('mail.smtp2go.timeout', 30),
            ]
        ]);
    }

    /**
     * Create appropriate mailable for the notification
     *
     * @param array $notificationData Notification data
     * @param string $notificationId Notification ID
     * @return \Illuminate\Mail\Mailable Mailable instance
     */
    private function createMailable(array $notificationData, string $notificationId): \Illuminate\Mail\Mailable
    {
        $eventType = $notificationData['event_type'];
        
        // Create specific mailable based on event type
        switch ($eventType) {
            case 'split_brain_detected':
            case 'graceful_degradation_unavailable':
            case 'failover_attempt_failed':
            case 'connection_health_check_failed':
            case 'data_consistency_issues_detected':
                return new DatabaseFailoverAlert($notificationData, $notificationId);
                
            case 'query_circuit_breaker_open':
            case 'transaction_circuit_breaker_open':
                return new CircuitBreakerAlert($notificationData, $notificationId);
                
            case 'database_topology_mapping_completed':
                return new DatabaseTopologyReport($notificationData, $notificationId);
                
            default:
                return new DatabaseFailoverAlert($notificationData, $notificationId);
        }
    }

    /**
     * Send daily summary report
     *
     * @return bool Success status
     */
    public function sendDailySummaryReport(): bool
    {
        $reportId = $this->generateReportId();
        
        SharedLog::databaseFailover('daily_summary_report_generation_started', [
            'report_id' => $reportId,
            'timestamp' => now()->toISOString()
        ]);

        try {
            $summaryData = $this->generateDailySummary();
            
            if ($summaryData['total_events'] === 0) {
                SharedLog::databaseFailover('daily_summary_report_skipped', [
                    'report_id' => $reportId,
                    'reason' => 'no_events_to_report'
                ]);
                return true;
            }
            
            $recipients = config('mail.notifications.daily_reports.recipients', []);
            
            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send(new DatabaseTopologyReport($summaryData, $reportId));
            }
            
            SharedLog::databaseFailover('daily_summary_report_sent', [
                'report_id' => $reportId,
                'recipients_count' => count($recipients),
                'events_summarized' => $summaryData['total_events']
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            SharedLog::databaseFailover('daily_summary_report_failed', [
                'report_id' => $reportId,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);
            
            return false;
        }
    }

    /**
     * Generate daily summary data
     *
     * @return array Summary data
     */
    private function generateDailySummary(): array
    {
        // This would typically query the SharedLog system for events from the last 24 hours
        // For now, we'll return a placeholder structure
        
        return [
            'report_type' => 'daily_summary',
            'date' => now()->format('Y-m-d'),
            'period' => 'last_24_hours',
            'total_events' => 0, // Would be populated from actual data
            'events_by_severity' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'info' => 0
            ],
            'top_events' => [],
            'circuit_breaker_stats' => [],
            'connection_health_summary' => [],
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Generate unique notification ID
     *
     * @return string Notification ID
     */
    private function generateNotificationId(): string
    {
        return 'notification_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }

    /**
     * Generate unique report ID
     *
     * @return string Report ID
     */
    private function generateReportId(): string
    {
        return 'report_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }
}
