<?php

namespace Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Core\ProcedureEngine;
use Shared\Core\RestHandler;
use Shared\Core\RpcHandler;
use Shared\Services\DatabaseFailoverManager;
use Shared\Services\SharedLoggingService;
use Shared\Services\DatabaseTopologyMapper;
use Shared\Services\CircuitBreakerParameterTuner;
use Shared\Services\DatabaseFailoverEmailNotificationService;
use Shared\Services\DatabaseFailoverOrchestrator;
use Shared\Services\QueryExecutionService;
use Shared\Services\DatabaseConsistencyValidator;
use Shared\Services\DatabaseFailoverRecoveryManager;
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

        // Register shared logging service
        $this->app->singleton(SharedLoggingService::class, function ($app) {
            return new SharedLoggingService();
        });

        // Register database failover services
        $this->app->singleton(DatabaseHealthChecker::class, function ($app) {
            return new DatabaseHealthChecker();
        });

        $this->app->singleton(DatabaseFailoverManager::class, function ($app) {
            return new DatabaseFailoverManager();
        });

        $this->app->singleton(DatabaseConsistencyValidator::class, function ($app) {
            return new DatabaseConsistencyValidator();
        });

        $this->app->singleton(DatabaseFailoverRecoveryManager::class, function ($app) {
            return new DatabaseFailoverRecoveryManager();
        });

        // Register new database failover integration services
        $this->app->singleton(DatabaseTopologyMapper::class, function ($app) {
            return new DatabaseTopologyMapper();
        });

        $this->app->singleton(CircuitBreakerParameterTuner::class, function ($app) {
            return new CircuitBreakerParameterTuner(
                $app->make(DatabaseTopologyMapper::class)
            );
        });

        $this->app->singleton(DatabaseFailoverEmailNotificationService::class, function ($app) {
            return new DatabaseFailoverEmailNotificationService();
        });

        $this->app->singleton(QueryExecutionService::class, function ($app) {
            return new QueryExecutionService('shared-service');
        });

        $this->app->singleton(DatabaseFailoverOrchestrator::class, function ($app) {
            return new DatabaseFailoverOrchestrator(
                $app->make(DatabaseTopologyMapper::class),
                $app->make(CircuitBreakerParameterTuner::class),
                $app->make(DatabaseFailoverEmailNotificationService::class)
            );
        });

        // Bind interface to implementation
        $this->app->bind(DatabaseFailoverInterface::class, DatabaseFailoverManager::class);

        // Register aliases for easier access
        $this->app->alias(ProcedureEngine::class, 'shared.procedure-engine');
        $this->app->alias(RestHandler::class, 'shared.rest-handler');
        $this->app->alias(RpcHandler::class, 'shared.rpc-handler');
        $this->app->alias(SharedLoggingService::class, 'shared.logging');
        $this->app->alias(DatabaseFailoverManager::class, 'shared.database-failover');
        $this->app->alias(DatabaseHealthChecker::class, 'shared.database-health-checker');
        
        // Register aliases for new database failover services
        $this->app->alias(DatabaseTopologyMapper::class, 'shared.topology-mapper');
        $this->app->alias(CircuitBreakerParameterTuner::class, 'shared.circuit-breaker-tuner');
        $this->app->alias(DatabaseFailoverEmailNotificationService::class, 'shared.email-notifications');
        $this->app->alias(QueryExecutionService::class, 'shared.query-execution');
        $this->app->alias(DatabaseFailoverOrchestrator::class, 'shared.failover-orchestrator');
        $this->app->alias(DatabaseConsistencyValidator::class, 'shared.consistency-validator');
        $this->app->alias(DatabaseFailoverRecoveryManager::class, 'shared.recovery-manager');
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

        // Load shared logging configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/logging.php', 'shared-logging'
        );

        // Load Fuse circuit breaker configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/fuse.php', 'fuse'
        );

        // Load routes if they exist
        if (file_exists(__DIR__ . '/../../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        }

        // Publish configuration if running in console
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/shared.php' => config_path('shared.php'),
                __DIR__ . '/../../config/database-failover.php' => config_path('database-failover.php'),
                __DIR__ . '/../../config/fuse.php' => config_path('fuse.php'),
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
            SharedLoggingService::class,
            DatabaseFailoverManager::class,
            DatabaseHealthChecker::class,
            DatabaseTopologyMapper::class,
            CircuitBreakerParameterTuner::class,
            DatabaseFailoverEmailNotificationService::class,
            QueryExecutionService::class,
            DatabaseFailoverOrchestrator::class,
            DatabaseConsistencyValidator::class,
            DatabaseFailoverRecoveryManager::class,
            DatabaseFailoverInterface::class,
            'shared.procedure-engine',
            'shared.rest-handler',
            'shared.rpc-handler',
            'shared.logging',
            'shared.database-failover',
            'shared.database-health-checker',
            'shared.topology-mapper',
            'shared.circuit-breaker-tuner',
            'shared.email-notifications',
            'shared.query-execution',
            'shared.failover-orchestrator',
            'shared.consistency-validator',
            'shared.recovery-manager',
        ];
    }
}
