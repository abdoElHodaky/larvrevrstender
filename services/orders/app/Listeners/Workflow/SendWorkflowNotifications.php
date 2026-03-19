<?php

namespace App\Listeners\Workflow;

use App\Events\Workflow\ActivityCompleted;
use App\Events\Workflow\CompensationExecuted;
use App\Events\Workflow\OrderWorkflowInitiated;
use App\Events\Workflow\WorkflowCompleted;
use App\Events\Workflow\WorkflowFailed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener for sending workflow-related notifications
 */
class SendWorkflowNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle workflow initiated event
     */
    public function handleWorkflowInitiated(OrderWorkflowInitiated $event): void
    {
        try {
            // Send notification to customer
            $this->notificationService->sendWorkflowInitiatedNotification(
                $event->order,
                $event->workflowId
            );

            // Send notification to internal team
            $this->notificationService->sendInternalWorkflowNotification(
                'workflow_initiated',
                $event->order,
                [
                    'workflow_id' => $event->workflowId,
                    'timestamp' => $event->timestamp,
                ]
            );

            Log::info('Workflow initiated notifications sent', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send workflow initiated notifications', [
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
            // Send progress update to customer for major milestones
            if ($this->isMajorMilestone($event->activityName)) {
                $this->notificationService->sendActivityProgressNotification(
                    $event->order,
                    $event->activityName,
                    $event->calculateProgress()
                );
            }

            Log::info('Activity completed notifications processed', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'activity_name' => $event->activityName,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send activity completed notifications', [
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
            // Send urgent notification for compensation events
            $this->notificationService->sendCompensationNotification(
                $event->order,
                $event->compensationActivity,
                $event->originalActivity,
                $event->failureReason
            );

            // Alert internal team immediately
            $this->notificationService->sendUrgentInternalAlert(
                'compensation_executed',
                $event->order,
                [
                    'workflow_id' => $event->workflowId,
                    'compensation_activity' => $event->compensationActivity,
                    'original_activity' => $event->originalActivity,
                    'failure_reason' => $event->failureReason,
                    'severity' => $event->getSeverity(),
                ]
            );

            Log::warning('Compensation executed notifications sent', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'compensation_activity' => $event->compensationActivity,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send compensation executed notifications', [
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
            // Send completion notification to customer
            $this->notificationService->sendWorkflowCompletedNotification(
                $event->order,
                $event->finalResult
            );

            // Send success notification to internal team
            $this->notificationService->sendInternalWorkflowNotification(
                'workflow_completed',
                $event->order,
                [
                    'workflow_id' => $event->workflowId,
                    'total_execution_time' => $event->totalExecutionTime,
                    'activities_completed' => count($event->completedActivities),
                    'final_result' => $event->finalResult,
                ]
            );

            Log::info('Workflow completed notifications sent', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'total_execution_time' => $event->totalExecutionTime,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send workflow completed notifications', [
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
            // Send failure notification to customer
            $this->notificationService->sendWorkflowFailedNotification(
                $event->order,
                $event->failureReason,
                $event->requiresManualIntervention()
            );

            // Send critical alert to internal team
            $this->notificationService->sendCriticalInternalAlert(
                'workflow_failed',
                $event->order,
                [
                    'workflow_id' => $event->workflowId,
                    'failure_reason' => $event->failureReason,
                    'failed_activity' => $event->failedActivity,
                    'compensations_executed' => count($event->compensationsExecuted),
                    'requires_manual_intervention' => $event->requiresManualIntervention(),
                    'severity' => $event->getSeverity(),
                ]
            );

            Log::error('Workflow failed notifications sent', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'failure_reason' => $event->failureReason,
                'failed_activity' => $event->failedActivity,
            ]);
        } catch (\Exception $e) {
            Log::critical('Failed to send workflow failed notifications', [
                'order_id' => $event->order->id,
                'workflow_id' => $event->workflowId,
                'failure_reason' => $event->failureReason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine if an activity represents a major milestone
     */
    private function isMajorMilestone(string $activityName): bool
    {
        $majorMilestones = [
            'ProcessPaymentActivity',
            'ScheduleShippingActivity',
        ];

        foreach ($majorMilestones as $milestone) {
            if (str_contains($activityName, $milestone)) {
                return true;
            }
        }

        return false;
    }
}
