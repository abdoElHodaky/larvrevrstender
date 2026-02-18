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
        $this->app->singleton('analytics_service.service', function ($app) {
            return new \App\Services\AnalyticsServiceService;
        });

        // Register FileUploadService for analytics-service
        $this->app->singleton(FileUploadService::class, function ($app) {
            return new FileUploadService('analytics-service');
        });

        // Register RPC client compatibility bindings
        $this->registerRpcClientBindings();
    }

    /**
     * Register RPC client bindings to replace HTTP clients
     */
    private function registerRpcClientBindings(): void
    {
        // Replace AuthServiceClient with RPC-based implementation
        $this->app->bind(\App\Http\Clients\AuthServiceClient::class, function ($app) {
            return new \App\Http\Clients\RpcAuthServiceClient();
        });

        // Replace DataCollectionClient with RPC-based implementation  
        $this->app->bind(\App\Http\Clients\DataCollectionClient::class, function ($app) {
            return new \App\Http\Clients\RpcDataCollectionClient();
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
