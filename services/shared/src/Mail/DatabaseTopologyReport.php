<?php

namespace Shared\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * DatabaseTopologyReport
 * 
 * Mailable class for database topology reports and daily summaries.
 * Handles topology mapping reports, daily summaries, and system status reports.
 */
class DatabaseTopologyReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Report data
     */
    public array $reportData;

    /**
     * Report ID for tracking
     */
    public string $reportId;

    /**
     * Create a new message instance
     *
     * @param array $reportData Report data
     * @param string $reportId Report ID
     */
    public function __construct(array $reportData, string $reportId)
    {
        $this->reportData = $reportData;
        $this->reportId = $reportId;
        
        // Set queue for async processing
        $this->onQueue('reports');
    }

    /**
     * Build the message
     *
     * @return $this
     */
    public function build()
    {
        $reportType = $this->reportData['report_type'] ?? 'topology_report';
        
        if ($reportType === 'daily_summary') {
            return $this->buildDailySummaryReport();
        } else {
            return $this->buildTopologyReport();
        }
    }

    /**
     * Build topology mapping report
     *
     * @return $this
     */
    private function buildTopologyReport()
    {
        $subject = '📊 Database Topology Mapping Report - ' . now()->format('Y-m-d H:i');
        
        return $this->subject($subject)
                    ->view('emails.database-topology.report')
                    ->with([
                        'reportType' => 'topology_mapping',
                        'reportId' => $this->reportId,
                        'timestamp' => $this->reportData['timestamp'] ?? now()->toISOString(),
                        'environment' => $this->reportData['environment'] ?? config('app.env'),
                        'serviceName' => $this->reportData['service_name'] ?? 'Database Failover System',
                        'topologyData' => $this->formatTopologyData(),
                        'connectionSummary' => $this->generateConnectionSummary(),
                        'healthStatus' => $this->analyzeHealthStatus(),
                        'recommendations' => $this->generateTopologyRecommendations(),
                        'circuitBreakerConfigs' => $this->formatCircuitBreakerConfigs()
                    ]);
    }

    /**
     * Build daily summary report
     *
     * @return $this
     */
    private function buildDailySummaryReport()
    {
        $date = $this->reportData['date'] ?? now()->format('Y-m-d');
        $subject = '📈 Daily Database Failover Summary - ' . $date;
        
        return $this->subject($subject)
                    ->view('emails.database-topology.daily-summary')
                    ->with([
                        'reportType' => 'daily_summary',
                        'reportId' => $this->reportId,
                        'date' => $date,
                        'period' => $this->reportData['period'] ?? 'last_24_hours',
                        'eventsSummary' => $this->formatEventsSummary(),
                        'topEvents' => $this->reportData['top_events'] ?? [],
                        'circuitBreakerStats' => $this->reportData['circuit_breaker_stats'] ?? [],
                        'connectionHealthSummary' => $this->reportData['connection_health_summary'] ?? [],
                        'trends' => $this->analyzeTrends(),
                        'recommendations' => $this->generateDailyRecommendations()
                    ]);
    }

    /**
     * Format topology data for display
     *
     * @return array Formatted topology data
     */
    private function formatTopologyData(): array
    {
        if (!isset($this->reportData['topology_data'])) {
            return [];
        }

        $topology = $this->reportData['topology_data']['topology'] ?? [];
        
        return [
            'total_connections' => $topology['total_connections'] ?? 0,
            'connections_by_criticality' => $topology['by_criticality'] ?? [],
            'connections_by_type' => $topology['by_type'] ?? [],
            'connections_by_load' => $topology['by_load'] ?? [],
            'replication_pairs' => $topology['replication_pairs'] ?? [],
            'health_status' => $topology['health_status'] ?? []
        ];
    }

    /**
     * Generate connection summary
     *
     * @return array Connection summary
     */
    private function generateConnectionSummary(): array
    {
        $topology = $this->formatTopologyData();
        
        $summary = [
            'total' => $topology['total_connections'],
            'healthy' => 0,
            'unhealthy' => 0,
            'by_criticality' => [],
            'by_type' => []
        ];

        // Count healthy/unhealthy connections
        foreach ($topology['health_status'] as $connection => $status) {
            if ($status === 'healthy') {
                $summary['healthy']++;
            } else {
                $summary['unhealthy']++;
            }
        }

        // Summarize by criticality
        foreach ($topology['connections_by_criticality'] as $level => $connections) {
            $summary['by_criticality'][$level] = count($connections);
        }

        // Summarize by type
        foreach ($topology['connections_by_type'] as $type => $connections) {
            $summary['by_type'][$type] = count($connections);
        }

        return $summary;
    }

    /**
     * Analyze health status
     *
     * @return array Health analysis
     */
    private function analyzeHealthStatus(): array
    {
        $topology = $this->formatTopologyData();
        $healthStatus = $topology['health_status'];
        
        $analysis = [
            'overall_health' => 'unknown',
            'health_percentage' => 0,
            'critical_issues' => [],
            'warnings' => []
        ];

        if (empty($healthStatus)) {
            return $analysis;
        }

        $total = count($healthStatus);
        $healthy = count(array_filter($healthStatus, fn($status) => $status === 'healthy'));
        $analysis['health_percentage'] = round(($healthy / $total) * 100, 1);

        // Determine overall health
        if ($analysis['health_percentage'] >= 95) {
            $analysis['overall_health'] = 'excellent';
        } elseif ($analysis['health_percentage'] >= 80) {
            $analysis['overall_health'] = 'good';
        } elseif ($analysis['health_percentage'] >= 60) {
            $analysis['overall_health'] = 'fair';
        } else {
            $analysis['overall_health'] = 'poor';
        }

        // Identify critical issues
        foreach ($healthStatus as $connection => $status) {
            if ($status !== 'healthy') {
                $criticality = $this->getConnectionCriticality($connection);
                if (in_array($criticality, ['critical', 'high'])) {
                    $analysis['critical_issues'][] = [
                        'connection' => $connection,
                        'status' => $status,
                        'criticality' => $criticality
                    ];
                } else {
                    $analysis['warnings'][] = [
                        'connection' => $connection,
                        'status' => $status,
                        'criticality' => $criticality
                    ];
                }
            }
        }

        return $analysis;
    }

    /**
     * Get connection criticality
     *
     * @param string $connection Connection name
     * @return string Criticality level
     */
    private function getConnectionCriticality(string $connection): string
    {
        $topology = $this->formatTopologyData();
        
        foreach ($topology['connections_by_criticality'] as $level => $connections) {
            if (in_array($connection, $connections)) {
                return $level;
            }
        }
        
        return 'medium';
    }

    /**
     * Generate topology recommendations
     *
     * @return array Recommendations
     */
    private function generateTopologyRecommendations(): array
    {
        $healthStatus = $this->analyzeHealthStatus();
        $recommendations = [];

        if ($healthStatus['health_percentage'] < 80) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Address Unhealthy Connections',
                'description' => 'Multiple database connections are unhealthy. Investigate and resolve connection issues.',
                'action' => 'Review connection logs and server status'
            ];
        }

        if (!empty($healthStatus['critical_issues'])) {
            $recommendations[] = [
                'priority' => 'critical',
                'title' => 'Critical Connection Issues',
                'description' => 'Critical database connections are experiencing issues.',
                'action' => 'Immediate investigation and resolution required'
            ];
        }

        $topology = $this->formatTopologyData();
        if (empty($topology['replication_pairs'])) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'No Replication Pairs Detected',
                'description' => 'No master-replica pairs were identified in the topology.',
                'action' => 'Review database replication setup and naming conventions'
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'info',
                'title' => 'Topology Healthy',
                'description' => 'Database topology appears to be healthy and well-configured.',
                'action' => 'Continue monitoring for any changes'
            ];
        }

        return $recommendations;
    }

    /**
     * Format circuit breaker configurations
     *
     * @return array Formatted configurations
     */
    private function formatCircuitBreakerConfigs(): array
    {
        if (!isset($this->reportData['topology_data']['circuit_configs'])) {
            return [];
        }

        $configs = $this->reportData['topology_data']['circuit_configs'];
        $formatted = [];

        foreach ($configs as $connection => $config) {
            $formatted[] = [
                'connection' => $connection,
                'failure_threshold' => $config['failure_threshold'] ?? 'N/A',
                'recovery_timeout' => $config['recovery_timeout'] ?? 'N/A',
                'success_threshold' => $config['success_threshold'] ?? 'N/A',
                'timeout' => $config['timeout'] ?? 'N/A',
                'generated_at' => $config['generated_at'] ?? 'N/A'
            ];
        }

        return $formatted;
    }

    /**
     * Format events summary for daily report
     *
     * @return array Formatted events summary
     */
    private function formatEventsSummary(): array
    {
        return [
            'total_events' => $this->reportData['total_events'] ?? 0,
            'events_by_severity' => $this->reportData['events_by_severity'] ?? [],
            'severity_distribution' => $this->calculateSeverityDistribution()
        ];
    }

    /**
     * Calculate severity distribution percentages
     *
     * @return array Severity distribution
     */
    private function calculateSeverityDistribution(): array
    {
        $eventsBySeverity = $this->reportData['events_by_severity'] ?? [];
        $total = array_sum($eventsBySeverity);
        
        if ($total === 0) {
            return [];
        }

        $distribution = [];
        foreach ($eventsBySeverity as $severity => $count) {
            $distribution[$severity] = [
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1)
            ];
        }

        return $distribution;
    }

    /**
     * Analyze trends from daily data
     *
     * @return array Trend analysis
     */
    private function analyzeTrends(): array
    {
        $eventsBySeverity = $this->reportData['events_by_severity'] ?? [];
        
        return [
            'critical_events_trend' => $this->determineTrend($eventsBySeverity['critical'] ?? 0),
            'high_events_trend' => $this->determineTrend($eventsBySeverity['high'] ?? 0),
            'total_events_trend' => $this->determineTrend($this->reportData['total_events'] ?? 0),
            'overall_health_trend' => $this->determineOverallHealthTrend($eventsBySeverity)
        ];
    }

    /**
     * Determine trend direction
     *
     * @param int $currentValue Current value
     * @return array Trend information
     */
    private function determineTrend(int $currentValue): array
    {
        // In a real implementation, this would compare with historical data
        // For now, we'll provide a placeholder structure
        
        return [
            'direction' => 'stable', // up, down, stable
            'change_percentage' => 0,
            'status' => $currentValue === 0 ? 'good' : ($currentValue > 10 ? 'concerning' : 'normal')
        ];
    }

    /**
     * Determine overall health trend
     *
     * @param array $eventsBySeverity Events by severity
     * @return array Health trend
     */
    private function determineOverallHealthTrend(array $eventsBySeverity): array
    {
        $criticalEvents = $eventsBySeverity['critical'] ?? 0;
        $highEvents = $eventsBySeverity['high'] ?? 0;
        
        if ($criticalEvents > 0) {
            $status = 'poor';
        } elseif ($highEvents > 5) {
            $status = 'fair';
        } elseif ($highEvents > 0) {
            $status = 'good';
        } else {
            $status = 'excellent';
        }

        return [
            'status' => $status,
            'direction' => 'stable', // Would be calculated from historical data
            'recommendation' => $this->getHealthRecommendation($status)
        ];
    }

    /**
     * Get health recommendation based on status
     *
     * @param string $status Health status
     * @return string Recommendation
     */
    private function getHealthRecommendation(string $status): string
    {
        return match($status) {
            'poor' => 'Immediate attention required - multiple critical issues detected',
            'fair' => 'Monitor closely - several high-priority issues need attention',
            'good' => 'System is stable but continue monitoring for improvements',
            'excellent' => 'System is performing well - maintain current practices',
            default => 'Continue monitoring system health'
        };
    }

    /**
     * Generate daily recommendations
     *
     * @return array Daily recommendations
     */
    private function generateDailyRecommendations(): array
    {
        $trends = $this->analyzeTrends();
        $eventsSummary = $this->formatEventsSummary();
        $recommendations = [];

        if ($eventsSummary['total_events'] === 0) {
            $recommendations[] = [
                'priority' => 'info',
                'title' => 'No Events Recorded',
                'description' => 'No database failover events occurred in the last 24 hours.',
                'action' => 'System appears stable - continue monitoring'
            ];
        } else {
            if (($eventsSummary['events_by_severity']['critical'] ?? 0) > 0) {
                $recommendations[] = [
                    'priority' => 'critical',
                    'title' => 'Critical Events Detected',
                    'description' => 'Critical database events occurred in the last 24 hours.',
                    'action' => 'Review critical events and implement preventive measures'
                ];
            }

            if (($eventsSummary['events_by_severity']['high'] ?? 0) > 5) {
                $recommendations[] = [
                    'priority' => 'high',
                    'title' => 'High Number of High-Priority Events',
                    'description' => 'Multiple high-priority events suggest system stress.',
                    'action' => 'Investigate root causes and consider infrastructure scaling'
                ];
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'info',
                'title' => 'System Operating Normally',
                'description' => 'Database failover system is operating within normal parameters.',
                'action' => 'Continue current monitoring and maintenance practices'
            ];
        }

        return $recommendations;
    }
}
