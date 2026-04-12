<?php

namespace Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Shared\Contracts\DatabaseFailoverInterface;
use Shared\Events\DatabaseFailoverEvent;
use Carbon\Carbon;

/**
 * Database Failover Manager
 * 
 * Manages database failover across the 3-tier architecture:
 * - Primary: Neon PostgreSQL
 * - Secondary: Cloud Provider PostgreSQL  
 * - Fallback: MongoDB Atlas
 */
class DatabaseFailoverManager implements DatabaseFailoverInterface
{
    private array $connections;
    private array $connectionHealth;
    private array $failoverMetrics;
    private array $eventListeners;
    private string $currentConnection;
    private array $config;

    public function __construct()
    {
        $this->config = config('database-failover', []);
        $this->connections = $this->config['connections'] ?? [
            'primary' => 'neon_postgresql',
            'secondary' => 'cloud_postgresql',
            'fallback' => 'mongodb_atlas'
        ];
        
        $this->connectionHealth = [];
        $this->failoverMetrics = [
            'total_failovers' => 0,
            'last_failover' => null,
            'recovery_count' => 0,
            'uptime_start' => now(),
        ];
        $this->eventListeners = [];
        $this->currentConnection = $this->connections['primary'];
        
        $this->initializeHealthStatus();
    }

    /**
     * Get the currently healthy database connection name.
     */
    public function getHealthyConnection(): string
    {
        // Check if current connection is still healthy
        if ($this->isConnectionHealthy($this->currentConnection)) {
            return $this->currentConnection;
        }

        // Try to find a healthy connection in priority order
        foreach ($this->connections as $priority => $connectionName) {
            if ($this->isConnectionHealthy($connectionName)) {
                if ($connectionName !== $this->currentConnection) {
                    $this->triggerFailover($this->currentConnection);
                }
                return $connectionName;
            }
        }

        // If no healthy connections found, throw exception
        $this->fireEvent('all_connections_failed', [
            'timestamp' => now(),
            'attempted_connections' => $this->connections
        ]);

        throw new \RuntimeException('All database connections are unhealthy');
    }

    /**
     * Check if a specific database connection is healthy.
     */
    public function isConnectionHealthy(string $connectionName): bool
    {
        $cacheKey = "db_health_{$connectionName}";
        $healthCheckInterval = $this->config['health_check']['interval'] ?? 30;

        // Check cache first to avoid excessive health checks
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $isHealthy = $this->performHealthCheck($connectionName);
        
        // Cache the result
        Cache::put($cacheKey, $isHealthy, $healthCheckInterval);
        
        // Update internal health status
        $this->connectionHealth[$connectionName] = [
            'healthy' => $isHealthy,
            'last_check' => now(),
            'consecutive_failures' => $isHealthy ? 0 : ($this->connectionHealth[$connectionName]['consecutive_failures'] ?? 0) + 1,
            'consecutive_successes' => $isHealthy ? ($this->connectionHealth[$connectionName]['consecutive_successes'] ?? 0) + 1 : 0,
        ];

        return $isHealthy;
    }

