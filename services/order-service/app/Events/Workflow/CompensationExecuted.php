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
 * Event fired when a compensation activity is executed
 */
class CompensationExecuted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $workflowId;
    public string $compensationActivity;
    public array $compensationResult;
    public string $originalActivity;
    public string $failureReason;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Order $order,
        string $workflowId,
        string $compensationActivity,
        array $compensationResult,
        string $originalActivity,
        string $failureReason
    ) {
        $this->order = $order;
        $this->workflowId = $workflowId;
        $this->compensationActivity = $compensationActivity;
        $this->compensationResult = $compensationResult;
        $this->originalActivity = $originalActivity;
        $this->failureReason = $failureReason;
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
            new Channel('workflows.compensations'),
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
            'compensation_activity' => $this->compensationActivity,
            'compensation_type' => $this->getCompensationType(),
            'original_activity' => $this->originalActivity,
            'failure_reason' => $this->failureReason,
            'success' => $this->compensationResult['success'] ?? false,
            'timestamp' => $this->timestamp,
            'severity' => $this->getSeverity(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'compensation.executed';
    }

    /**
     * Get the compensation type based on activity name
     */
    private function getCompensationType(): string
    {
        if (str_contains($this->compensationActivity, 'Refund')) {
            return 'refund';
        } elseif (str_contains($this->compensationActivity, 'Release')) {
            return 'inventory_release';
        } elseif (str_contains($this->compensationActivity, 'Cancel')) {
            return 'shipping_cancel';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get severity level based on compensation type
     */
    private function getSeverity(): string
    {
        $compensationType = $this->getCompensationType();
        
        return match ($compensationType) {
            'refund' => 'high',
            'inventory_release' => 'medium',
            'shipping_cancel' => 'low',
            default => 'medium'
        };
    }
}
