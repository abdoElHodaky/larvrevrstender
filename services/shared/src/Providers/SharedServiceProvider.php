<?php

namespace Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Core\ProcedureEngine;
use Shared\Core\RestHandler;
use Shared\Core\RpcHandler;
use Shared\Contracts\ModelResolverInterface;
use Shared\Services\ModelResolver;

/**
 * Shared Service Provider
 * 
 * Registers shared service components and bindings for cross-service infrastructure
 */
class SharedServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register core components as singletons
        $this->app->singleton(ProcedureEngine::class, function ($app) {
            return new ProcedureEngine();
        });

        $this->app->singleton(RestHandler::class, function ($app) {
            return new RestHandler();
        });

        $this->app->singleton(RpcHandler::class, function ($app) {
            return new RpcHandler();
        });

        // Register Model Resolver for Eloquent ORM integration
        $this->app->singleton(ModelResolverInterface::class, function ($app) {
            return new ModelResolver();
        });

        // Register aliases for easier access
        $this->app->alias(ProcedureEngine::class, 'shared.procedure-engine');
        $this->app->alias(RestHandler::class, 'shared.rest-handler');
        $this->app->alias(RpcHandler::class, 'shared.rpc-handler');
        $this->app->alias(ModelResolverInterface::class, 'shared.model-resolver');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load configuration files
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/shared.php', 'shared'
        );

        // Load routes if they exist
        if (file_exists(__DIR__ . '/../../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        }

        // Publish configuration if running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/shared.php' => config_path('shared.php'),
            ], 'shared-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ProcedureEngine::class,
            RestHandler::class,
            RpcHandler::class,
            ModelResolverInterface::class,
            'shared.procedure-engine',
            'shared.rest-handler',
            'shared.rpc-handler',
            'shared.model-resolver',
        ];
    }
}
