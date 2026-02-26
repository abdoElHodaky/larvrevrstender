<?php

namespace Shared\HealthCheck;

use Carbon\Carbon;

/**
 * Connection Health Status
 * 
 * Represents the health status of a database connection including
 * connectivity, query performance, metrics, errors, and warnings.
 */
class ConnectionHealthStatus
{
    private string $connectionName;
    private bool $healthy = false;
    private bool $connectable = false;
    private bool $queryable = false;
    private float $checkDuration = 0.0;
    private Carbon $checkedAt;
    private array $metrics = [];
    private array $errors = [];
    private array $warnings = [];

    public function __construct(string $connectionName)
    {
        $this->connectionName = $connectionName;
        $this->checkedAt = now();
    }

    /**
     * Get the connection name.
     *
     * @return string The connection name
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Check if the connection is overall healthy.
     *
     * @return bool True if healthy, false otherwise
     */
    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    /**
     * Set the overall health status.
     *
     * @param bool $healthy The health status
     * @return void
     */
    public function setHealthy(bool $healthy): void
    {
        $this->healthy = $healthy;
    }

    /**
     * Set the overall health status (alias for setHealthy).
     *
     * @param bool $healthy The health status
     * @return void
     */
    public function setOverallHealth(bool $healthy): void
    {
        $this->setHealthy($healthy);
    }

    /**
     * Check if the connection is connectable.
     *
     * @return bool True if connectable, false otherwise
     */
    public function isConnectable(): bool
    {
        return $this->connectable;
    }

    /**
     * Set the connectable status.
     *
     * @param bool $connectable The connectable status
     * @return void
     */
    public function setConnectable(bool $connectable): void
    {
        $this->connectable = $connectable;
    }

    /**
     * Check if the connection is queryable.
     *
     * @return bool True if queryable, false otherwise
     */
    public function isQueryable(): bool
    {
        return $this->queryable;
    }

    /**
     * Set the queryable status.
     *
     * @param bool $queryable The queryable status
     * @return void
     */
    public function setQueryable(bool $queryable): void
    {
        $this->queryable = $queryable;
    }

    /**
     * Get the health check duration in milliseconds.
     *
     * @return float The check duration
     */
    public function getCheckDuration(): float
    {
        return $this->checkDuration;
    }

    /**
     * Set the health check duration in milliseconds.
     *
     * @param float $duration The check duration
     * @return void
     */
    public function setCheckDuration(float $duration): void
    {
        $this->checkDuration = $duration;
    }

    /**
     * Get the timestamp when the check was performed.
     *
     * @return Carbon The check timestamp
     */
    public function getCheckedAt(): Carbon
    {
        return $this->checkedAt;
    }

    /**
     * Set the timestamp when the check was performed.
     *
     * @param Carbon $checkedAt The check timestamp
     * @return void
     */
    public function setCheckedAt(Carbon $checkedAt): void
    {
        $this->checkedAt = $checkedAt;
    }

    /**
     * Add a metric to the health status.
     *
     * @param string $key The metric key
     * @param mixed $value The metric value
     * @return void
     */
    public function addMetric(string $key, $value): void
    {
        $this->metrics[$key] = $value;
    }

    /**
     * Get a specific metric value.
     *
     * @param string $key The metric key
     * @param mixed $default Default value if metric doesn't exist
     * @return mixed The metric value or default
     */
    public function getMetric(string $key, $default = null)
    {
        return $this->metrics[$key] ?? $default;
    }

    /**
     * Get all metrics.
     *
     * @return array All metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Set all metrics.
     *
     * @param array $metrics The metrics array
     * @return void
     */
    public function setMetrics(array $metrics): void
    {
        $this->metrics = $metrics;
    }

    /**
     * Add an error to the health status.
     *
     * @param string $type The error type
     * @param string $message The error message
     * @return void
     */
    public function addError(string $type, string $message): void
    {
        $this->errors[$type] = $message;
    }

    /**
     * Get a specific error message.
     *
     * @param string $type The error type
     * @return string|null The error message or null if not found
     */
    public function getError(string $type): ?string
    {
        return $this->errors[$type] ?? null;
    }

    /**
     * Get all errors.
     *
     * @return array All errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are any errors.
     *
     * @return bool True if there are errors, false otherwise
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Clear all errors.
     *
     * @return void
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Add a warning to the health status.
     *
     * @param string $type The warning type
     * @param string $message The warning message
     * @return void
     */
    public function addWarning(string $type, string $message): void
    {
        $this->warnings[$type] = $message;
    }

