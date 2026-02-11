<?php

namespace App\Events\Workflow;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ManualInterventionRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $workflowId;
    public string $interventionId;
    public string $reason;
    public string $priority;
    public string $requesterId;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $workflowId,
        string $interventionId,
        string $reason,
        string $priority,
        string $requesterId
    ) {
        $this->workflowId = $workflowId;
        $this->interventionId = $interventionId;
        $this->reason = $reason;
        $this->priority = $priority;
        $this->requesterId = $requesterId;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('workflow.' . $this->workflowId),
            new Channel('workflow.interventions'),
        ];

        // Add high-priority channel for urgent interventions
        if (in_array($this->priority, ['high', 'critical'])) {
            $channels[] = new Channel('workflow.interventions.urgent');
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'intervention_id' => $this->interventionId,
            'event_type' => 'manual_intervention_requested',
            'reason' => $this->reason,
            'priority' => $this->priority,
            'requester_id' => $this->requesterId,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'workflow.intervention.requested';
    }
}
