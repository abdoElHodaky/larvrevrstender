<?php

namespace Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatabaseRecoveryEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $fromConnection;
    public string $toConnection;
    public string $recoveryType;
    public string $timestamp;
    public string $correlationId;
    public array $metadata;

    /**
     * Create a new database recovery event instance.
     */
    public function __construct(
        string $fromConnection,
        string $toConnection,
        string $recoveryType,
        string $correlationId,
        array $metadata = []
    ) {
        $this->fromConnection = $fromConnection;
        $this->toConnection = $toConnection;
        $this->recoveryType = $recoveryType;
        $this->timestamp = now()->toISOString();
        $this->correlationId = $correlationId;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
