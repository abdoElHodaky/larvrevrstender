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

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
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
