<?php

namespace Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Write Operation Replayed Event
 * 
 * This event is fired when a buffered write operation is replayed after database recovery.
 * Provides information for monitoring replay success rates and performance.
 */
class WriteOperationReplayedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new write operation replayed event instance.
     *
     * @param string $serviceName The service that replayed the operation
     * @param string $operation The operation type that was replayed
     * @param string $bufferId The unique buffer ID for tracking
     * @param string $status The replay status (started, completed, failed)
     * @param string|null $errorMessage Error message if replay failed
     * @param array $context Additional context information
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly string $operation,
        public readonly string $bufferId,
        public readonly string $status,
        public readonly ?string $errorMessage = null,
        public readonly array $context = []
    ) {
        // Add timestamp for precise tracking
        $this->context['timestamp'] = microtime(true);
        $this->context['occurred_at'] = now()->toISOString();
    }

    /**
     * Get the event data for monitoring and alerting.
     *
     * @return array
     */
    public function getMonitoringData(): array
    {
        return [
            'event_type' => 'write_operation_replayed',
            'service' => $this->serviceName,
            'operation' => $this->operation,
            'buffer_id' => $this->bufferId,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
            'timestamp' => $this->context['timestamp'],
            'occurred_at' => $this->context['occurred_at'],
            'context' => $this->context,
        ];
    }

    /**
     * Get the severity level for alerting.
     *
     * @return string
     */
    public function getSeverityLevel(): string
    {
        return match ($this->status) {
            'failed' => 'high',
            'started' => 'low',
            'completed' => 'info',
            default => 'medium',
        };
    }

    /**
     * Check if this event should trigger an alert.
     *
     * @return bool
     */
    public function shouldAlert(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if this is a successful replay.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if this is a failed replay.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the replay duration if available.
     *
     * @return float|null
     */
    public function getReplayDuration(): ?float
    {
        return $this->context['replay_duration'] ?? null;
    }
}
