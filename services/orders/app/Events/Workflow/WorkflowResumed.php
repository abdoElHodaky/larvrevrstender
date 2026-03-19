<?php

namespace App\Events\Workflow;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowResumed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $workflowId;
    public string $userId;
    public array $result;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(string $workflowId, string $userId, array $result)
    {
        $this->workflowId = $workflowId;
        $this->userId = $userId;
        $this->result = $result;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workflow.' . $this->workflowId),
            new Channel('workflow.status'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'event_type' => 'workflow_resumed',
            'user_id' => $this->userId,
            'result' => $this->result,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'workflow.resumed';
    }
}
