<?php

namespace App\Services;

use App\Events\Workflow\ActivityCompleted;
use App\Events\Workflow\CompensationExecuted;
use App\Events\Workflow\OrderWorkflowInitiated;
use App\Events\Workflow\WorkflowCompleted;
use App\Events\Workflow\WorkflowFailed;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Service for publishing workflow-related events
 */
class WorkflowEventPublisher
{
    /**
     * Publish workflow initiated event
     */
    public function publishWorkflowInitiated(Order $order, string $workflowId, array $workflowData): void
    {
        try {
            event(new OrderWorkflowInitiated($order, $workflowId, $workflowData));
            
            Log::info('Workflow initiated event published', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'customer_id' => $order->customer_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish workflow initiated event', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publish activity completed event
     */
    public function publishActivityCompleted(
        Order $order,
        string $workflowId,
        string $activityName,
        array $activityResult,
        float $executionTime = 0.0
    ): void {
        try {
            event(new ActivityCompleted($order, $workflowId, $activityName, $activityResult, $executionTime));
            
            Log::info('Activity completed event published', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'activity_name' => $activityName,
                'success' => $activityResult['success'] ?? false,
                'execution_time' => $executionTime,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish activity completed event', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'activity_name' => $activityName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publish compensation executed event
     */
    public function publishCompensationExecuted(
        Order $order,
        string $workflowId,
        string $compensationActivity,
        array $compensationResult,
        string $originalActivity,
        string $failureReason
    ): void {
        try {
            event(new CompensationExecuted(
                $order,
                $workflowId,
                $compensationActivity,
                $compensationResult,
                $originalActivity,
                $failureReason
            ));
            
            Log::warning('Compensation executed event published', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'compensation_activity' => $compensationActivity,
                'original_activity' => $originalActivity,
                'failure_reason' => $failureReason,
                'success' => $compensationResult['success'] ?? false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish compensation executed event', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'compensation_activity' => $compensationActivity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publish workflow completed event
     */
    public function publishWorkflowCompleted(
        Order $order,
        string $workflowId,
        array $finalResult,
        float $totalExecutionTime,
        array $completedActivities = []
    ): void {
        try {
            event(new WorkflowCompleted($order, $workflowId, $finalResult, $totalExecutionTime, $completedActivities));
            
            Log::info('Workflow completed event published', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'total_execution_time' => $totalExecutionTime,
                'activities_completed' => count($completedActivities),
                'final_state' => $order->state::class,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish workflow completed event', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publish workflow failed event
     */
    public function publishWorkflowFailed(
        Order $order,
        string $workflowId,
        string $failureReason,
        string $failedActivity,
        array $errorDetails = [],
        array $compensationsExecuted = [],
        float $executionTimeBeforeFailure = 0.0
    ): void {
        try {
            event(new WorkflowFailed(
                $order,
                $workflowId,
                $failureReason,
                $failedActivity,
                $errorDetails,
                $compensationsExecuted,
                $executionTimeBeforeFailure
            ));
            
            Log::error('Workflow failed event published', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'failure_reason' => $failureReason,
                'failed_activity' => $failedActivity,
                'compensations_executed' => count($compensationsExecuted),
                'execution_time_before_failure' => $executionTimeBeforeFailure,
            ]);
        } catch (\Exception $e) {
            Log::critical('Failed to publish workflow failed event', [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'failure_reason' => $failureReason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publish multiple events in batch
     */
    public function publishBatch(array $events): void
    {
        foreach ($events as $event) {
            try {
                event($event);
            } catch (\Exception $e) {
                Log::error('Failed to publish event in batch', [
                    'event_class' => get_class($event),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
