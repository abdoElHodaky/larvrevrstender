<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Resources\Json\JsonResource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register notification service bindings
        $this->app->singleton('notification.service', function ($app) {
            return new \App\Services\NotificationService();
        });

        // Register email service
        $this->app->singleton('email.service', function ($app) {
            return new \App\Services\EmailService();
        });

        // Register SMS service
        $this->app->singleton('sms.service', function ($app) {
            return new \App\Services\SMSService();
        });

        // Register push notification service
        $this->app->singleton('push.service', function ($app) {
            return new \App\Services\PushNotificationService();
        });
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
        // Add notification-specific validation rules here
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        // Register notification event listeners here
    }
}
