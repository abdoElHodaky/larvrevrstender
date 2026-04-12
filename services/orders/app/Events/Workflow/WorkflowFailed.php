<?php

namespace App\Events\Workflow;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a workflow fails
 */
class WorkflowFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $workflowId;
    public string $failureReason;
    public string $failedActivity;
    public array $errorDetails;
    public array $compensationsExecuted;
    public float $executionTimeBeforeFailure;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Order $order,
        string $workflowId,
        string $failureReason,
        string $failedActivity,
        array $errorDetails = [],
        array $compensationsExecuted = [],
        float $executionTimeBeforeFailure = 0.0
    ) {
        $this->order = $order;
        $this->workflowId = $workflowId;
        $this->failureReason = $failureReason;
        $this->failedActivity = $failedActivity;
        $this->errorDetails = $errorDetails;
        $this->compensationsExecuted = $compensationsExecuted;
        $this->executionTimeBeforeFailure = $executionTimeBeforeFailure;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order.' . $this->order->id),
            new PrivateChannel('customer.' . $this->order->customer_id),
            new Channel('workflows.failed'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'workflow_id' => $this->workflowId,
            'status' => 'failed',
            'failure_reason' => $this->failureReason,
            'failed_activity' => $this->failedActivity,
            'failed_activity_type' => $this->getFailedActivityType(),
            'compensations_executed' => count($this->compensationsExecuted),
            'execution_time_before_failure' => $this->executionTimeBeforeFailure,
            'timestamp' => $this->timestamp,
            'severity' => $this->getSeverity(),
            'requires_manual_intervention' => $this->requiresManualIntervention(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'workflow.failed';
    }

    /**
     * Get the failed activity type
     */
    private function getFailedActivityType(): string
    {
        if (str_contains($this->failedActivity, 'Payment')) {
            return 'payment';
        } elseif (str_contains($this->failedActivity, 'Inventory')) {
            return 'inventory';
        } elseif (str_contains($this->failedActivity, 'Shipping')) {
            return 'shipping';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get severity level based on failed activity
     */
    private function getSeverity(): string
    {
        $activityType = $this->getFailedActivityType();
        
        return match ($activityType) {
            'payment' => 'critical',
            'inventory' => 'high',
            'shipping' => 'medium',
            default => 'high'
        };
    }

    /**
     * Determine if manual intervention is required
     */
    private function requiresManualIntervention(): bool
    {
        // Payment failures typically require manual intervention
        if ($this->getFailedActivityType() === 'payment') {
            return true;
        }

        // If compensations failed, manual intervention is needed
        foreach ($this->compensationsExecuted as $compensation) {
            if (!($compensation['success'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
