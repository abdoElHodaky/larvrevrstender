<?php

namespace Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Database Failover Event
 * 
 * This event is fired whenever a database failover occurs, providing
 * comprehensive information for monitoring and debugging purposes.
 * Integrates with Laravel Telescope for detailed tracking.
 */
class DatabaseFailoverEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new database failover event instance.
     *
     * @param string $fromConnection The connection that failed
     * @param string $toConnection The connection that was switched to
     * @param string $reason The reason for the failover
     * @param float $duration The time taken for the failover in seconds
     * @param array $healthStatus The health status of all connections
     * @param string|null $requestId The request ID that triggered the failover
     * @param array $context Additional context information
     */
    public function __construct(
        public readonly string $fromConnection,
        public readonly string $toConnection,
        public readonly string $reason,
        public readonly float $duration,
        public readonly array $healthStatus,
        public readonly ?string $requestId = null,
        public readonly array $context = []
    ) {
        // Add timestamp for precise tracking
        $this->context['timestamp'] = microtime(true);
        $this->context['occurred_at'] = now()->toISOString();
    }

    /**
     * Get the event data for Telescope tracking.
     *
     * @return array
     */
    public function getTelescopeData(): array
    {
        return [
            'from_connection' => $this->fromConnection,
            'to_connection' => $this->toConnection,
            'reason' => $this->reason,
            'duration_ms' => round($this->duration * 1000, 2),
            'duration_seconds' => $this->duration,
            'health_status' => $this->healthStatus,
            'request_id' => $this->requestId,
            'context' => $this->context,
            'severity' => $this->getSeverity(),
            'impact' => $this->getImpact(),
        ];
    }

    /**
     * Determine the severity of the failover event.
     *
     * @return string
     */
    public function getSeverity(): string
    {
        // Determine severity based on the target connection
        return match ($this->toConnection) {
            'pgsql' => 'info',           // Failback to primary
            'pgsql_secondary' => 'warning', // Failover to secondary
            'mongodb' => 'critical',     // Failover to fallback
            default => 'unknown'
        };
    }

    /**
     * Determine the impact level of the failover.
     *
     * @return string
     */
    public function getImpact(): string
    {
        // Determine impact based on duration and target
        if ($this->duration > 5.0) {
            return 'high';
        } elseif ($this->duration > 1.0) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get a human-readable description of the failover event.
     *
     * @return string
     */
    public function getDescription(): string
    {
        $duration = round($this->duration * 1000, 2);
        return "Database failover from {$this->fromConnection} to {$this->toConnection} " .
               "completed in {$duration}ms. Reason: {$this->reason}";
    }

    /**
     * Check if this is a failback event (returning to primary).
     *
     * @return bool
     */
    public function isFailback(): bool
    {
        return $this->toConnection === 'pgsql' && $this->fromConnection !== 'pgsql';
    }

    /**
     * Check if this is a critical failover (to MongoDB).
     *
     * @return bool
     */
    public function isCriticalFailover(): bool
    {
        return $this->toConnection === 'mongodb';
    }

    /**
     * Get metrics for monitoring systems.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        return [
            'failover_duration_ms' => round($this->duration * 1000, 2),
            'failover_count' => 1,
            'severity_level' => $this->getSeverityLevel(),
            'connection_tier' => $this->getConnectionTier(),
            'is_failback' => $this->isFailback() ? 1 : 0,
            'is_critical' => $this->isCriticalFailover() ? 1 : 0,
        ];
    }

    /**
     * Get numeric severity level for metrics.
     *
     * @return int
     */
    private function getSeverityLevel(): int
    {
        return match ($this->getSeverity()) {
            'info' => 1,
            'warning' => 2,
            'critical' => 3,
            default => 0
        };
    }

    /**
     * Get the connection tier number.
     *
     * @return int
     */
    private function getConnectionTier(): int
    {
        return match ($this->toConnection) {
            'pgsql' => 1,
            'pgsql_secondary' => 2,
            'mongodb' => 3,
            default => 0
        };
    }
}
