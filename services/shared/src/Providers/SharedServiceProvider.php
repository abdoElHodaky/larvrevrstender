<?php

namespace Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Core\ProcedureEngine;
use Shared\Core\RestHandler;
use Shared\Core\RpcHandler;
use Shared\Services\DatabaseFailoverManager;
use Shared\HealthCheck\DatabaseHealthChecker;
use Shared\Contracts\DatabaseFailoverInterface;

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

        // Register database failover services
        $this->app->singleton(DatabaseHealthChecker::class, function ($app) {
            return new DatabaseHealthChecker();
        });

        $this->app->singleton(DatabaseFailoverManager::class, function ($app) {
            return new DatabaseFailoverManager();
        });

        // Bind interface to implementation
        $this->app->bind(DatabaseFailoverInterface::class, DatabaseFailoverManager::class);

        // Register aliases for easier access
        $this->app->alias(ProcedureEngine::class, 'shared.procedure-engine');
        $this->app->alias(RestHandler::class, 'shared.rest-handler');
        $this->app->alias(RpcHandler::class, 'shared.rpc-handler');
        $this->app->alias(DatabaseFailoverManager::class, 'shared.database-failover');
        $this->app->alias(DatabaseHealthChecker::class, 'shared.database-health-checker');
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

        // Load database failover configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/database-failover.php', 'database-failover'
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
            DatabaseFailoverManager::class,
            DatabaseHealthChecker::class,
            DatabaseFailoverInterface::class,
            'shared.procedure-engine',
            'shared.rest-handler',
            'shared.rpc-handler',
            'shared.database-failover',
            'shared.database-health-checker',
        ];
    }
}
