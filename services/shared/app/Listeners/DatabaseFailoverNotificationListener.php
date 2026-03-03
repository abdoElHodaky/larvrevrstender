<?php

namespace App\Listeners;

use Shared\Events\DatabaseFailoverEvent;
use Shared\Events\DatabaseFailoverSystemEvent;
use Shared\Services\DatabaseFailoverEmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Shared\Facades\SharedLog;

/**
 * Database Failover Notification Listener
 * 
 * Connects database failover events to the existing comprehensive
 * email notification infrastructure for cross-service event handling.
 */
class DatabaseFailoverNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'database-failover-notifications';
    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        protected DatabaseFailoverEmailNotificationService $notificationService
    ) {}

    /**
     * Handle database failover events.
     */
    public function handle(DatabaseFailoverEvent $event): void
    {
        SharedLog::info('Processing database failover event', [
            'from_connection' => $event->fromConnection,
            'to_connection' => $event->toConnection,
            'severity' => $event->getSeverity(),
            'request_id' => $event->requestId,
        ]);

        // Use existing comprehensive notification service
        $this->notificationService->processFailoverEvent('database_failover', [
            'from_connection' => $event->fromConnection,
            'to_connection' => $event->toConnection,
            'severity' => $event->getSeverity(),
            'duration' => $event->duration,
            'reason' => $event->reason,
            'health_status' => $event->healthStatus,
            'request_id' => $event->requestId,
            'context' => $event->context,
            'impact' => $event->getImpact(),
            'description' => $event->getDescription(),
            'is_failback' => $event->isFailback(),
            'is_critical' => $event->isCriticalFailover(),
        ]);
    }

    /**
     * Handle system-wide database failover events.
     */
    public function handleSystemEvent(DatabaseFailoverSystemEvent $event): void
    {
        SharedLog::warning('Processing system database failover event', [
            'event_type' => $event->eventType,
            'affected_services' => $event->affectedServices,
        ]);

        // Use existing comprehensive notification service
        $this->notificationService->processFailoverEvent('system_failover', [
            'event_type' => $event->eventType,
            'affected_services' => $event->affectedServices,
            'timestamp' => $event->timestamp,
            'system_context' => $event->systemContext,
        ]);
    }
}
