<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Service for workflow alerting and notifications
 */
class WorkflowAlertingService
{
    protected array $alertRules;
    protected array $alertChannels;

    public function __construct()
    {
        $this->alertRules = config('workflow.alerts.rules', []);
        $this->alertChannels = config('workflow.alerts.channels', []);
    }

    /**
     * Check all alert conditions and trigger alerts if needed
     */
    public function checkAlerts(): array
    {
        $triggeredAlerts = [];

        foreach ($this->alertRules as $ruleName => $rule) {
            try {
                if ($this->evaluateAlertCondition($rule)) {
                    $alert = $this->triggerAlert($ruleName, $rule);
                    $triggeredAlerts[] = $alert;
                }
            } catch (\Exception $e) {
                Log::error('Failed to evaluate alert rule', [
                    'rule_name' => $ruleName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $triggeredAlerts;
    }

    /**
     * Trigger specific alert for DLQ threshold
     */
    public function triggerDlqThresholdAlert(int $pendingCount, int $threshold): void
    {
        $alertData = [
            'type' => 'dlq_threshold',
            'severity' => 'warning',
            'message' => "DLQ pending count ({$pendingCount}) exceeds threshold ({$threshold})",
            'data' => [
                'pending_count' => $pendingCount,
                'threshold' => $threshold,
                'timestamp' => now()->toISOString(),
            ],
        ];

        $this->sendAlert($alertData, ['slack', 'email']);
    }

    /**
     * Trigger critical manual intervention alert
     */
    public function triggerCriticalInterventionAlert(string $workflowId, string $interventionId, string $reason): void
    {
        $alertData = [
            'type' => 'manual_intervention_critical',
            'severity' => 'critical',
            'message' => "Critical manual intervention required for workflow {$workflowId}",
            'data' => [
                'workflow_id' => $workflowId,
                'intervention_id' => $interventionId,
                'reason' => $reason,
                'timestamp' => now()->toISOString(),
            ],
        ];

        $this->sendAlert($alertData, ['slack', 'pagerduty', 'sms']);
    }

    /**
     * Trigger workflow failure rate alert
     */
    public function triggerFailureRateAlert(float $failureRate, float $threshold): void
    {
        $alertData = [
            'type' => 'workflow_failure_rate',
            'severity' => 'warning',
            'message' => "Workflow failure rate ({$failureRate}%) exceeds threshold ({$threshold}%)",
            'data' => [
                'failure_rate' => $failureRate,
                'threshold' => $threshold,
                'timeframe' => '1h',
                'timestamp' => now()->toISOString(),
            ],
        ];

        $this->sendAlert($alertData, ['slack', 'email']);
    }

    /**
     * Trigger correlation timeout alert
     */
    public function triggerCorrelationTimeoutAlert(float $avgResponseTime, float $threshold): void
    {
        $alertData = [
            'type' => 'correlation_timeout',
            'severity' => 'critical',
            'message' => "Average response time ({$avgResponseTime}s) exceeds threshold ({$threshold}s)",
            'data' => [
                'avg_response_time' => $avgResponseTime,
                'threshold' => $threshold,
                'timeframe' => '5m',
                'timestamp' => now()->toISOString(),
            ],
        ];

        $this->sendAlert($alertData, ['slack', 'pagerduty']);
    }

    /**
     * Trigger queue depth alert
     */
    public function triggerQueueDepthAlert(string $queueName, int $depth, int $threshold): void
    {
        $alertData = [
            'type' => 'queue_depth',
            'severity' => 'warning',
            'message' => "Queue {$queueName} depth ({$depth}) exceeds threshold ({$threshold})",
            'data' => [
                'queue_name' => $queueName,
                'depth' => $depth,
                'threshold' => $threshold,
                'timestamp' => now()->toISOString(),
            ],
        ];

        $this->sendAlert($alertData, ['slack', 'email']);
    }

    /**
     * Trigger resource utilization alert
     */
    public function triggerResourceAlert(string $resource, float $usage, float $threshold): void
    {
        $severity = $usage > ($threshold * 1.5) ? 'critical' : 'warning';
        $channels = $severity === 'critical' ? ['slack', 'pagerduty'] : ['slack', 'email'];

        $alertData = [
            'type' => 'resource_utilization',
            'severity' => $severity,
            'message' => "High {$resource} usage: {$usage}% (threshold: {$threshold}%)",
            'data' => [
                'resource' => $resource,
                'usage' => $usage,
                'threshold' => $threshold,
                'timestamp' => now()->toISOString(),
            ],
        ];

        $this->sendAlert($alertData, $channels);
    }

    /**
     * Evaluate alert condition
     */
    protected function evaluateAlertCondition(array $rule): bool
    {
        $condition = $rule['condition'] ?? '';
        $type = $rule['type'] ?? '';

        switch ($type) {
            case 'dlq_threshold':
                return $this->evaluateDlqThreshold($rule);
                
            case 'failure_rate':
                return $this->evaluateFailureRate($rule);
                
            case 'response_time':
                return $this->evaluateResponseTime($rule);
                
            case 'queue_depth':
                return $this->evaluateQueueDepth($rule);
                
            case 'resource_usage':
                return $this->evaluateResourceUsage($rule);
                
            default:
                Log::warning('Unknown alert rule type', ['type' => $type]);
                return false;
        }
    }

    /**
     * Evaluate DLQ threshold condition
     */
    protected function evaluateDlqThreshold(array $rule): bool
    {
        $threshold = $rule['threshold'] ?? 10;
        $pendingCount = Cache::get('dlq.metrics.pending_retries', 0);
        
        return $pendingCount > $threshold;
    }

    /**
     * Evaluate failure rate condition
     */
    protected function evaluateFailureRate(array $rule): bool
    {
        $threshold = $rule['threshold'] ?? 5.0;
        $timeframe = $rule['timeframe'] ?? '1h';
        
        $successful = Cache::get("workflow.metrics.performance.{$timeframe}.workflow.successful", 0);
        $failed = Cache::get("workflow.metrics.performance.{$timeframe}.workflow.failed", 0);
        $total = $successful + $failed;
        
        if ($total === 0) {
            return false;
        }
        
        $failureRate = ($failed / $total) * 100;
        return $failureRate > $threshold;
    }

    /**
     * Evaluate response time condition
     */
    protected function evaluateResponseTime(array $rule): bool
    {
        $threshold = $rule['threshold'] ?? 10.0;
        $timeframe = $rule['timeframe'] ?? '5m';
        
        $avgResponseTime = Cache::get("correlation.metrics.{$timeframe}.avg_duration", 0);
        return $avgResponseTime > $threshold;
    }

    /**
     * Evaluate queue depth condition
     */
    protected function evaluateQueueDepth(array $rule): bool
    {
        $threshold = $rule['threshold'] ?? 100;
        $queues = $rule['queues'] ?? ['signals-high', 'signals-medium', 'dlq-payment'];
        
        foreach ($queues as $queue) {
            $depth = Cache::get("queue.depth.{$queue}", 0);
            if ($depth > $threshold) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Evaluate resource usage condition
     */
    protected function evaluateResourceUsage(array $rule): bool
    {
        $threshold = $rule['threshold'] ?? 80.0;
        $resource = $rule['resource'] ?? 'memory';
        $timeframe = $rule['timeframe'] ?? '5m';
        
        $usage = Cache::get("workflow.metrics.performance.{$timeframe}.avg_{$resource}", 0);
        return $usage > $threshold;
    }

    /**
     * Trigger alert
     */
    protected function triggerAlert(string $ruleName, array $rule): array
    {
        $alertData = [
            'rule_name' => $ruleName,
            'type' => $rule['type'] ?? 'unknown',
            'severity' => $rule['severity'] ?? 'warning',
            'message' => $rule['message'] ?? "Alert triggered for rule: {$ruleName}",
            'timestamp' => now()->toISOString(),
            'data' => $this->getAlertContextData($rule),
        ];

        $channels = $rule['channels'] ?? ['slack'];
        $this->sendAlert($alertData, $channels);

        // Store alert in cache for dashboard
        $this->storeAlert($alertData);

        return $alertData;
    }

    /**
     * Send alert to specified channels
     */
    protected function sendAlert(array $alertData, array $channels): void
    {
        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'slack':
                        $this->sendSlackAlert($alertData);
                        break;
                        
                    case 'email':
                        $this->sendEmailAlert($alertData);
                        break;
                        
                    case 'pagerduty':
                        $this->sendPagerDutyAlert($alertData);
                        break;
                        
                    case 'sms':
                        $this->sendSmsAlert($alertData);
                        break;
                        
                    case 'webhook':
                        $this->sendWebhookAlert($alertData);
                        break;
                        
                    default:
                        Log::warning('Unknown alert channel', ['channel' => $channel]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send alert', [
                    'channel' => $channel,
                    'alert_type' => $alertData['type'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send Slack alert
     */
    protected function sendSlackAlert(array $alertData): void
    {
        $severity = $alertData['severity'];
        $emoji = match ($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📢',
        };

        $message = "{$emoji} **{$alertData['type']}** - {$alertData['message']}";
        
        // TODO: Integrate with actual Slack notification service
        Log::info('Slack alert sent', [
            'message' => $message,
            'alert_data' => $alertData,
        ]);
    }

    /**
     * Send email alert
     */
    protected function sendEmailAlert(array $alertData): void
    {
        // TODO: Integrate with Laravel notification system
        Log::info('Email alert sent', [
            'alert_data' => $alertData,
        ]);
    }

    /**
     * Send PagerDuty alert
     */
    protected function sendPagerDutyAlert(array $alertData): void
    {
        // TODO: Integrate with PagerDuty API
        Log::info('PagerDuty alert sent', [
            'alert_data' => $alertData,
        ]);
    }

    /**
     * Send SMS alert
     */
    protected function sendSmsAlert(array $alertData): void
    {
        // TODO: Integrate with SMS service
        Log::info('SMS alert sent', [
            'alert_data' => $alertData,
        ]);
    }

    /**
     * Send webhook alert
     */
    protected function sendWebhookAlert(array $alertData): void
    {
        // TODO: Send HTTP POST to configured webhook URL
        Log::info('Webhook alert sent', [
            'alert_data' => $alertData,
        ]);
    }

    /**
     * Get alert context data
     */
    protected function getAlertContextData(array $rule): array
    {
        $type = $rule['type'] ?? '';
        
        switch ($type) {
            case 'dlq_threshold':
                return [
                    'pending_retries' => Cache::get('dlq.metrics.pending_retries', 0),
                    'manual_interventions' => Cache::get('dlq.metrics.manual_interventions', 0),
                ];
                
            case 'failure_rate':
                $timeframe = $rule['timeframe'] ?? '1h';
                return [
                    'successful' => Cache::get("workflow.metrics.performance.{$timeframe}.workflow.successful", 0),
                    'failed' => Cache::get("workflow.metrics.performance.{$timeframe}.workflow.failed", 0),
                    'timeframe' => $timeframe,
                ];
                
            case 'response_time':
                $timeframe = $rule['timeframe'] ?? '5m';
                return [
                    'avg_response_time' => Cache::get("correlation.metrics.{$timeframe}.avg_duration", 0),
                    'timeframe' => $timeframe,
                ];
                
            default:
                return [];
        }
    }

    /**
     * Store alert for dashboard display
     */
    protected function storeAlert(array $alertData): void
    {
        $alertId = uniqid('alert_');
        $alertData['id'] = $alertId;
        
        // Store individual alert
        Cache::put("alerts.{$alertId}", $alertData, now()->addHours(24));
        
        // Add to recent alerts list
        $recentAlerts = Cache::get('alerts.recent', []);
        array_unshift($recentAlerts, $alertId);
        
        // Keep only last 100 alerts
        $recentAlerts = array_slice($recentAlerts, 0, 100);
        Cache::put('alerts.recent', $recentAlerts, now()->addHours(24));
        
        // Update alert counters
        $today = now()->format('Y-m-d');
        Cache::increment("alerts.daily.{$today}.total");
        Cache::increment("alerts.daily.{$today}.{$alertData['severity']}");
        Cache::increment("alerts.daily.{$today}.{$alertData['type']}");
    }

    /**
     * Get recent alerts for dashboard
     */
    public function getRecentAlerts(int $limit = 20): array
    {
        $recentAlertIds = Cache::get('alerts.recent', []);
        $alerts = [];
        
        foreach (array_slice($recentAlertIds, 0, $limit) as $alertId) {
            $alert = Cache::get("alerts.{$alertId}");
            if ($alert) {
                $alerts[] = $alert;
            }
        }
        
        return $alerts;
    }

    /**
     * Get alert statistics
     */
    public function getAlertStatistics(): array
    {
        $today = now()->format('Y-m-d');
        
        return [
            'today' => [
                'total' => Cache::get("alerts.daily.{$today}.total", 0),
                'critical' => Cache::get("alerts.daily.{$today}.critical", 0),
                'warning' => Cache::get("alerts.daily.{$today}.warning", 0),
                'info' => Cache::get("alerts.daily.{$today}.info", 0),
            ],
            'by_type' => [
                'dlq_threshold' => Cache::get("alerts.daily.{$today}.dlq_threshold", 0),
                'failure_rate' => Cache::get("alerts.daily.{$today}.failure_rate", 0),
                'response_time' => Cache::get("alerts.daily.{$today}.response_time", 0),
                'queue_depth' => Cache::get("alerts.daily.{$today}.queue_depth", 0),
                'resource_usage' => Cache::get("alerts.daily.{$today}.resource_usage", 0),
            ],
        ];
    }
}