    /**
     * Perform actual health check on a database connection.
     */
    private function performHealthCheck(string $connectionName): bool
    {
        try {
            $timeout = $this->config['health_check']['timeout'] ?? 5;
            $maxAttempts = $this->config['health_check']['retry_attempts'] ?? 3;
            $retryDelay = $this->config['health_check']['retry_delay'] ?? 1000;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    // Test database connection
                    $pdo = DB::connection($connectionName)->getPdo();
                    
                    // Perform a simple query to verify functionality
                    if ($this->isDatabasePostgreSQL($connectionName)) {
                        DB::connection($connectionName)->select('SELECT 1 as health_check');
                    } elseif ($this->isDatabaseMongoDB($connectionName)) {
                        // For MongoDB, we'll use a different approach
                        $this->performMongoHealthCheck($connectionName);
                    }

                    // If we reach here, the connection is healthy
                    if ($attempt > 1) {
                        Log::info("Database connection {$connectionName} recovered after {$attempt} attempts");
                    }
                    
                    return true;

                } catch (\Exception $e) {
                    Log::warning("Database health check attempt {$attempt}/{$maxAttempts} failed for {$connectionName}: " . $e->getMessage());
                    
                    if ($attempt < $maxAttempts) {
                        usleep($retryDelay * 1000); // Convert to microseconds
                    }
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Database health check failed for {$connectionName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the health status of all configured database connections.
     */
    public function getAllConnectionsHealth(): array
    {
        $healthStatus = [];
        
        foreach ($this->connections as $priority => $connectionName) {
            $healthStatus[$connectionName] = [
                'priority' => $priority,
                'healthy' => $this->isConnectionHealthy($connectionName),
                'last_check' => $this->connectionHealth[$connectionName]['last_check'] ?? null,
                'consecutive_failures' => $this->connectionHealth[$connectionName]['consecutive_failures'] ?? 0,
                'consecutive_successes' => $this->connectionHealth[$connectionName]['consecutive_successes'] ?? 0,
            ];
        }

        return $healthStatus;
    }

    /**
     * Manually trigger a failover to the next available connection.
     */
    public function triggerFailover(?string $fromConnection = null): string
    {
        $startTime = microtime(true);
        $fromConnection = $fromConnection ?? $this->currentConnection;
        
        Log::warning("Triggering database failover from {$fromConnection}");

        // Find the next healthy connection
        $connectionFound = false;
        $newConnection = null;

        foreach ($this->connections as $priority => $connectionName) {
            // Skip the failed connection
            if ($connectionName === $fromConnection) {
                continue;
            }

            if ($this->isConnectionHealthy($connectionName)) {
                $newConnection = $connectionName;
                $connectionFound = true;
                break;
            }
        }

        if (!$connectionFound || !$newConnection) {
            $this->fireEvent('failover_failed', [
                'from_connection' => $fromConnection,
                'timestamp' => now(),
                'reason' => 'No healthy connections available'
            ]);
            
            throw new \RuntimeException("Failover failed: No healthy connections available");
        }

        // Update current connection
        $previousConnection = $this->currentConnection;
        $this->currentConnection = $newConnection;

        // Calculate failover duration
        $duration = microtime(true) - $startTime;

        // Update metrics
        $this->failoverMetrics['total_failovers']++;
        $this->failoverMetrics['last_failover'] = now();

        // Get current health status for event
        $healthStatus = $this->getAllConnectionHealth();

        // Fire legacy failover event for backward compatibility
        $this->fireEvent('failover_triggered', [
            'from_connection' => $previousConnection,
            'to_connection' => $newConnection,
            'timestamp' => now(),
            'failover_count' => $this->failoverMetrics['total_failovers']
        ]);

        // Fire new DatabaseFailoverEvent for Telescope tracking
        Event::dispatch(new DatabaseFailoverEvent(
            fromConnection: $previousConnection,
            toConnection: $newConnection,
            reason: 'Manual failover triggered',
            duration: $duration,
            healthStatus: $healthStatus,
            requestId: request()->header('X-Request-ID') ?? uniqid('req_'),
            context: [
                'failover_count' => $this->failoverMetrics['total_failovers'],
                'trigger_method' => 'manual',
                'user_agent' => request()->header('User-Agent'),
                'ip_address' => request()->ip(),
            ]
        ));

        // Update Laravel's default database connection
        Config::set('database.default', $this->getDatabaseConnectionName($newConnection));

        Log::info("Database failover completed: {$previousConnection} -> {$newConnection} in " . round($duration * 1000, 2) . "ms");

        return $newConnection;
    }

    /**
     * Attempt to recover a failed connection.
     */
    public function attemptRecovery(string $connectionName): bool
    {
        Log::info("Attempting recovery for database connection: {$connectionName}");

        // Clear cached health status
        Cache::forget("db_health_{$connectionName}");

        // Perform fresh health check
        $isHealthy = $this->performHealthCheck($connectionName);

        if ($isHealthy) {
            $this->failoverMetrics['recovery_count']++;
            
            $this->fireEvent('connection_recovered', [
                'connection' => $connectionName,
                'timestamp' => now(),
                'recovery_count' => $this->failoverMetrics['recovery_count']
            ]);

            Log::info("Database connection {$connectionName} successfully recovered");
            return true;
        }

        Log::warning("Database connection {$connectionName} recovery failed");
        return false;
    }

    /**
     * Get the current active database connection name.
     */
    public function getCurrentConnection(): string
    {
        return $this->currentConnection;
    }

    /**
     * Set the active database connection.
     */
    public function setActiveConnection(string $connectionName): bool
    {
        if (!in_array($connectionName, $this->connections)) {
            throw new \InvalidArgumentException("Invalid connection name: {$connectionName}");
        }

        if (!$this->isConnectionHealthy($connectionName)) {
            Log::warning("Attempted to set unhealthy connection as active: {$connectionName}");
            return false;
        }

        $previousConnection = $this->currentConnection;
        $this->currentConnection = $connectionName;

        // Update Laravel's default database connection
        Config::set('database.default', $this->getDatabaseConnectionName($connectionName));

        $this->fireEvent('connection_changed', [
            'from_connection' => $previousConnection,
            'to_connection' => $connectionName,
            'timestamp' => now()
        ]);

        return true;
    }

    /**
     * Get failover statistics and metrics.
     */
    public function getFailoverMetrics(): array
    {
        return array_merge($this->failoverMetrics, [
            'current_connection' => $this->currentConnection,
            'connection_health' => $this->connectionHealth,
            'uptime_duration' => now()->diffInSeconds($this->failoverMetrics['uptime_start']),
        ]);
    }

    /**
     * Get health status of all connections for event tracking.
     */
    public function getAllConnectionHealth(): array
    {
        $healthStatus = [];
        
        foreach ($this->connections as $priority => $connectionName) {
            $healthStatus[$connectionName] = [
                'healthy' => $this->isConnectionHealthy($connectionName),
                'response_time_ms' => $this->connectionHealth[$connectionName]['response_time'] ?? null,
                'last_error' => $this->connectionHealth[$connectionName]['last_error'] ?? null,
                'checked_at' => $this->connectionHealth[$connectionName]['last_check'] ?? null,
                'priority' => $priority,
            ];
        }
        
        return $healthStatus;
    }

    /**
     * Check if graceful degradation is enabled for the current service.
     */
    public function isGracefulDegradationEnabled(string $serviceName): bool
    {
        return $this->config['services'][$serviceName]['allow_readonly_fallback'] ?? false;
    }

    /**
     * Execute a callback with automatic failover handling.
     */
    public function executeWithFailover(callable $callback, array $options = [])
    {
        $maxAttempts = $options['max_attempts'] ?? $this->config['failover']['max_attempts'] ?? 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $healthyConnection = $this->getHealthyConnection();
                
                // Set the connection for this execution
                $originalConnection = Config::get('database.default');
                Config::set('database.default', $this->getDatabaseConnectionName($healthyConnection));

                try {
                    $result = $callback();
                    
                    // Restore original connection
                    Config::set('database.default', $originalConnection);
                    
                    return $result;
                } catch (\Exception $e) {
                    // Restore original connection
                    Config::set('database.default', $originalConnection);
                    throw $e;
                }

            } catch (\Exception $e) {
                $lastException = $e;
                
                Log::warning("Execution attempt {$attempt}/{$maxAttempts} failed: " . $e->getMessage());
                
                if ($attempt < $maxAttempts) {
                    // Mark current connection as unhealthy and try failover
                    Cache::forget("db_health_{$this->currentConnection}");
                    
                    try {
                        $this->triggerFailover();
                    } catch (\Exception $failoverException) {
                        Log::error("Failover attempt failed: " . $failoverException->getMessage());
                    }
                }
            }
        }

        throw new \RuntimeException("All failover attempts exhausted. Last error: " . $lastException->getMessage(), 0, $lastException);
    }

    /**
     * Register a failover event listener.
     */
    public function addEventListener(string $event, callable $listener): void
    {
        if (!isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }
        
        $this->eventListeners[$event][] = $listener;
    }

    /**
     * Get the connection priority order for failover.
     */
    public function getConnectionPriority(): array
    {
        return array_values($this->connections);
    }

    /**
     * Update the connection priority order.
     */
    public function updateConnectionPriority(array $connections): bool
    {
        // Validate that all provided connections are valid
        foreach ($connections as $connection) {
            if (!in_array($connection, $this->connections)) {
                throw new \InvalidArgumentException("Invalid connection name: {$connection}");
            }
        }

        // Update the priority order
        $this->connections = [
            'primary' => $connections[0] ?? $this->connections['primary'],
            'secondary' => $connections[1] ?? $this->connections['secondary'],
            'fallback' => $connections[2] ?? $this->connections['fallback'],
        ];

        $this->fireEvent('priority_updated', [
            'new_priority' => $this->connections,
            'timestamp' => now()
        ]);

        return true;
    }

    /**
     * Check if the primary database connection is healthy
     */
    public function isPrimaryHealthy(): bool
    {
        return $this->isConnectionHealthy($this->connections['primary']);
    }

    /**
     * Check if the secondary database connection is healthy
     */
    public function isSecondaryHealthy(): bool
    {
        return $this->isConnectionHealthy($this->connections['secondary']);
    }

    /**
     * Switch to the secondary database connection
     */
    public function switchToSecondary(): bool
    {
        return $this->setActiveConnection($this->connections['secondary']);
    }

    /**
     * Switch back to the primary database connection
     */
    public function switchToPrimary(): bool
    {
        return $this->setActiveConnection($this->connections['primary']);
    }

    /**
     * Perform automatic failover if primary is unhealthy
     */
    public function performFailover(): bool
    {
        if (!$this->isPrimaryHealthy()) {
            $newConnection = $this->triggerFailover($this->connections['primary']);
            return $newConnection !== $this->connections['primary'];
        }
        return false;
    }

    /**
     * Get failover status information
     */
    public function getFailoverStatus(): array
    {
        return $this->getFailoverMetrics();
    }

    /**
     * Test database connection health
     */
    public function testConnection(string $connection): bool
    {
        return $this->isConnectionHealthy($connection);
    }

    /**
     * Get connection health metrics
     */
    public function getConnectionMetrics(string $connection): array
    {
        return $this->connectionHealth[$connection] ?? [
            'healthy' => false,
            'last_check' => null,
            'consecutive_failures' => 0,
            'consecutive_successes' => 0,
        ];
    }

    /**
     * Enable or disable automatic failover
     */
    public function setAutoFailover(bool $enabled): void
    {
        $this->config['auto_failover_enabled'] = $enabled;
        Config::set('database-failover.auto_failover_enabled', $enabled);
    }

    /**
     * Check if automatic failover is enabled
     */
    public function isAutoFailoverEnabled(): bool
    {
        return $this->config['auto_failover_enabled'] ?? true;
    }

    /**
     * Initialize health status for all connections.
     */
    private function initializeHealthStatus(): void
    {
        foreach ($this->connections as $connectionName) {
            $this->connectionHealth[$connectionName] = [
                'healthy' => true,
                'last_check' => null,
                'consecutive_failures' => 0,
                'consecutive_successes' => 0,
            ];
        }
    }

    /**
     * Fire an event to all registered listeners.
     */
    private function fireEvent(string $event, array $data): void
    {
        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $listener) {
                try {
                    $listener($data);
                } catch (\Exception $e) {
                    Log::error("Event listener failed for {$event}: " . $e->getMessage());
                }
            }
        }

