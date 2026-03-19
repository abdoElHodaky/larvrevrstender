<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\DatabaseFailoverEvent;

/**
 * Analytics Service Simple Database Failover Handler
 * 
 * Analytics service uses eventual consistency - complex failover not needed.
 * Simple read-only fallback is sufficient for reporting/analytics data.
 */
class HandleDatabaseFailover implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the database failover event for Analytics Service.
     * Simple approach: Switch to read-only mode and continue with eventual consistency.
     */
    public function handle(DatabaseFailoverEvent $event): void
    {
        Log::info('Analytics Service: Database failover - switching to read-only mode', [
            'service' => 'analytics-service',
            'from_connection' => $event->fromConnection,
            'to_connection' => $event->toConnection,
            'impact' => 'Analytics will use eventual consistency - no critical impact',
            'mode' => 'read-only-fallback',
        ]);

        // Simple fallback: Enable read-only mode for analytics
        cache()->put('analytics_service_mode', 'read-only', 3600);
        cache()->put('analytics_service_eventual_consistency', true, 3600);
        
        Log::info('Analytics Service: Read-only mode enabled - analytics will continue with eventual consistency');
    }
}
