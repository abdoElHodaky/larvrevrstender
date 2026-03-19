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
 * Event fired when a workflow is completed successfully
 */
class WorkflowCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $workflowId;
    public array $finalResult;
    public float $totalExecutionTime;
    public array $completedActivities;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Order $order,
        string $workflowId,
        array $finalResult,
        float $totalExecutionTime,
        array $completedActivities = []
    ) {
        $this->order = $order;
        $this->workflowId = $workflowId;
        $this->finalResult = $finalResult;
        $this->totalExecutionTime = $totalExecutionTime;
        $this->completedActivities = $completedActivities;
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
            new Channel('workflows.completed'),
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
            'status' => 'completed',
            'final_state' => $this->order->state::class,
            'total_execution_time' => $this->totalExecutionTime,
            'activities_completed' => count($this->completedActivities),
            'timestamp' => $this->timestamp,
            'payment_id' => $this->finalResult['payment_id'] ?? null,
            'reservation_id' => $this->finalResult['reservation_id'] ?? null,
            'shipment_id' => $this->finalResult['shipment_id'] ?? null,
            'tracking_number' => $this->finalResult['tracking_number'] ?? null,
            'estimated_delivery' => $this->finalResult['estimated_delivery'] ?? null,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'workflow.completed';
    }
}
