<?php

namespace Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Write Operation Buffered Event
 * 
 * This event is fired when a write operation is buffered during database failover.
 * Provides information for monitoring and alerting on write operation buffering.
 */
class WriteOperationBufferedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new write operation buffered event instance.
     *
     * @param string $serviceName The service that buffered the operation
     * @param string $operation The operation type that was buffered
     * @param string $bufferId The unique buffer ID for tracking
     * @param string $priority The priority level of the operation
     * @param int $queueSize Current size of the buffer queue
     * @param array $context Additional context information
     */
    public function __construct(
        public readonly string $serviceName,
        public readonly string $operation,
        public readonly string $bufferId,
        public readonly string $priority,
        public readonly int $queueSize,
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
            'event_type' => 'write_operation_buffered',
            'service' => $this->serviceName,
            'operation' => $this->operation,
            'buffer_id' => $this->bufferId,
            'priority' => $this->priority,
            'queue_size' => $this->queueSize,
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
        // Determine severity based on priority and queue size
        if ($this->priority === 'critical') {
            return 'high';
        }
        
        if ($this->queueSize > 5000) {
            return 'high';
        }
        
        if ($this->queueSize > 1000) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Check if this event should trigger an alert.
     *
     * @return bool
     */
    public function shouldAlert(): bool
    {
        return $this->priority === 'critical' || $this->queueSize > 1000;
    }
}
