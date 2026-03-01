<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Shared\Contracts\AlertManagerInterface;
use Shared\Events\DatabaseFailoverEvent;
use Carbon\Carbon;

/**
 * Database Failover Alert Manager
 * 
 * Handles alerting and incident response for database failover events.
 * Supports multiple alert channels: Slack, PagerDuty, Email, Webhook
 */
class DatabaseFailoverAlertManager implements AlertManagerInterface
{
    private array $config;
    private array $alertChannels;
    private array $escalationRules;
    private array $alertHistory;

    public function __construct()
    {
        $this->config = config('database-failover.alerting', []);
        $this->alertChannels = $this->config['channels'] ?? [];
        $this->escalationRules = $this->config['escalation'] ?? [];
        $this->alertHistory = [];
    }

    /**
     * Handle database failover event and trigger appropriate alerts.
     */
    public function handleFailoverEvent(DatabaseFailoverEvent $event): void
    {
        $alertData = $this->buildAlertData($event);
        $severity = $this->determineSeverity($event);
        
        Log::info("Processing failover alert", [
            'event_type' => $event->getType(),
            'severity' => $severity,
            'connection' => $event->getConnectionName(),
        ]);

        // Check if we should suppress duplicate alerts
        if ($this->shouldSuppressAlert($alertData)) {
            Log::info("Alert suppressed due to rate limiting", ['alert_id' => $alertData['alert_id']]);
            return;
        }

        // Send alerts based on severity
        $this->sendAlerts($alertData, $severity);
        
        // Track alert for escalation
        $this->trackAlert($alertData);
        
        // Handle escalation if needed
        $this->handleEscalation($alertData, $severity);
    }

