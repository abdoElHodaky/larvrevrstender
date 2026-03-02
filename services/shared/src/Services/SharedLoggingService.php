<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Ka4ivan\LaravelLogger\Facades\Llog;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;

/**
 * Shared Logging Service
 * 
 * Centralized logging service that integrates ka4ivan/laravel-logger
 * with Laravel Telescope for comprehensive cross-service logging.
 * Provides request correlation, context propagation, and structured logging.
 */
class SharedLoggingService
{
    private array $context = [];
    private ?string $requestId = null;
    private ?string $serviceName = null;
    private array $config;

    public function __construct()
    {
        $this->config = config('logging', []);
        $this->initializeContext();
    }

    /**
     * Initialize logging context with service identification and request correlation.
     */
    private function initializeContext(): void
    {
        $this->serviceName = $this->detectServiceName();
        $this->requestId = $this->generateOrExtractRequestId();
        
        $this->context = [
            'service_name' => $this->serviceName,
            'request_id' => $this->requestId,
            'timestamp' => Carbon::now()->toISOString(),
            'environment' => app()->environment(),
        ];
    }

    /**
     * Detect the current service name from configuration or environment.
     */
    private function detectServiceName(): string
    {
        // Try to get service name from various sources
        $serviceName = config('app.service_name') 
            ?? env('SERVICE_NAME') 
            ?? env('APP_NAME', 'unknown-service');

        // Clean up service name
        return strtolower(str_replace([' ', '_'], '-', $serviceName));
    }

    /**
     * Generate a new request ID or extract from headers.
     */
    private function generateOrExtractRequestId(): string
    {
        // Try to get request ID from HTTP headers (for service-to-service calls)
        if (request() && request()->hasHeader('X-Request-ID')) {
            return request()->header('X-Request-ID');
        }

        // Generate new request ID
        return Uuid::uuid4()->toString();
    }

    /**
     * Get the current request ID.
     */
    public function getRequestId(): string
    {
        return $this->requestId ?? $this->generateOrExtractRequestId();
    }

    /**
     * Get the current service name.
     */
    public function getServiceName(): string
    {
        return $this->serviceName ?? $this->detectServiceName();
    }

    /**
     * Add context to all subsequent log entries.
     */
    public function addContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Set user context for logging.
     */
    public function setUser($user): self
    {
        if ($user) {
            $this->context['user'] = [
                'id' => $user->id ?? null,
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
            ];
        }
        return $this;
    }

    /**
     * Log emergency message.
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Log alert message.
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Log critical message.
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Log error message.
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning message.
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log notice message.
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Log info message.
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log debug message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log database failover event.
     */
    public function databaseFailover(string $event, array $context = []): void
    {
        $failoverContext = array_merge($context, [
            'event_type' => 'database_failover',
            'failover_event' => $event,
            'timestamp' => Carbon::now()->toISOString(),
        ]);

        $this->logToChannel('database_failover', 'warning', 
            "Database Failover Event: {$event}", $failoverContext);
    }

    /**
     * Log health check event.
     */
    public function healthCheck(string $connection, bool $healthy, array $context = []): void
    {
        $level = $healthy ? 'info' : 'warning';
        $status = $healthy ? 'HEALTHY' : 'UNHEALTHY';
        
        $healthContext = array_merge($context, [
            'event_type' => 'health_check',
            'connection' => $connection,
            'healthy' => $healthy,
            'status' => $status,
            'timestamp' => Carbon::now()->toISOString(),
        ]);

        $this->logToChannel('health_checks', $level, 
            "Health Check: {$connection} is {$status}", $healthContext);
    }

    /**
     * Log request correlation event.
     */
    public function requestCorrelation(string $event, array $context = []): void
    {
        $correlationContext = array_merge($context, [
            'event_type' => 'request_correlation',
            'correlation_event' => $event,
            'timestamp' => Carbon::now()->toISOString(),
        ]);

        $this->logToChannel('request_correlation', 'info', 
            "Request Correlation: {$event}", $correlationContext);
    }

    /**
     * Log to a specific channel.
     */
    public function logToChannel(string $channel, string $level, string $message, array $context = []): void
    {
        $fullContext = array_merge($this->context, $context);
        
        // Use ka4ivan/laravel-logger for structured logging
        Llog::channel($channel)->{$level}($message, $fullContext);
        
        // Also log to Laravel's default logger for Telescope integration
        if ($this->shouldLogToTelescope($level)) {
            Log::channel('telescope')->{$level}($message, $fullContext);
        }
    }

    /**
     * Main logging method that handles all log levels.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $fullContext = array_merge($this->context, $context);
        
        // Use ka4ivan/laravel-logger
        Llog::{$level}($message, $fullContext);
        
        // Also log to default Laravel logger for Telescope
        if ($this->shouldLogToTelescope($level)) {
            Log::{$level}($message, $fullContext);
        }
    }

    /**
     * Determine if a log level should be sent to Telescope.
     */
    private function shouldLogToTelescope(string $level): bool
    {
        $telescopeConfig = $this->config['telescope'] ?? [];
        
        if (!($telescopeConfig['enabled'] ?? true)) {
            return false;
        }

        $captureLevels = $telescopeConfig['capture_levels'] ?? ['error', 'warning', 'info'];
        
        return in_array($level, $captureLevels);
    }

    /**
     * Create a structured log entry for database failover events.
     */
    public function createFailoverLogEntry(string $event, array $data = []): array
    {
        return [
            'event_type' => 'database_failover',
            'event' => $event,
            'service' => $this->getServiceName(),
            'request_id' => $this->getRequestId(),
            'timestamp' => Carbon::now()->toISOString(),
            'data' => $data,
            'context' => $this->context,
        ];
    }

    /**
     * Batch log multiple entries (useful for performance).
     */
    public function batchLog(array $entries): void
    {
        foreach ($entries as $entry) {
            $level = $entry['level'] ?? 'info';
            $message = $entry['message'] ?? 'Batch log entry';
            $context = $entry['context'] ?? [];
            
            $this->log($level, $message, $context);
        }
    }

    /**
     * Get current logging context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Clear current context (useful for long-running processes).
     */
    public function clearContext(): self
    {
        $this->context = [];
        $this->initializeContext();
        return $this;
    }

    /**
     * Create a child logger with additional context.
     */
    public function withContext(array $context): self
    {
        $child = clone $this;
        $child->addContext($context);
        return $child;
    }

    /**
     * Log performance metrics.
     */
    public function performance(string $operation, float $duration, array $context = []): void
    {
        $performanceContext = array_merge($context, [
            'event_type' => 'performance',
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => Carbon::now()->toISOString(),
        ]);

        $this->info("Performance: {$operation} completed in {$performanceContext['duration_ms']}ms", $performanceContext);
    }

    /**
     * Log exception with full context.
     */
    public function exception(\Throwable $exception, array $context = []): void
    {
        $exceptionContext = array_merge($context, [
            'event_type' => 'exception',
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
            'timestamp' => Carbon::now()->toISOString(),
        ]);

        $this->error("Exception: {$exception->getMessage()}", $exceptionContext);
    }
}
