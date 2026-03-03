<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Shared\Events\DatabaseFailoverEvent;
use Shared\Events\DatabaseFailoverSystemEvent;
use App\Listeners\DatabaseFailoverNotificationListener;

/**
 * Event Service Provider for Shared Service
 * 
 * Registers event listeners for database failover events to enable
 * cross-service notification and monitoring capabilities.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        DatabaseFailoverEvent::class => [
            DatabaseFailoverNotificationListener::class . '@handle',
        ],
        DatabaseFailoverSystemEvent::class => [
            DatabaseFailoverNotificationListener::class . '@handleSystemEvent',
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