    /**
     * Send alerts through configured channels.
     */
    private function sendAlerts(array $alertData, string $severity): void
    {
        $channels = $this->getChannelsForSeverity($severity);
        
        foreach ($channels as $channel) {
            try {
                switch ($channel['type']) {
                    case 'slack':
                        $this->sendSlackAlert($alertData, $channel);
                        break;
                    case 'pagerduty':
                        $this->sendPagerDutyAlert($alertData, $channel);
                        break;
                    case 'email':
                        $this->sendEmailAlert($alertData, $channel);
                        break;
                    case 'webhook':
                        $this->sendWebhookAlert($alertData, $channel);
                        break;
                    case 'teams':
                        $this->sendTeamsAlert($alertData, $channel);
                        break;
                    default:
                        Log::warning("Unknown alert channel type: {$channel['type']}");
                }
                
                Log::info("Alert sent successfully", [
                    'channel' => $channel['type'],
                    'alert_id' => $alertData['alert_id']
                ]);
                
            } catch (\Exception $e) {
                Log::error("Failed to send alert via {$channel['type']}: " . $e->getMessage(), [
                    'alert_id' => $alertData['alert_id'],
                    'channel' => $channel,
                    'error' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Send Slack alert.
     */
    private function sendSlackAlert(array $alertData, array $channel): void
    {
        $webhookUrl = $channel['webhook_url'];
        $color = $this->getSeverityColor($alertData['severity']);
        
        $payload = [
            'username' => 'Database Failover Bot',
            'icon_emoji' => ':warning:',
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $alertData['title'],
                    'text' => $alertData['description'],
                    'fields' => [
                        [
                            'title' => 'Service',
                            'value' => $alertData['service'],
                            'short' => true
                        ],
                        [
                            'title' => 'Connection',
                            'value' => $alertData['connection'],
                            'short' => true
                        ],
                        [
                            'title' => 'Severity',
                            'value' => strtoupper($alertData['severity']),
                            'short' => true
                        ],
                        [
                            'title' => 'Timestamp',
                            'value' => $alertData['timestamp'],
                            'short' => true
                        ]
                    ],
                    'actions' => [
                        [
                            'type' => 'button',
                            'text' => 'View Dashboard',
                            'url' => $this->getDashboardUrl($alertData)
                        ],
                        [
                            'type' => 'button',
                            'text' => 'View Logs',
                            'url' => $this->getLogsUrl($alertData)
                        ]
                    ],
                    'footer' => 'Database Failover System',
                    'ts' => time()
                ]
            ]
        ];

        Http::timeout(10)->post($webhookUrl, $payload);
    }

    /**
     * Send PagerDuty alert.
     */
    private function sendPagerDutyAlert(array $alertData, array $channel): void
    {
        $integrationKey = $channel['integration_key'];
        
        $payload = [
            'routing_key' => $integrationKey,
            'event_action' => 'trigger',
            'dedup_key' => $alertData['alert_id'],
            'payload' => [
                'summary' => $alertData['title'],
                'source' => $alertData['service'],
                'severity' => $this->mapSeverityToPagerDuty($alertData['severity']),
                'component' => 'database-failover',
                'group' => 'infrastructure',
                'class' => 'database',
                'custom_details' => [
                    'connection' => $alertData['connection'],
                    'event_type' => $alertData['event_type'],
                    'description' => $alertData['description'],
                    'dashboard_url' => $this->getDashboardUrl($alertData),
                    'logs_url' => $this->getLogsUrl($alertData)
                ]
            ]
        ];

        Http::timeout(10)->post('https://events.pagerduty.com/v2/enqueue', $payload);
    }

    /**
     * Send email alert.
     */
    private function sendEmailAlert(array $alertData, array $channel): void
    {
        $recipients = $channel['recipients'] ?? [];
        
        foreach ($recipients as $recipient) {
            // Use Laravel's Mail facade
            \Mail::send('emails.database-failover-alert', $alertData, function ($message) use ($recipient, $alertData) {
                $message->to($recipient)
                        ->subject($alertData['title'])
                        ->priority(3); // High priority
            });
        }
    }

    /**
     * Send webhook alert.
     */
    private function sendWebhookAlert(array $alertData, array $channel): void
    {
        $webhookUrl = $channel['url'];
        $headers = $channel['headers'] ?? [];
        
        $payload = [
            'alert_id' => $alertData['alert_id'],
            'event_type' => 'database_failover',
            'severity' => $alertData['severity'],
            'title' => $alertData['title'],
            'description' => $alertData['description'],
            'service' => $alertData['service'],
            'connection' => $alertData['connection'],
            'timestamp' => $alertData['timestamp'],
            'metadata' => $alertData['metadata'] ?? []
        ];

        Http::withHeaders($headers)->timeout(10)->post($webhookUrl, $payload);
    }

    /**
     * Send Microsoft Teams alert.
     */
    private function sendTeamsAlert(array $alertData, array $channel): void
    {
        $webhookUrl = $channel['webhook_url'];
        $color = $this->getSeverityColor($alertData['severity']);
        
        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => $color,
            'summary' => $alertData['title'],
            'sections' => [
                [
                    'activityTitle' => $alertData['title'],
                    'activitySubtitle' => $alertData['description'],
                    'facts' => [
                        ['name' => 'Service', 'value' => $alertData['service']],
                        ['name' => 'Connection', 'value' => $alertData['connection']],
                        ['name' => 'Severity', 'value' => strtoupper($alertData['severity'])],
                        ['name' => 'Timestamp', 'value' => $alertData['timestamp']]
                    ],
                    'markdown' => true
                ]
            ],
            'potentialAction' => [
                [
                    '@type' => 'OpenUri',
                    'name' => 'View Dashboard',
                    'targets' => [
                        ['os' => 'default', 'uri' => $this->getDashboardUrl($alertData)]
                    ]
                ]
            ]
        ];

        Http::timeout(10)->post($webhookUrl, $payload);
    }

    /**
     * Build alert data from failover event.
     */
    private function buildAlertData(DatabaseFailoverEvent $event): array
    {
        $alertId = $this->generateAlertId($event);
        
        return [
            'alert_id' => $alertId,
            'event_type' => $event->getType(),
            'title' => $this->buildAlertTitle($event),
            'description' => $this->buildAlertDescription($event),
            'severity' => $this->determineSeverity($event),
            'service' => $event->getServiceName() ?? 'unknown',
            'connection' => $event->getConnectionName(),
            'timestamp' => $event->getTimestamp()->toISOString(),
            'metadata' => $event->getMetadata()
        ];
    }

    /**
     * Determine alert severity based on event type.
     */
    private function determineSeverity(DatabaseFailoverEvent $event): string
    {
        $eventType = $event->getType();
        
        $severityMap = [
            'failover_triggered' => 'high',
            'all_connections_failed' => 'critical',
            'connection_recovered' => 'info',
            'failover_completed' => 'medium',
            'health_check_failed' => 'medium',
            'circuit_breaker_opened' => 'high',
            'graceful_degradation_enabled' => 'medium'
        ];

        return $severityMap[$eventType] ?? 'medium';
    }

    /**
     * Build alert title.
     */
    private function buildAlertTitle(DatabaseFailoverEvent $event): string
    {
        $eventType = $event->getType();
        $connection = $event->getConnectionName();
        $service = $event->getServiceName() ?? 'Unknown Service';
        
        $titleMap = [
            'failover_triggered' => "🚨 Database Failover Triggered - {$service}",
            'all_connections_failed' => "🔥 ALL DATABASE CONNECTIONS FAILED - {$service}",
            'connection_recovered' => "✅ Database Connection Recovered - {$connection}",
            'failover_completed' => "🔄 Database Failover Completed - {$service}",
            'health_check_failed' => "⚠️ Database Health Check Failed - {$connection}",
            'circuit_breaker_opened' => "🛑 Database Circuit Breaker Opened - {$connection}",
            'graceful_degradation_enabled' => "⚡ Graceful Degradation Enabled - {$service}"
        ];

        return $titleMap[$eventType] ?? "📊 Database Event - {$eventType}";
    }

    /**
     * Build alert description.
     */
    private function buildAlertDescription(DatabaseFailoverEvent $event): string
    {
        $metadata = $event->getMetadata();
        $connection = $event->getConnectionName();
        $eventType = $event->getType();
        
        $baseDescription = "Database failover event detected for connection: {$connection}";
        
        if (isset($metadata['error_message'])) {
            $baseDescription .= "\nError: " . $metadata['error_message'];
        }
        
        if (isset($metadata['failover_duration'])) {
            $baseDescription .= "\nFailover Duration: " . $metadata['failover_duration'] . "ms";
        }
        
        if (isset($metadata['new_connection'])) {
            $baseDescription .= "\nNew Connection: " . $metadata['new_connection'];
        }
        
        return $baseDescription;
    }

    /**
     * Generate unique alert ID.
     */
    private function generateAlertId(DatabaseFailoverEvent $event): string
    {
        return 'db_failover_' . $event->getType() . '_' . $event->getConnectionName() . '_' . time();
    }

    /**
     * Check if alert should be suppressed due to rate limiting.
     */
    private function shouldSuppressAlert(array $alertData): bool
    {
        $suppressionKey = "alert_suppression_{$alertData['event_type']}_{$alertData['connection']}";
        $suppressionWindow = $this->config['suppression_window'] ?? 300; // 5 minutes default
        
        if (Cache::has($suppressionKey)) {
            return true;
        }
        
        Cache::put($suppressionKey, true, $suppressionWindow);
        return false;
    }

    /**
     * Get alert channels for severity level.
     */
    private function getChannelsForSeverity(string $severity): array
    {
        $allChannels = $this->alertChannels;
        $filteredChannels = [];
        
        foreach ($allChannels as $channel) {
            $channelSeverities = $channel['severities'] ?? ['critical', 'high', 'medium', 'low', 'info'];
            
            if (in_array($severity, $channelSeverities)) {
                $filteredChannels[] = $channel;
            }
        }
        
        return $filteredChannels;
    }

    /**
     * Get color for severity level.
     */
    private function getSeverityColor(string $severity): string
    {
        $colorMap = [
            'critical' => '#FF0000', // Red
            'high' => '#FF8C00',     // Dark Orange
            'medium' => '#FFD700',   // Gold
            'low' => '#32CD32',      // Lime Green
            'info' => '#1E90FF'      // Dodger Blue
        ];

        return $colorMap[$severity] ?? '#808080'; // Gray default
    }

    /**
     * Map severity to PagerDuty severity.
     */
    private function mapSeverityToPagerDuty(string $severity): string
    {
        $severityMap = [
            'critical' => 'critical',
            'high' => 'error',
            'medium' => 'warning',
            'low' => 'info',
            'info' => 'info'
        ];

        return $severityMap[$severity] ?? 'warning';
    }

    /**
     * Track alert for escalation purposes.
     */
    private function trackAlert(array $alertData): void
    {
        $this->alertHistory[] = [
            'alert_id' => $alertData['alert_id'],
            'severity' => $alertData['severity'],
            'timestamp' => now(),
            'escalated' => false
        ];
        
        // Keep only recent alerts (last 24 hours)
        $this->alertHistory = array_filter($this->alertHistory, function ($alert) {
            return $alert['timestamp']->diffInHours(now()) <= 24;
        });
    }

    /**
     * Handle alert escalation.
     */
    private function handleEscalation(array $alertData, string $severity): void
    {
        if (!isset($this->escalationRules[$severity])) {
            return;
        }
        
        $escalationRule = $this->escalationRules[$severity];
        $escalationDelay = $escalationRule['delay'] ?? 900; // 15 minutes default
        
        // Schedule escalation check
        Cache::put(
            "escalation_check_{$alertData['alert_id']}", 
            $alertData, 
            $escalationDelay
        );
    }

    /**
     * Get dashboard URL for alert.
     */
    private function getDashboardUrl(array $alertData): string
    {
        $baseUrl = $this->config['dashboard_base_url'] ?? 'http://localhost:3000';
        return "{$baseUrl}/database-failover?service={$alertData['service']}&connection={$alertData['connection']}";
    }

    /**
     * Get logs URL for alert.
     */
    private function getLogsUrl(array $alertData): string
    {
        $baseUrl = $this->config['logs_base_url'] ?? 'http://localhost:5601';
        $timestamp = urlencode($alertData['timestamp']);
        return "{$baseUrl}/app/logs?query=database_failover&timestamp={$timestamp}";
    }

    /**
     * Get recent alert history.
     */
    public function getAlertHistory(int $hours = 24): array
    {
        return array_filter($this->alertHistory, function ($alert) use ($hours) {
            return $alert['timestamp']->diffInHours(now()) <= $hours;
        });
    }

    /**
     * Test alert channels.
     */
    public function testAlertChannels(): array
    {
        $results = [];
        
        foreach ($this->alertChannels as $channel) {
            try {
                $testAlert = [
                    'alert_id' => 'test_' . time(),
                    'title' => '🧪 Database Failover Alert Test',
                    'description' => 'This is a test alert to verify the alerting system is working correctly.',
                    'severity' => 'info',
                    'service' => 'test-service',
                    'connection' => 'test-connection',
                    'timestamp' => now()->toISOString(),
                    'event_type' => 'test'
                ];
                
                $this->sendAlerts($testAlert, 'info');
                $results[$channel['type']] = 'success';
                
            } catch (\Exception $e) {
                $results[$channel['type']] = 'failed: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
}
