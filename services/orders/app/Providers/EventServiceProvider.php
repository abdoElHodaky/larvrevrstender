<?php

namespace App\Providers;

use App\Events\Workflow\ActivityCompleted;
use App\Events\Workflow\CompensationExecuted;
use App\Events\Workflow\OrderWorkflowInitiated;
use App\Events\Workflow\WorkflowCompleted;
use App\Events\Workflow\WorkflowFailed;
use App\Listeners\Workflow\CollectWorkflowMetrics;
use App\Listeners\Workflow\SendWorkflowNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Shared\Events\DatabaseFailoverEvent;
use Shared\Events\DatabaseFailoverSystemEvent;
use Shared\Events\WriteOperationBufferedEvent;
use Shared\Events\WriteOperationReplayedEvent;
use Shared\Listeners\DatabaseFailoverNotificationListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Database Failover Events (imported from shared)
        DatabaseFailoverEvent::class => [
            DatabaseFailoverNotificationListener::class . '@handle',
        ],

        DatabaseFailoverSystemEvent::class => [
            DatabaseFailoverNotificationListener::class . '@handleSystemEvent',
        ],

        // Write Operation Events (Order service has write operations)
        WriteOperationBufferedEvent::class => [
            'App\Listeners\HandleWriteOperationBuffered',
        ],

        WriteOperationReplayedEvent::class => [
            'App\Listeners\HandleWriteOperationReplayed',
        ],

        // Workflow Events
        OrderWorkflowInitiated::class => [
            SendWorkflowNotifications::class . '@handleWorkflowInitiated',
            CollectWorkflowMetrics::class . '@handleWorkflowInitiated',
        ],

        ActivityCompleted::class => [
            SendWorkflowNotifications::class . '@handleActivityCompleted',
            CollectWorkflowMetrics::class . '@handleActivityCompleted',
        ],

        CompensationExecuted::class => [
            SendWorkflowNotifications::class . '@handleCompensationExecuted',
            CollectWorkflowMetrics::class . '@handleCompensationExecuted',
        ],

        WorkflowCompleted::class => [
            SendWorkflowNotifications::class . '@handleWorkflowCompleted',
            CollectWorkflowMetrics::class . '@handleWorkflowCompleted',
        ],

        WorkflowFailed::class => [
            SendWorkflowNotifications::class . '@handleWorkflowFailed',
            CollectWorkflowMetrics::class . '@handleWorkflowFailed',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