        // Also log the event
        Log::info("Database failover event: {$event}", $data);
    }

    /**
     * Check if a connection is PostgreSQL.
     */
    private function isDatabasePostgreSQL(string $connectionName): bool
    {
        return str_contains($connectionName, 'postgresql') || str_contains($connectionName, 'pgsql');
    }

    /**
     * Check if a connection is MongoDB.
     */
    private function isDatabaseMongoDB(string $connectionName): bool
    {
        return str_contains($connectionName, 'mongodb') || str_contains($connectionName, 'mongo');
    }

    /**
     * Perform MongoDB-specific health check.
     */
    private function performMongoHealthCheck(string $connectionName): bool
    {
        // For MongoDB, we'll implement a basic ping operation
        // This is a placeholder - actual implementation would depend on the MongoDB driver
        try {
            // Assuming we have a MongoDB connection configured
            $connection = DB::connection($connectionName);
            // Perform a simple operation to verify connectivity
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the actual Laravel database connection name for a failover connection.
     */
    private function getDatabaseConnectionName(string $failoverConnectionName): string
    {
        // Map failover connection names to actual Laravel connection names
        $mapping = [
            'neon_postgresql' => 'pgsql',
            'cloud_postgresql' => 'pgsql_secondary',
            'mongodb_atlas' => 'mongodb'
        ];

        return $mapping[$failoverConnectionName] ?? $failoverConnectionName;
    }
}
