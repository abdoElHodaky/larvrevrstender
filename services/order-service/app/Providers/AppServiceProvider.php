<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Shared\Services\FileUploadService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register service-specific bindings
        $this->app->singleton('order_service.service', function ($app) {
            return new \App\Services\OrderServiceService;
        });

        // Register FileUploadService for order-service
        $this->app->singleton(FileUploadService::class, function ($app) {
            return new FileUploadService('order-service');
        });

        // Register workflow services
        $this->app->singleton(\App\Services\WorkflowSignalHandler::class);
        $this->app->singleton(\App\Services\WorkflowDeadLetterQueue::class);
        $this->app->singleton(\App\Services\CorrelationService::class);
        $this->app->singleton(\App\Services\WorkflowEventPublisher::class);
        $this->app->singleton(\App\Services\WorkflowTracingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for MySQL
        Schema::defaultStringLength(191);

        // Remove data wrapping from JSON resources
        JsonResource::withoutWrapping();

        // Register custom validation rules
        $this->registerCustomValidationRules();

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Register custom validation rules.
     */
    private function registerCustomValidationRules(): void
    {
        // Add custom validation rules here
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        // Register cross-service event listeners
        \Event::listen(
            \App\Events\OrderCreated::class,
            \App\Listeners\OrderCreatedListener::class
        );

        // Listen for PaymentCompleted events from PaymentService
        // This will be triggered via cross-service event publishing
        \Event::listen(
            'payment.completed',
            \App\Listeners\PaymentCompletedListener::class
        );

        // Listen for PaymentFailed events from PaymentService
        \Event::listen(
            'payment.failed',
            function ($event) {
                \Log::info('PaymentFailed event received', [
                    'payment_id' => $event->payment->id ?? null,
                    'order_id' => $event->payment->order_id ?? null,
                    'reason' => $event->reason ?? 'Unknown'
                ]);
                
                // TODO: Handle payment failure - potentially cancel order or retry
            }
        );
    }
}
