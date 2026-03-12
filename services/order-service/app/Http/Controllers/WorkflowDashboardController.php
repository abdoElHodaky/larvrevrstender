<?php

namespace App\Http\Controllers;

use App\Services\WorkflowDeadLetterQueue;
use App\Services\WorkflowSignalHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controller for workflow dashboard data aggregation
 */
class WorkflowDashboardController extends Controller
{
    public function __construct(
        protected WorkflowDeadLetterQueue$dlqService,
        protected WorkflowSignalHandler$signalHandler
    ) {
    }

    /**
     * Get executive dashboard data
     */
    public function getExecutiveDashboard(): JsonResponse
    {
        try {
            $data = [
                'workflow_overview' => $this->getWorkflowOverview(),
                'real_time_status' => $this->getRealTimeStatus(),
                'performance_summary' => $this->getPerformanceSummary(),
                'trend_data' => $this->getTrendData(),
                'timestamp' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'refresh_interval' => 30, // seconds
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get executive dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve dashboard data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get operations dashboard data
     */
    public function getOperationsDashboard(): JsonResponse
    {
        try {
            $data = [
                'active_workflows' => $this->getActiveWorkflows(),
                'dlq_status' => $this->getDlqStatus(),
                'signal_status' => $this->getSignalStatus(),
                'intervention_queue' => $this->getInterventionQueue(),
                'recent_activities' => $this->getRecentActivities(),
                'timestamp' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'refresh_interval' => 10, // seconds
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get operations dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve operations dashboard data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance dashboard data
     */
    public function getPerformanceDashboard(Request $request): JsonResponse
    {
        try {
            $timeframe = $request->get('timeframe', '24h');
            
            $data = [
                'execution_metrics' => $this->getExecutionMetrics($timeframe),
                'throughput_metrics' => $this->getThroughputMetrics($timeframe),
                'error_analysis' => $this->getErrorAnalysis($timeframe),
                'resource_utilization' => $this->getResourceUtilization($timeframe),
                'correlation_metrics' => $this->getCorrelationMetrics($timeframe),
                'timeframe' => $timeframe,
                'timestamp' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'refresh_interval' => 60, // seconds
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get performance dashboard data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timeframe' => $request->get('timeframe', '24h'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve performance dashboard data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workflow overview metrics
     */
    private function getWorkflowOverview(): array
    {
        return [
            'total_active' => Cache::get('workflow.metrics.active_workflows', 0),
            'completed_today' => Cache::get('workflow.metrics.daily.' . now()->format('Y-m-d') . '.completed', 0),
            'failed_today' => Cache::get('workflow.metrics.daily.' . now()->format('Y-m-d') . '.failed', 0),
            'success_rate' => $this->calculateDailySuccessRate(),
            'avg_execution_time' => Cache::get('workflow.metrics.avg_execution_time', 0),
            'total_initiated' => Cache::get('workflow.metrics.initiated', 0),
            'total_completed' => Cache::get('workflow.metrics.completed', 0),
        ];
    }

    /**
     * Get real-time status
     */
    private function getRealTimeStatus(): array
    {
        $dlqStats = $this->dlqService->getStatistics();
        
        return [
            'paused_workflows' => Cache::get('workflow.signals.paused_count', 0),
            'pending_interventions' => $dlqStats['manual_interventions'] ?? 0,
            'dlq_items' => $dlqStats['pending_retries'] ?? 0,
            'active_signals' => Cache::get('workflow.signals.active_count', 0),
            'queue_depth' => $this->getQueueDepth(),
        ];
    }

    /**
     * Get performance summary
     */
    private function getPerformanceSummary(): array
    {
        return [
            'avg_workflow_duration' => Cache::get('workflow.metrics.performance.24h.avg_duration', 0),
            'p95_workflow_duration' => Cache::get('workflow.metrics.performance.24h.p95_duration', 0),
            'workflows_per_hour' => Cache::get('workflow.metrics.performance.24h.workflows_per_hour', 0),
            'activities_per_hour' => Cache::get('workflow.metrics.performance.24h.activities_per_hour', 0),
            'error_rate' => $this->calculateErrorRate('24h'),
        ];
    }

    /**
     * Get trend data for charts
     */
    private function getTrendData(): array
    {
        $days = 7;
        $trends = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $trends[] = [
                'date' => $date,
                'completed' => Cache::get("workflow.metrics.daily.{$date}.completed", 0),
                'failed' => Cache::get("workflow.metrics.daily.{$date}.failed", 0),
                'avg_duration' => Cache::get("workflow.metrics.daily.{$date}.avg_duration", 0),
            ];
        }
        
        return $trends;
    }

    /**
     * Get active workflows
     */
    private function getActiveWorkflows(): array
    {
        // This would typically query a database or cache for active workflows
        // For now, return mock data structure
        return [
            'count' => Cache::get('workflow.metrics.active_workflows', 0),
            'by_status' => [
                'running' => Cache::get('workflow.status.running', 0),
                'paused' => Cache::get('workflow.status.paused', 0),
                'waiting' => Cache::get('workflow.status.waiting', 0),
            ],
            'recent' => $this->getRecentWorkflows(),
        ];
    }

    /**
     * Get DLQ status
     */
    private function getDlqStatus(): array
    {
        return $this->dlqService->getStatistics();
    }

    /**
     * Get signal status
     */
    private function getSignalStatus(): array
    {
        return [
            'total_signals_today' => Cache::get('workflow.signals.daily.' . now()->format('Y-m-d') . '.total', 0),
            'pause_signals' => Cache::get('workflow.signals.daily.' . now()->format('Y-m-d') . '.pause', 0),
            'resume_signals' => Cache::get('workflow.signals.daily.' . now()->format('Y-m-d') . '.resume', 0),
            'intervention_requests' => Cache::get('workflow.signals.daily.' . now()->format('Y-m-d') . '.intervention', 0),
            'active_paused' => Cache::get('workflow.signals.paused_count', 0),
        ];
    }

    /**
     * Get intervention queue
     */
    private function getInterventionQueue(): array
    {
        $queue = $this->dlqService->getManualInterventionQueue();
        
        return [
            'total' => count($queue),
            'by_priority' => $this->groupInterventionsByPriority($queue),
            'oldest' => $this->getOldestIntervention($queue),
            'recent' => array_slice($queue, 0, 5), // Last 5 interventions
        ];
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(): array
    {
        // This would typically query logs or activity tables
        // For now, return structure for recent activities
        return [
            'workflow_initiated' => Cache::get('workflow.recent.initiated', []),
            'workflow_completed' => Cache::get('workflow.recent.completed', []),
            'interventions_resolved' => Cache::get('workflow.recent.interventions_resolved', []),
            'dlq_retries' => Cache::get('workflow.recent.dlq_retries', []),
        ];
    }

    /**
     * Calculate daily success rate
     */
    private function calculateDailySuccessRate(): float
    {
        $today = now()->format('Y-m-d');
        $completed = Cache::get("workflow.metrics.daily.{$today}.completed", 0);
        $failed = Cache::get("workflow.metrics.daily.{$today}.failed", 0);
        $total = $completed + $failed;
        
        return $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
    }

    /**
     * Get queue depth across all queues
     */
    private function getQueueDepth(): array
    {
        return [
            'signals_high' => Cache::get('queue.depth.signals-high', 0),
            'signals_medium' => Cache::get('queue.depth.signals-medium', 0),
            'signals_low' => Cache::get('queue.depth.signals-low', 0),
            'dlq_payment' => Cache::get('queue.depth.dlq-payment', 0),
            'dlq_inventory' => Cache::get('queue.depth.dlq-inventory', 0),
            'dlq_shipping' => Cache::get('queue.depth.dlq-shipping', 0),
        ];
    }

    /**
     * Calculate error rate for timeframe
     */
    private function calculateErrorRate(string $timeframe): float
    {
        $successful = Cache::get("workflow.metrics.performance.{$timeframe}.workflow.successful", 0);
        $failed = Cache::get("workflow.metrics.performance.{$timeframe}.workflow.failed", 0);
        $total = $successful + $failed;
        
        return $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;
    }

    /**
     * Get execution metrics for timeframe
     */
    private function getExecutionMetrics(string $timeframe): array
    {
        return [
            'avg_duration' => Cache::get("workflow.metrics.performance.{$timeframe}.avg_duration", 0),
            'min_duration' => Cache::get("workflow.metrics.performance.{$timeframe}.min_duration", 0),
            'max_duration' => Cache::get("workflow.metrics.performance.{$timeframe}.max_duration", 0),
            'p50_duration' => Cache::get("workflow.metrics.performance.{$timeframe}.p50_duration", 0),
            'p95_duration' => Cache::get("workflow.metrics.performance.{$timeframe}.p95_duration", 0),
            'p99_duration' => Cache::get("workflow.metrics.performance.{$timeframe}.p99_duration", 0),
        ];
    }

    /**
     * Get throughput metrics for timeframe
     */
    private function getThroughputMetrics(string $timeframe): array
    {
        return [
            'workflows_per_hour' => Cache::get("workflow.metrics.performance.{$timeframe}.workflows_per_hour", 0),
            'activities_per_hour' => Cache::get("workflow.metrics.performance.{$timeframe}.activities_per_hour", 0),
            'peak_throughput' => Cache::get("workflow.metrics.performance.{$timeframe}.peak_throughput", 0),
            'avg_throughput' => Cache::get("workflow.metrics.performance.{$timeframe}.avg_throughput", 0),
        ];
    }

    /**
     * Get error analysis for timeframe
     */
    private function getErrorAnalysis(string $timeframe): array
    {
        return [
            'total_errors' => Cache::get("workflow.metrics.performance.{$timeframe}.total_errors", 0),
            'error_rate' => $this->calculateErrorRate($timeframe),
            'by_activity_type' => [
                'payment' => Cache::get("workflow.metrics.performance.{$timeframe}.errors.payment", 0),
                'inventory' => Cache::get("workflow.metrics.performance.{$timeframe}.errors.inventory", 0),
                'shipping' => Cache::get("workflow.metrics.performance.{$timeframe}.errors.shipping", 0),
            ],
            'top_error_reasons' => Cache::get("workflow.metrics.performance.{$timeframe}.top_errors", []),
        ];
    }

    /**
     * Get resource utilization for timeframe
     */
    private function getResourceUtilization(string $timeframe): array
    {
        return [
            'avg_memory_usage' => Cache::get("workflow.metrics.performance.{$timeframe}.avg_memory", 0),
            'peak_memory_usage' => Cache::get("workflow.metrics.performance.{$timeframe}.peak_memory", 0),
            'avg_cpu_usage' => Cache::get("workflow.metrics.performance.{$timeframe}.avg_cpu", 0),
            'peak_cpu_usage' => Cache::get("workflow.metrics.performance.{$timeframe}.peak_cpu", 0),
        ];
    }

    /**
     * Get correlation metrics for timeframe
     */
    private function getCorrelationMetrics(string $timeframe): array
    {
        return [
            'total_correlations' => Cache::get("correlation.metrics.{$timeframe}.total", 0),
            'avg_correlation_duration' => Cache::get("correlation.metrics.{$timeframe}.avg_duration", 0),
            'rpc_calls_total' => Cache::get("correlation.metrics.{$timeframe}.rpc_calls", 0),
            'avg_rpc_duration' => Cache::get("correlation.metrics.{$timeframe}.avg_rpc_duration", 0),
            'services_involved' => Cache::get("correlation.metrics.{$timeframe}.services", []),
        ];
    }

    /**
     * Get recent workflows
     */
    private function getRecentWorkflows(): array
    {
        return Cache::get('workflow.recent.workflows', []);
    }

    /**
     * Group interventions by priority
     */
    private function groupInterventionsByPriority(array $queue): array
    {
        $grouped = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        
        foreach ($queue as $intervention) {
            $priority = $intervention['priority'] ?? 'medium';
            if (isset($grouped[$priority])) {
                $grouped[$priority]++;
            }
        }
        
        return $grouped;
    }

    /**
     * Get oldest intervention
     */
    private function getOldestIntervention(array $queue): ?array
    {
        if (empty($queue)) {
            return null;
        }
        
        $oldest = null;
        $oldestTime = null;
        
        foreach ($queue as $intervention) {
            $createdAt = $intervention['created_at'] ?? null;
            if ($createdAt && (!$oldestTime || $createdAt < $oldestTime)) {
                $oldest = $intervention;
                $oldestTime = $createdAt;
            }
        }
        
        return $oldest;
    }
}