    /**
     * Get a specific warning message.
     *
     * @param string $type The warning type
     * @return string|null The warning message or null if not found
     */
    public function getWarning(string $type): ?string
    {
        return $this->warnings[$type] ?? null;
    }

    /**
     * Get all warnings.
     *
     * @return array All warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if there are any warnings.
     *
     * @return bool True if there are warnings, false otherwise
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Clear all warnings.
     *
     * @return void
     */
    public function clearWarnings(): void
    {
        $this->warnings = [];
    }

    /**
     * Get a summary of the health status.
     *
     * @return array Health status summary
     */
    public function getSummary(): array
    {
        return [
            'connection_name' => $this->connectionName,
            'healthy' => $this->healthy,
            'connectable' => $this->connectable,
            'queryable' => $this->queryable,
            'check_duration_ms' => $this->checkDuration,
            'checked_at' => $this->checkedAt->toISOString(),
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'metrics_count' => count($this->metrics),
        ];
    }

    /**
     * Get detailed health status information.
     *
     * @return array Detailed health status
     */
    public function getDetails(): array
    {
        return [
            'connection_name' => $this->connectionName,
            'healthy' => $this->healthy,
            'connectable' => $this->connectable,
            'queryable' => $this->queryable,
            'check_duration_ms' => $this->checkDuration,
            'checked_at' => $this->checkedAt->toISOString(),
            'metrics' => $this->metrics,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Convert the health status to an array.
     *
     * @return array Health status as array
     */
    public function toArray(): array
    {
        return $this->getDetails();
    }

    /**
     * Convert the health status to JSON.
     *
     * @return string Health status as JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Get the health status level (healthy, warning, error).
     *
     * @return string The status level
     */
    public function getStatusLevel(): string
    {
        if (!$this->healthy || $this->hasErrors()) {
            return 'error';
        }

        if ($this->hasWarnings()) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Get a human-readable status message.
     *
     * @return string Status message
     */
    public function getStatusMessage(): string
    {
        if (!$this->connectable) {
            return "Connection '{$this->connectionName}' is not connectable";
        }

        if (!$this->queryable) {
            return "Connection '{$this->connectionName}' is connectable but not queryable";
        }

        if ($this->hasErrors()) {
            $errorCount = count($this->errors);
            return "Connection '{$this->connectionName}' has {$errorCount} error(s)";
        }

        if ($this->hasWarnings()) {
            $warningCount = count($this->warnings);
            return "Connection '{$this->connectionName}' is healthy with {$warningCount} warning(s)";
        }

        return "Connection '{$this->connectionName}' is healthy";
    }

    /**
     * Check if the connection has been healthy for a specified duration.
     *
     * @param int $seconds The duration in seconds
     * @return bool True if healthy for the specified duration
     */
    public function hasBeenHealthyFor(int $seconds): bool
    {
        // This would require historical data to implement properly
        // For now, we'll just check if it's currently healthy
        return $this->healthy && $this->checkedAt->diffInSeconds(now()) <= $seconds;
    }

    /**
     * Check if the connection has been unhealthy for a specified duration.
     *
     * @param int $seconds The duration in seconds
     * @return bool True if unhealthy for the specified duration
     */
    public function hasBeenUnhealthyFor(int $seconds): bool
    {
        // This would require historical data to implement properly
        // For now, we'll just check if it's currently unhealthy
        return !$this->healthy && $this->checkedAt->diffInSeconds(now()) <= $seconds;
    }

    /**
     * Get performance score based on metrics.
     *
     * @return float Performance score (0-100)
     */
    public function getPerformanceScore(): float
    {
        if (!$this->healthy) {
            return 0.0;
        }

        $score = 100.0;

        // Deduct points for slow query response time
        $queryTime = $this->getMetric('query_response_time', 0);
        if ($queryTime > 1000) { // > 1 second
            $score -= 30;
        } elseif ($queryTime > 500) { // > 500ms
            $score -= 15;
        } elseif ($queryTime > 100) { // > 100ms
            $score -= 5;
        }

        // Deduct points for warnings
        $score -= count($this->warnings) * 5;

        // Deduct points for high replication lag
        $replicationLag = $this->getMetric('replication_lag_seconds', 0);
        if ($replicationLag > 30) {
            $score -= 20;
        } elseif ($replicationLag > 10) {
            $score -= 10;
        }

        return max(0.0, $score);
    }

    /**
     * Magic method to convert to string.
     *
     * @return string String representation
     */
    public function __toString(): string
    {
        return $this->getStatusMessage();
    }
}
