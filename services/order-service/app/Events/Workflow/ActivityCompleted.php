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
 * Event fired when a workflow activity is completed
 */
class ActivityCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $workflowId;
    public string $activityName;
    public array $activityResult;
    public float $executionTime;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Order $order, 
        string $workflowId, 
        string $activityName, 
        array $activityResult, 
        float $executionTime = 0.0
    ) {
        $this->order = $order;
        $this->workflowId = $workflowId;
        $this->activityName = $activityName;
        $this->activityResult = $activityResult;
        $this->executionTime = $executionTime;
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
            new Channel('workflows.activities'),
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
            'activity_name' => $this->activityName,
            'activity_type' => $this->getActivityType(),
            'success' => $this->activityResult['success'] ?? false,
            'execution_time' => $this->executionTime,
            'timestamp' => $this->timestamp,
            'progress' => $this->calculateProgress(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'activity.completed';
    }

    /**
     * Get the activity type based on class name
     */
    private function getActivityType(): string
    {
        if (str_contains($this->activityName, 'Payment')) {
            return 'payment';
        } elseif (str_contains($this->activityName, 'Inventory')) {
            return 'inventory';
        } elseif (str_contains($this->activityName, 'Shipping')) {
            return 'shipping';
        } else {
            return 'unknown';
        }
    }

    /**
     * Calculate workflow progress based on completed activity
     */
    private function calculateProgress(): int
    {
        $activityProgress = [
            'ProcessPaymentActivity' => 33,
            'ReserveInventoryActivity' => 66,
            'ScheduleShippingActivity' => 100,
        ];

        foreach ($activityProgress as $activity => $progress) {
            if (str_contains($this->activityName, $activity)) {
                return $progress;
            }
        }

        return 0;
    }
}
