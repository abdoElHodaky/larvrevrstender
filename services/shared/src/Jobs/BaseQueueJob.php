<?php

namespace Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shared\Middleware\FuseCircuitBreakerMiddleware;

/**
 * Base Queue Job with Laravel Fuse Circuit Breaker Integration
 * 
 * This abstract base class provides a foundation for all queue jobs in the
 * reverse tender platform with built-in circuit breaker protection using Laravel Fuse.
 * 
 * Features:
 * - Laravel Fuse circuit breaker middleware integration
 * - Configurable retry logic with unlimited releases
 * - Service-specific circuit breaker configuration
 * - Comprehensive logging and error handling
 * - Standardized job structure across all microservices
 */
abstract class BaseQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Set to 0 to allow unlimited releases (circuit breaker handles failures)
     */
    public int $tries = 0;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     * This caps actual failures while allowing circuit breaker releases
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The service name for circuit breaker configuration
     */
    protected string $serviceName;

    /**
     * Create a new job instance
     *
     * @param string $serviceName The service name for circuit breaker configuration
     */
    public function __construct(string $serviceName = 'default')
    {
        $this->serviceName = $serviceName;
        
        Log::debug("BaseQueueJob created for service: {$serviceName}", [
            'job_class' => static::class,
            'service' => $serviceName
        ]);
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            new FuseCircuitBreakerMiddleware($this->serviceName)
        ];
    }

    /**
     * Execute the job.
     * This method should be implemented by concrete job classes.
     *
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed for service: {$this->serviceName}", [
            'job_class' => static::class,
            'service' => $this->serviceName,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempts' => $this->attempts(),
            'max_exceptions' => $this->maxExceptions
        ]);

        // Call the concrete implementation's failure handler if it exists
        if (method_exists($this, 'onFailure')) {
            $this->onFailure($exception);
        }
    }

    /**
     * Get the service name
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * Set the service name
     *
     * @param string $serviceName
     * @return self
     */
    public function setServiceName(string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'service:' . $this->serviceName,
            'job:' . class_basename(static::class)
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return int
     */
    public function backoff(): int
    {
        // Exponential backoff: 1, 2, 4, 8, 16 seconds...
        return min(pow(2, $this->attempts()), 300); // Cap at 5 minutes
    }

    /**
     * Determine if the job should be retried based on the exception.
     *
     * @param \Throwable $exception
     * @return bool
     */
    public function retryUntil(): \DateTime
    {
        // Retry for up to 24 hours
        return now()->addHours(24);
    }
}
