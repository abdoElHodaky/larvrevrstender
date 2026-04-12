<?php

namespace App\Events\Workflow;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an order workflow is initiated
 */
class OrderWorkflowInitiated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $workflowId;
    public array $workflowData;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, string $workflowId, array $workflowData)
    {
        $this->order = $order;
        $this->workflowId = $workflowId;
        $this->workflowData = $workflowData;
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
            new Channel('workflows.initiated'),
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
            'customer_id' => $this->order->customer_id,
            'status' => 'initiated',
            'timestamp' => $this->timestamp,
            'order_title' => $this->order->title,
            'total_amount' => $this->order->total_amount,
            'currency' => $this->order->currency ?? 'SAR',
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'workflow.initiated';
    }
}
