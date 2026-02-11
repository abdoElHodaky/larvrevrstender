<?php

namespace App\Listeners\Workflow;

use App\Events\Workflow\ActivityCompleted;
use App\Events\Workflow\CompensationExecuted;
use App\Events\Workflow\OrderWorkflowInitiated;
use App\Events\Workflow\WorkflowCompleted;
use App\Events\Workflow\WorkflowFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Listener for collecting workflow metrics and analytics
 */
class CollectWorkflowMetrics implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle workflow initiated event
     */
    public function handleWorkflowInitiated(OrderWorkflowInitiated $event): void
    {
        try {
            // Increment workflow initiated counter
            $this->incrementMetric('workflows.initiated.total');
            $this->incrementMetric('workflows.initiated.today', now()->format('Y-m-d'));
            
            // Track by customer
            $this->incrementMetric("workflows.initiated.customer.{$event->order->customer_id}");
            
            // Track workflow start time
            Cache::put(
                "workflow.start_time.{$event->workflowId}",
                microtime(true),
                now()->addHours(24)
            );

            Log::info('Workflow initiated metrics collected', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to collect workflow initiated metrics', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle activity completed event
     */
    public function handleActivityCompleted(ActivityCompleted $event): void
    {
        try {
            $activityType = $this->getActivityType($event->activityName);
            
            // Increment activity completion counters
            $this->incrementMetric('activities.completed.total');
            $this->incrementMetric("activities.completed.{$activityType}");
            $this->incrementMetric("activities.completed.today", now()->format('Y-m-d'));
            
            // Track execution time
            $this->recordExecutionTime("activities.execution_time.{$activityType}", $event->executionTime);
            
            // Track success/failure rates
            if ($event->activityResult['success'] ?? false) {
                $this->incrementMetric("activities.success.{$activityType}");
            } else {
                $this->incrementMetric("activities.failure.{$activityType}");
            }

            Log::info('Activity completed metrics collected', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'activity_type' => $activityType,
                'execution_time' => $event->executionTime,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to collect activity completed metrics', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'activity_name' => $event->activityName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle compensation executed event
     */
    public function handleCompensationExecuted(CompensationExecuted $event): void
    {
        try {
            $compensationType = $this->getCompensationType($event->compensationActivity);
            
            // Increment compensation counters
            $this->incrementMetric('compensations.executed.total');
            $this->incrementMetric("compensations.executed.{$compensationType}");
            $this->incrementMetric("compensations.executed.today", now()->format('Y-m-d'));
            
            // Track by severity
            $this->incrementMetric("compensations.severity.{$event->getSeverity()}");
            
            // Track success/failure of compensations
            if ($event->compensationResult['success'] ?? false) {
                $this->incrementMetric("compensations.success.{$compensationType}");
            } else {
                $this->incrementMetric("compensations.failure.{$compensationType}");
            }

            Log::warning('Compensation executed metrics collected', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'compensation_type' => $compensationType,
                'severity' => $event->getSeverity(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to collect compensation executed metrics', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'compensation_activity' => $event->compensationActivity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle workflow completed event
     */
    public function handleWorkflowCompleted(WorkflowCompleted $event): void
    {
        try {
            // Increment completion counters
            $this->incrementMetric('workflows.completed.total');
            $this->incrementMetric('workflows.completed.today', now()->format('Y-m-d'));
            
            // Calculate and record total execution time
            $startTime = Cache::get("workflow.start_time.{$event->workflowId}");
            if ($startTime) {
                $totalTime = microtime(true) - $startTime;
                $this->recordExecutionTime('workflows.execution_time.total', $totalTime);
                Cache::forget("workflow.start_time.{$event->workflowId}");
            }
            
            // Record provided execution time
            $this->recordExecutionTime('workflows.execution_time.reported', $event->totalExecutionTime);
            
            // Track activities completed
            $this->recordValue('workflows.activities_per_completion', count($event->completedActivities));

            Log::info('Workflow completed metrics collected', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'total_execution_time' => $event->totalExecutionTime,
                'activities_completed' => count($event->completedActivities),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to collect workflow completed metrics', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle workflow failed event
     */
    public function handleWorkflowFailed(WorkflowFailed $event): void
    {
        try {
            $failedActivityType = $this->getActivityType($event->failedActivity);
            
            // Increment failure counters
            $this->incrementMetric('workflows.failed.total');
            $this->incrementMetric("workflows.failed.{$failedActivityType}");
            $this->incrementMetric('workflows.failed.today', now()->format('Y-m-d'));
            
            // Track by severity
            $this->incrementMetric("workflows.failed.severity.{$event->getSeverity()}");
            
            // Track manual intervention requirements
            if ($event->requiresManualIntervention()) {
                $this->incrementMetric('workflows.manual_intervention_required');
            }
            
            // Calculate execution time before failure
            $startTime = Cache::get("workflow.start_time.{$event->workflowId}");
            if ($startTime) {
                $timeBeforeFailure = microtime(true) - $startTime;
                $this->recordExecutionTime('workflows.time_before_failure', $timeBeforeFailure);
                Cache::forget("workflow.start_time.{$event->workflowId}");
            }
            
            // Track compensations executed
            $this->recordValue('workflows.compensations_per_failure', count($event->compensationsExecuted));

            Log::error('Workflow failed metrics collected', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'failed_activity_type' => $failedActivityType,
                'severity' => $event->getSeverity(),
                'compensations_executed' => count($event->compensationsExecuted),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to collect workflow failed metrics', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'failed_activity' => $event->failedActivity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Increment a metric counter
     */
    private function incrementMetric(string $key, ?string $date = null): void
    {
        $cacheKey = $date ? "{$key}.{$date}" : $key;
        $ttl = $date ? now()->addDays(30) : now()->addHours(24);
        
        Cache::increment($cacheKey, 1);
        Cache::put($cacheKey . '.ttl', $ttl, $ttl);
    }

    /**
     * Record execution time for averaging
     */
    private function recordExecutionTime(string $key, float $time): void
    {
        $times = Cache::get("{$key}.times", []);
        $times[] = $time;
        
        // Keep only last 1000 measurements
        if (count($times) > 1000) {
            $times = array_slice($times, -1000);
        }
        
        Cache::put("{$key}.times", $times, now()->addHours(24));
        Cache::put("{$key}.average", array_sum($times) / count($times), now()->addHours(24));
    }

    /**
     * Record a value for statistical analysis
     */
    private function recordValue(string $key, $value): void
    {
        $values = Cache::get("{$key}.values", []);
        $values[] = $value;
        
        // Keep only last 1000 measurements
        if (count($values) > 1000) {
            $values = array_slice($values, -1000);
        }
        
        Cache::put("{$key}.values", $values, now()->addHours(24));
        Cache::put("{$key}.average", array_sum($values) / count($values), now()->addHours(24));
    }

    /**
     * Get activity type from activity name
     */
    private function getActivityType(string $activityName): string
    {
        if (str_contains($activityName, 'Payment')) {
            return 'payment';
        } elseif (str_contains($activityName, 'Inventory')) {
            return 'inventory';
        } elseif (str_contains($activityName, 'Shipping')) {
            return 'shipping';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get compensation type from compensation activity name
     */
    private function getCompensationType(string $compensationActivity): string
    {
        if (str_contains($compensationActivity, 'Refund')) {
            return 'refund';
        } elseif (str_contains($compensationActivity, 'Release')) {
            return 'inventory_release';
        } elseif (str_contains($compensationActivity, 'Cancel')) {
            return 'shipping_cancel';
        } else {
            return 'unknown';
        }
    }
}
