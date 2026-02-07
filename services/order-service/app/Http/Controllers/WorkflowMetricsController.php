<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controller for workflow metrics and monitoring endpoints
 */
class WorkflowMetricsController extends Controller
{
    /**
     * Get workflow overview metrics
     */
    public function getOverview(): JsonResponse
    {
        try {
            $metrics = [
                'workflows' => [
                    'initiated' => Cache::get('workflow.metrics.initiated', 0),
                    'completed' => Cache::get('workflow.metrics.completed', 0),
                    'failed' => Cache::get('workflow.metrics.failed', 0),
                    'manual_intervention_required' => Cache::get('workflow.metrics.manual_intervention_required', 0),
                ],
                'activities' => [
                    'total_executed' => Cache::get('workflow.metrics.activities.total_executed', 0),
                    'successful' => Cache::get('workflow.metrics.activities.successful', 0),
                    'failed' => Cache::get('workflow.metrics.activities.failed', 0),
                ],
                'compensations' => [
                    'executed' => Cache::get('workflow.metrics.compensations.executed', 0),
                    'successful' => Cache::get('workflow.metrics.compensations.successful', 0),
                    'failed' => Cache::get('workflow.metrics.compensations.failed', 0),
                ],
                'performance' => [
                    'avg_execution_time_ms' => Cache::get('workflow.metrics.avg_execution_time', 0),
                    'success_rate' => $this->calculateSuccessRate(),
                ],
                'timestamp' => now()->toISOString(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get workflow overview metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve workflow overview metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get activity-specific metrics
     */
    public function getActivityMetrics(): JsonResponse
    {
        try {
            $activityTypes = ['payment', 'inventory', 'shipping'];
            $metrics = [];
            
            foreach ($activityTypes as $type) {
                $metrics[$type] = [
                    'executed' => Cache::get("workflow.metrics.activities.{$type}.executed", 0),
                    'successful' => Cache::get("workflow.metrics.activities.{$type}.successful", 0),
                    'failed' => Cache::get("workflow.metrics.activities.{$type}.failed", 0),
                    'avg_execution_time_ms' => Cache::get("workflow.metrics.activities.{$type}.avg_execution_time", 0),
                    'success_rate' => $this->calculateActivitySuccessRate($type),
                ];
            }
            
            // Add daily activity metrics
            $today = now()->format('Y-m-d');
            $dailyMetrics = [
                'today' => [
                    'executed' => Cache::get("workflow.metrics.daily.{$today}.activities.executed", 0),
                    'successful' => Cache::get("workflow.metrics.daily.{$today}.activities.successful", 0),
                    'failed' => Cache::get("workflow.metrics.daily.{$today}.activities.failed", 0),
                ],
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'by_type' => $metrics,
                    'daily' => $dailyMetrics,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get activity metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve activity metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get compensation metrics
     */
    public function getCompensationMetrics(): JsonResponse
    {
        try {
            $compensationTypes = ['refund', 'inventory_release', 'shipping_cancellation'];
            $metrics = [];
            
            foreach ($compensationTypes as $type) {
                $metrics[$type] = [
                    'executed' => Cache::get("workflow.metrics.compensations.{$type}.executed", 0),
                    'successful' => Cache::get("workflow.metrics.compensations.{$type}.successful", 0),
                    'failed' => Cache::get("workflow.metrics.compensations.{$type}.failed", 0),
                    'avg_execution_time_ms' => Cache::get("workflow.metrics.compensations.{$type}.avg_execution_time", 0),
                ];
            }
            
            // Add severity distribution
            $severityMetrics = [
                'high' => Cache::get('workflow.metrics.compensations.severity.high', 0),
                'medium' => Cache::get('workflow.metrics.compensations.severity.medium', 0),
                'low' => Cache::get('workflow.metrics.compensations.severity.low', 0),
            ];
            
            // Add daily compensation metrics
            $today = now()->format('Y-m-d');
            $dailyMetrics = [
                'today' => [
                    'executed' => Cache::get("workflow.metrics.daily.{$today}.compensations.executed", 0),
                    'successful' => Cache::get("workflow.metrics.daily.{$today}.compensations.successful", 0),
                    'failed' => Cache::get("workflow.metrics.daily.{$today}.compensations.failed", 0),
                ],
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'by_type' => $metrics,
                    'by_severity' => $severityMetrics,
                    'daily' => $dailyMetrics,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get compensation metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve compensation metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        try {
            $timeframe = $request->get('timeframe', '24h'); // 24h, 7d, 30d
            
            $metrics = [
                'execution_times' => [
                    'avg_workflow_duration_ms' => Cache::get("workflow.metrics.performance.{$timeframe}.avg_duration", 0),
                    'min_workflow_duration_ms' => Cache::get("workflow.metrics.performance.{$timeframe}.min_duration", 0),
                    'max_workflow_duration_ms' => Cache::get("workflow.metrics.performance.{$timeframe}.max_duration", 0),
                    'p95_workflow_duration_ms' => Cache::get("workflow.metrics.performance.{$timeframe}.p95_duration", 0),
                ],
                'throughput' => [
                    'workflows_per_hour' => Cache::get("workflow.metrics.performance.{$timeframe}.workflows_per_hour", 0),
                    'activities_per_hour' => Cache::get("workflow.metrics.performance.{$timeframe}.activities_per_hour", 0),
                ],
                'error_rates' => [
                    'workflow_failure_rate' => $this->calculateFailureRate('workflow', $timeframe),
                    'activity_failure_rate' => $this->calculateFailureRate('activity', $timeframe),
                    'compensation_failure_rate' => $this->calculateFailureRate('compensation', $timeframe),
                ],
                'resource_usage' => [
                    'avg_memory_usage_mb' => Cache::get("workflow.metrics.performance.{$timeframe}.avg_memory", 0),
                    'avg_cpu_usage_percent' => Cache::get("workflow.metrics.performance.{$timeframe}.avg_cpu", 0),
                ],
                'timeframe' => $timeframe,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get performance metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve performance metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate overall success rate
     */
    private function calculateSuccessRate(): float
    {
        $completed = Cache::get('workflow.metrics.completed', 0);
        $failed = Cache::get('workflow.metrics.failed', 0);
        $total = $completed + $failed;
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
    }

    /**
     * Calculate activity-specific success rate
     */
    private function calculateActivitySuccessRate(string $activityType): float
    {
        $successful = Cache::get("workflow.metrics.activities.{$activityType}.successful", 0);
        $failed = Cache::get("workflow.metrics.activities.{$activityType}.failed", 0);
        $total = $successful + $failed;
        
        return $total > 0 ? round(($successful / $total) * 100, 2) : 0.0;
    }

    /**
     * Calculate failure rate for different components
     */
    private function calculateFailureRate(string $component, string $timeframe): float
    {
        $successful = Cache::get("workflow.metrics.performance.{$timeframe}.{$component}.successful", 0);
        $failed = Cache::get("workflow.metrics.performance.{$timeframe}.{$component}.failed", 0);
        $total = $successful + $failed;
        
        return $total > 0 ? round(($failed / $total) * 100, 2) : 0.0;
    }
}
