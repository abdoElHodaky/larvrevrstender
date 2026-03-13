<?php

namespace Shared\Contracts;

/**
 * Database Failover Interface
 * 
 * Defines the contract for database failover management services.
 * This interface ensures consistent failover behavior across all services.
 */
interface DatabaseFailoverInterface
{
    /**
     * Check if the primary database connection is healthy
     *
     * @return bool
     */
    public function isPrimaryHealthy(): bool;

    /**
     * Check if the secondary database connection is healthy
     *
     * @return bool
     */
    public function isSecondaryHealthy(): bool;

    /**
     * Get the current active database connection name
     *
     * @return string
     */
    public function getCurrentConnection(): string;

    /**
     * Switch to the secondary database connection
     *
     * @return bool Success status
     */
    public function switchToSecondary(): bool;

    /**
     * Switch back to the primary database connection
     *
     * @return bool Success status
     */
    public function switchToPrimary(): bool;

    /**
     * Perform automatic failover if primary is unhealthy
     *
     * @return bool True if failover was performed
     */
    public function performFailover(): bool;

    /**
     * Get failover status information
     *
     * @return array
     */
    public function getFailoverStatus(): array;

    /**
     * Test database connection health
     *
     * @param string $connection Connection name
     * @return bool
     */
    public function testConnection(string $connection): bool;

    /**
     * Get connection health metrics
     *
     * @param string $connection Connection name
     * @return array
     */
    public function getConnectionMetrics(string $connection): array;

    /**
     * Enable or disable automatic failover
     *
     * @param bool $enabled
     * @return void
     */
    public function setAutoFailover(bool $enabled): void;

    /**
     * Check if automatic failover is enabled
     *
     * @return bool
     */
    public function isAutoFailoverEnabled(): bool;
}
