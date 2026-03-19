<?php

namespace App\Providers;

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
            'App\Listeners\HandleDatabaseFailover',
        ],

        DatabaseFailoverSystemEvent::class => [
            DatabaseFailoverNotificationListener::class . '@handleSystemEvent',
            'App\Listeners\HandleDatabaseFailoverSystem',
        ],

        // Write Operation Events (Analytics is read-only, but listen for monitoring)
        WriteOperationBufferedEvent::class => [
            'App\Listeners\HandleWriteOperationBuffered',
        ],

        WriteOperationReplayedEvent::class => [
            'App\Listeners\HandleWriteOperationReplayed',
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
