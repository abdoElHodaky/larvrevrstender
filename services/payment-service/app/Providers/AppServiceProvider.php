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
        // Register service-specific bindings
        $this->app->singleton('payment_service.service', function ($app) {
            return new \App\Services\PaymentServiceService();
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
        // Add custom validation rules here
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        // Add event listeners here
    }
}
