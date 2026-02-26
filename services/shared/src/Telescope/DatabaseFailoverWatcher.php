<?php

namespace Shared\Telescope;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\Watcher;
use Shared\Events\DatabaseFailoverEvent;

/**
 * Database Failover Watcher for Laravel Telescope
 * 
 * This watcher monitors database failover events and records them
 * in Telescope for comprehensive monitoring and debugging.
 */
class DatabaseFailoverWatcher extends Watcher
{
    /**
     * Register the watcher.
     *
     * @param Application $app
     * @return void
     */
    public function register($app): void
    {
        $app['events']->listen(DatabaseFailoverEvent::class, [$this, 'recordFailover']);
    }

    /**
     * Record a database failover event.
     *
     * @param DatabaseFailoverEvent $event
     * @return void
     */
    public function recordFailover(DatabaseFailoverEvent $event): void
    {
        if (!$this->shouldRecord($event)) {
            return;
        }

        Telescope::recordFailover(
            $this->createIncomingEntry($event)
        );
    }

    /**
     * Create an incoming entry for the failover event.
     *
     * @param DatabaseFailoverEvent $event
     * @return IncomingEntry
     */
    private function createIncomingEntry(DatabaseFailoverEvent $event): IncomingEntry
    {
        return IncomingEntry::make([
            'type' => 'database_failover',
            'family_hash' => $this->generateFamilyHash($event),
            'content' => [
                'from_connection' => $event->fromConnection,
                'to_connection' => $event->toConnection,
                'reason' => $event->reason,
                'duration_ms' => round($event->duration * 1000, 2),
                'duration_seconds' => $event->duration,
                'severity' => $event->getSeverity(),
                'impact' => $event->getImpact(),
                'description' => $event->getDescription(),
                'is_failback' => $event->isFailback(),
                'is_critical' => $event->isCriticalFailover(),
                'request_id' => $event->requestId,
                'health_status' => $this->sanitizeHealthStatus($event->healthStatus),
                'context' => $event->context,
                'metrics' => $event->getMetrics(),
            ],
            'tags' => $this->generateTags($event),
        ])->tags($this->generateTags($event));
    }

    /**
     * Generate a family hash for grouping related failover events.
     *
     * @param DatabaseFailoverEvent $event
     * @return string
     */
    private function generateFamilyHash(DatabaseFailoverEvent $event): string
    {
        // Group by connection transition and reason
        return md5($event->fromConnection . '->' . $event->toConnection . ':' . $event->reason);
    }

    /**
     * Generate tags for the failover event.
     *
     * @param DatabaseFailoverEvent $event
     * @return array
     */
    private function generateTags(DatabaseFailoverEvent $event): array
    {
        $tags = [
            'database_failover',
            'from:' . $event->fromConnection,
            'to:' . $event->toConnection,
            'severity:' . $event->getSeverity(),
            'impact:' . $event->getImpact(),
        ];

        // Add specific tags based on event characteristics
        if ($event->isFailback()) {
            $tags[] = 'failback';
        }

        if ($event->isCriticalFailover()) {
            $tags[] = 'critical';
        }

        // Add duration-based tags
        if ($event->duration > 5.0) {
            $tags[] = 'slow';
        } elseif ($event->duration < 0.1) {
            $tags[] = 'fast';
        }

        // Add request ID if available
        if ($event->requestId) {
            $tags[] = 'request:' . $event->requestId;
        }

        return $tags;
    }

    /**
     * Sanitize health status data for storage.
     *
     * @param array $healthStatus
     * @return array
     */
    private function sanitizeHealthStatus(array $healthStatus): array
    {
        $sanitized = [];

        foreach ($healthStatus as $connection => $status) {
            if (is_array($status)) {
                // Keep only essential health information
                $sanitized[$connection] = [
                    'healthy' => $status['healthy'] ?? false,
                    'response_time_ms' => $status['response_time_ms'] ?? null,
                    'last_error' => $status['last_error'] ?? null,
                    'checked_at' => $status['checked_at'] ?? null,
                ];

                // Add specific metrics if available
                if (isset($status['metrics'])) {
                    $sanitized[$connection]['key_metrics'] = [
                        'connection_count' => $status['metrics']['connection_count'] ?? null,
                        'query_time_avg' => $status['metrics']['query_time_avg'] ?? null,
                        'replication_lag' => $status['metrics']['replication_lag'] ?? null,
                    ];
                }
            } else {
                $sanitized[$connection] = $status;
            }
        }

        return $sanitized;
    }

    /**
     * Determine if the failover event should be recorded.
     *
     * @param DatabaseFailoverEvent $event
     * @return bool
     */
    private function shouldRecord(DatabaseFailoverEvent $event): bool
    {
        // Always record failover events as they are critical
        return true;
    }

    /**
     * Get the watcher's configuration options.
     *
     * @return array
     */
    public static function getDefaultOptions(): array
    {
        return [
            'enabled' => true,
            'record_health_status' => true,
            'record_context' => true,
            'max_health_status_size' => 1024, // bytes
        ];
    }
}

// Extend Telescope to support failover entries
if (!method_exists(Telescope::class, 'recordFailover')) {
    /**
     * Add failover recording capability to Telescope.
     */
    Telescope::macro('recordFailover', function (IncomingEntry $entry) {
        return Telescope::record($entry);
    });
}
