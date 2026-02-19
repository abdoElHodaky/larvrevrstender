<?php

namespace Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Middleware\VarnishCacheMiddleware;

class VarnishServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/varnish.php',
            'varnish'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/varnish.php' => config_path('varnish.php'),
            ], 'varnish-config');
        }

        // Register middleware
        $this->app['router']->aliasMiddleware('varnish', VarnishCacheMiddleware::class);

        // Register console commands if needed
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add Varnish-related commands here if needed
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            VarnishCacheMiddleware::class,
        ];
    }
}
