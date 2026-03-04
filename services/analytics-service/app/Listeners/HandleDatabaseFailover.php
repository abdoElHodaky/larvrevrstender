<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\DatabaseFailoverEvent;

class HandleDatabaseFailover implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the database failover event for Analytics Service.
     */
    public function handle(DatabaseFailoverEvent $event): void
    {
        Log::channel('database-failover')->info('Analytics Service: Database failover detected', [
            'service' => 'analytics-service',
            'from_connection' => $event->fromConnection,
            'to_connection' => $event->toConnection,
            'reason' => $event->reason,
            'timestamp' => $event->timestamp,
            'correlation_id' => $event->correlationId,
        ]);

        // Analytics-specific failover handling
        $this->handleAnalyticsFailover($event);
    }

    /**
     * Handle analytics-specific failover logic.
     */
    private function handleAnalyticsFailover(DatabaseFailoverEvent $event): void
    {
        // Analytics service is read-only, so we can gracefully degrade
        if ($event->toConnection === 'mongodb') {
            Log::info('Analytics Service: Switching to MongoDB read-only mode', [
                'service' => 'analytics-service',
                'mode' => 'read-only',
                'fallback_connection' => 'mongodb',
            ]);

            // Update service status to indicate read-only mode
            cache()->put('analytics_service_mode', 'read-only', 3600);
            
            // Notify monitoring systems
            $this->notifyMonitoringSystems($event);
        }
    }

    /**
     * Notify external monitoring systems.
     */
    private function notifyMonitoringSystems(DatabaseFailoverEvent $event): void
    {
        // Send metrics to monitoring dashboard
        // This would integrate with your monitoring stack (Prometheus, DataDog, etc.)
        Log::info('Analytics Service: Notifying monitoring systems of failover', [
            'service' => 'analytics-service',
            'event_type' => 'database_failover',
            'severity' => 'warning',
        ]);
    }
}
