<?php

namespace Shared\Contracts;

/**
 * Database Failover Interface
 * 
 * Defines the contract for database failover management across the 3-tier architecture:
 * - Primary: Neon PostgreSQL
 * - Secondary: Cloud Provider PostgreSQL  
 * - Fallback: MongoDB Atlas
 */
interface DatabaseFailoverInterface
{
    /**
     * Get the currently healthy database connection name.
     */
    public function getHealthyConnection(): string;

    /**
     * Check if a specific database connection is healthy.
     */
    public function isConnectionHealthy(string $connectionName): bool;

    /**
     * Get health status of all database connections.
     */
    public function getAllConnectionsHealth(): array;

    /**
     * Trigger failover from one connection to another.
     */
    public function triggerFailover(?string $fromConnection = null): string;

    /**
     * Attempt to recover a failed connection.
     */
    public function attemptRecovery(string $connectionName): bool;

    /**
     * Get the current active connection name.
     */
    public function getCurrentConnection(): string;

    /**
     * Set the active database connection.
     */
    public function setActiveConnection(string $connectionName): bool;

    /**
     * Get failover metrics and statistics.
     */
    public function getFailoverMetrics(): array;

    /**
     * Execute a callback with automatic failover handling.
     */
    public function executeWithFailover(callable $callback, array $options = []);

    /**
     * Add an event listener for failover events.
     */
    public function addEventListener(string $event, callable $listener): void;

    /**
     * Get the connection priority order.
     */
    public function getConnectionPriority(): array;

    /**
     * Update the connection priority order.
     */
    public function updateConnectionPriority(array $connections): bool;

    /**
     * Check if graceful degradation is enabled for a service.
     */
    public function isGracefulDegradationEnabled(string $serviceName): bool;
}
