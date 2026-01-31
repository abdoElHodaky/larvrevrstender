<?php

namespace App\Providers;

use App\Events\UserProfileUpdated;
use App\Events\KYCVerificationSubmitted;
use App\Events\KYCVerificationCompleted;
use App\Listeners\BroadcastUserProfileUpdated;
use App\Listeners\BroadcastKYCVerificationSubmitted;
use App\Listeners\BroadcastKYCVerificationCompleted;
use App\Listeners\HandleUserRegisteredFromAuth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        
        // User Profile Events
        UserProfileUpdated::class => [
            BroadcastUserProfileUpdated::class,
        ],
        
        // KYC Verification Events
        KYCVerificationSubmitted::class => [
            BroadcastKYCVerificationSubmitted::class,
        ],
        
        KYCVerificationCompleted::class => [
            BroadcastKYCVerificationCompleted::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Listen for external events from other services
        Event::listen('external.user.registered', HandleUserRegisteredFromAuth::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

