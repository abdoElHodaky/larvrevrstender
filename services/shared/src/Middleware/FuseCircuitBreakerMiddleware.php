<?php

namespace Shared\Middleware;

use Harris21\Fuse\Middleware\CircuitBreakerMiddleware;
use Illuminate\Support\Facades\Log;

/**
 * Laravel Fuse Circuit Breaker Middleware Integration
 * 
 * This middleware extends the Laravel Fuse CircuitBreakerMiddleware to provide
 * seamless integration with the existing QueueCircuitBreakerProcedure infrastructure.
 * 
 * Features:
 * - Wraps Harris21\Fuse\Middleware\CircuitBreakerMiddleware
 * - Provides logging and monitoring integration
 * - Maintains compatibility with existing queue infrastructure
 * - Supports service-specific configuration
 */
class FuseCircuitBreakerMiddleware extends CircuitBreakerMiddleware
{
    /**
     * The service name for circuit breaker configuration
     */
    protected string $serviceName;

    /**
     * Create a new circuit breaker middleware instance
     *
     * @param string $serviceName The service name for configuration lookup
     */
    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
        
        // Initialize parent with service name
        parent::__construct($serviceName);
        
        Log::debug("FuseCircuitBreakerMiddleware initialized for service: {$serviceName}");
    }

    /**
     * Handle the job execution with circuit breaker protection
     *
     * @param mixed $job
     * @param callable $next
     * @return mixed
     */
    public function handle($job, callable $next)
    {
        Log::debug("Circuit breaker middleware handling job for service: {$this->serviceName}", [
            'job_class' => get_class($job),
            'service' => $this->serviceName
        ]);

        try {
            // Delegate to parent Laravel Fuse middleware
            return parent::handle($job, $next);
        } catch (\Exception $e) {
            Log::error("Circuit breaker middleware error for service: {$this->serviceName}", [
                'job_class' => get_class($job),
                'service' => $this->serviceName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
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
}
