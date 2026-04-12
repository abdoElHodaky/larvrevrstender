<?php

declare(strict_types=1);

namespace Shared\Health\Enums;

/**
 * Health Status Enum - PHP 8.3 Implementation
 * 
 * Standardized health status types for comprehensive
 * service health monitoring across the ecosystem.
 */
enum HealthStatus: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';
    case UNKNOWN = 'unknown';

    /**
     * Get the HTTP status code for this health status
     */
    public function getHttpStatusCode(): int
    {
        return match ($this) {
            self::HEALTHY => 200,
            self::DEGRADED => 200, // Still operational but with issues
            self::UNHEALTHY => 503,
            self::UNKNOWN => 503,
        };
    }

    /**
     * Get the display name for this status
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::HEALTHY => 'Healthy',
            self::DEGRADED => 'Degraded',
            self::UNHEALTHY => 'Unhealthy',
            self::UNKNOWN => 'Unknown',
        };
    }

    /**
     * Get the description for this status
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::HEALTHY => 'All systems operational',
            self::DEGRADED => 'Some issues detected but service is operational',
            self::UNHEALTHY => 'Service is not operational',
            self::UNKNOWN => 'Health status cannot be determined',
        };
    }

    /**
     * Get the color code for UI display
     */
    public function getColorCode(): string
    {
        return match ($this) {
            self::HEALTHY => '#28a745',   // Green
            self::DEGRADED => '#ffc107',  // Yellow
            self::UNHEALTHY => '#dc3545', // Red
            self::UNKNOWN => '#6c757d',   // Gray
        };
    }

    /**
     * Check if this status indicates the service is operational
     */
    public function isOperational(): bool
    {
        return match ($this) {
            self::HEALTHY, self::DEGRADED => true,
            self::UNHEALTHY, self::UNKNOWN => false,
        };
    }

    /**
     * Check if this status requires immediate attention
     */
    public function requiresAttention(): bool
    {
        return match ($this) {
            self::HEALTHY => false,
            self::DEGRADED, self::UNHEALTHY, self::UNKNOWN => true,
        };
    }

    /**
     * Get priority level for alerting (1 = highest, 4 = lowest)
     */
    public function getAlertPriority(): int
    {
        return match ($this) {
            self::UNHEALTHY => 1,  // Critical
            self::UNKNOWN => 2,    // High
            self::DEGRADED => 3,   // Medium
            self::HEALTHY => 4,    // Low/None
        };
    }

    /**
     * Determine overall status from multiple component statuses
     */
    public static function aggregate(array $statuses): self
    {
        if (empty($statuses)) {
            return self::UNKNOWN;
        }

        $statusCounts = array_count_values(
            array_map(fn($status) => $status instanceof self ? $status->value : $status, $statuses)
        );

        // If any component is unhealthy, overall is unhealthy
        if (($statusCounts[self::UNHEALTHY->value] ?? 0) > 0) {
            return self::UNHEALTHY;
        }

        // If any component is unknown, overall is unknown
        if (($statusCounts[self::UNKNOWN->value] ?? 0) > 0) {
            return self::UNKNOWN;
        }

        // If any component is degraded, overall is degraded
        if (($statusCounts[self::DEGRADED->value] ?? 0) > 0) {
            return self::DEGRADED;
        }

        // All components are healthy
        return self::HEALTHY;
    }

    /**
     * Create status from boolean (for backward compatibility)
     */
    public static function fromBoolean(bool $isHealthy): self
    {
        return $isHealthy ? self::HEALTHY : self::UNHEALTHY;
    }

    /**
     * Create status from HTTP status code
     */
    public static function fromHttpStatusCode(int $statusCode): self
    {
        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => self::HEALTHY,
            $statusCode >= 400 && $statusCode < 500 => self::DEGRADED,
            $statusCode >= 500 => self::UNHEALTHY,
            default => self::UNKNOWN,
        };
    }
}
