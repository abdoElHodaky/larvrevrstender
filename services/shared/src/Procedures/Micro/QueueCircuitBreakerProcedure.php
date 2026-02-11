<?php

namespace Shared\Procedures\Micro;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Shared\Middleware\FuseCircuitBreakerMiddleware;
use Shared\Jobs\BaseQueueJob;

/**
 * Queue Circuit Breaker Procedure
 * 
 * Provides circuit breaker functionality specifically for queue jobs and
 * asynchronous operations. Integrates with Laravel Fuse patterns to
 * prevent cascade failures in queue processing.
 */
trait QueueCircuitBreakerProcedure
{
    /**
     * Dispatch a job with circuit breaker protection
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function dispatchWithCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $jobClass = $params['job_class'] ?? null;
            $serviceName = $params['service_name'] ?? null;
            $jobData = $params['job_data'] ?? [];
            $queueName = $params['queue'] ?? 'default';
            $delay = $params['delay'] ?? null;

            if (!$jobClass) {
                return [
                    'success' => false,
                    'error' => 'Job class is required',
                    'metadata' => ['procedure' => 'dispatchWithCircuitBreaker']
                ];
            }

            if (!class_exists($jobClass)) {
                return [
                    'success' => false,
                    'error' => "Job class {$jobClass} does not exist",
                    'metadata' => ['procedure' => 'dispatchWithCircuitBreaker']
                ];
            }

            // Check if circuit breaker is open for the service
            if ($serviceName && $this->isCircuitOpen($serviceName)) {
                Log::warning('Circuit breaker open: Job dispatch blocked', [
                    'service' => $serviceName,
                    'job_class' => $jobClass,
                    'context' => $context
                ]);

                return [
                    'success' => false,
                    'error' => "Circuit breaker is open for service: {$serviceName}",
                    'metadata' => [
                        'procedure' => 'dispatchWithCircuitBreaker',
                        'circuit_state' => 'open',
                        'service' => $serviceName
                    ]
                ];
            }

            // Create job instance
            $job = new $jobClass(...array_values($jobData));

            // Configure job if it extends BaseQueueJob
            if ($job instanceof BaseQueueJob) {
                if ($serviceName) {
                    $job->setServiceName($serviceName);
                }

                // Set queue name
                if ($queueName !== 'default') {
                    $job->onQueue($queueName);
                }
            }

            // Dispatch job
            if ($delay) {
                $dispatchedJob = dispatch($job)->delay($delay);
            } else {
                $dispatchedJob = dispatch($job);
            }

            Log::info('Job dispatched with circuit breaker protection', [
                'job_class' => $jobClass,
                'service' => $serviceName,
                'queue' => $queueName,
                'delay' => $delay,
                'context' => $context
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_class' => $jobClass,
                    'service' => $serviceName,
                    'queue' => $queueName,
                    'dispatched_at' => now()->toISOString(),
                    'job_id' => method_exists($dispatchedJob, 'getId') ? $dispatchedJob->getId() : null
                ],
                'metadata' => ['procedure' => 'dispatchWithCircuitBreaker']
            ];

        } catch (Exception $e) {
            Log::error('Failed to dispatch job with circuit breaker', [
                'error' => $e->getMessage(),
                'job_class' => $params['job_class'] ?? null,
                'service' => $params['service_name'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to dispatch job: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'dispatchWithCircuitBreaker']
            ];
        }
    }

    /**
     * Get queue circuit breaker statistics
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getQueueCircuitBreakerStats(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;
            $queueName = $params['queue'] ?? null;

            if ($serviceName) {
                // Get stats for specific service
                $stats = $this->getServiceQueueStats($serviceName);
            } elseif ($queueName) {
                // Get stats for specific queue
                $stats = $this->getQueueStats($queueName);
            } else {
                // Get stats for all services and queues
                $stats = $this->getAllQueueStats();
            }

            return [
                'success' => true,
                'data' => $stats,
                'metadata' => ['procedure' => 'getQueueCircuitBreakerStats']
            ];

        } catch (Exception $e) {
            Log::error('Failed to get queue circuit breaker stats', [
                'error' => $e->getMessage(),
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get stats: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'getQueueCircuitBreakerStats']
            ];
        }
    }

    /**
     * Reset queue circuit breaker for a service
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function resetQueueCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;

            if (!$serviceName) {
                return [
                    'success' => false,
                    'error' => 'Service name is required',
                    'metadata' => ['procedure' => 'resetQueueCircuitBreaker']
                ];
            }

            // Reset circuit breaker state
            $middleware = new FuseCircuitBreakerMiddleware($serviceName);
            $middleware->reset();

            // Clear release counters
            $this->clearServiceReleaseCounters($serviceName);

            Log::info('Queue circuit breaker reset', [
                'service' => $serviceName,
                'context' => $context
            ]);

            return [
                'success' => true,
                'data' => [
                    'service' => $serviceName,
                    'reset_at' => now()->toISOString()
                ],
                'metadata' => ['procedure' => 'resetQueueCircuitBreaker']
            ];

        } catch (Exception $e) {
            Log::error('Failed to reset queue circuit breaker', [
                'error' => $e->getMessage(),
                'service' => $params['service_name'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to reset circuit breaker: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'resetQueueCircuitBreaker']
            ];
        }
    }

    /**
     * Force open queue circuit breaker for a service
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function forceOpenQueueCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;

            if (!$serviceName) {
                return [
                    'success' => false,
                    'error' => 'Service name is required',
                    'metadata' => ['procedure' => 'forceOpenQueueCircuitBreaker']
                ];
            }

            // Force open circuit breaker
            $middleware = new FuseCircuitBreakerMiddleware($serviceName);
            $middleware->forceOpen();

            Log::warning('Queue circuit breaker forced open', [
                'service' => $serviceName,
                'context' => $context
            ]);

            return [
                'success' => true,
                'data' => [
                    'service' => $serviceName,
                    'forced_open_at' => now()->toISOString()
                ],
                'metadata' => ['procedure' => 'forceOpenQueueCircuitBreaker']
            ];

        } catch (Exception $e) {
            Log::error('Failed to force open queue circuit breaker', [
                'error' => $e->getMessage(),
                'service' => $params['service_name'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to force open circuit breaker: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'forceOpenQueueCircuitBreaker']
            ];
        }
    }

    /**
     * Get queue health status
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getQueueHealth(array $params, array $context = []): array
    {
        try {
            $queueName = $params['queue'] ?? 'default';
            
            // Get queue size and other metrics
            $queueSize = Queue::size($queueName);
            
            // Get failed jobs count (if available)
            $failedJobsCount = $this->getFailedJobsCount();
            
            // Get circuit breaker states for all services
            $circuitStates = $this->getAllCircuitStates();
            
            $health = [
                'queue' => $queueName,
                'size' => $queueSize,
                'failed_jobs' => $failedJobsCount,
                'circuit_breakers' => $circuitStates,
                'healthy' => $queueSize < 1000 && $failedJobsCount < 100, // Basic health check
                'checked_at' => now()->toISOString()
            ];

            return [
                'success' => true,
                'data' => $health,
                'metadata' => ['procedure' => 'getQueueHealth']
            ];

        } catch (Exception $e) {
            Log::error('Failed to get queue health', [
                'error' => $e->getMessage(),
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get queue health: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'getQueueHealth']
            ];
        }
    }

    /**
     * Check if circuit is open for a service
     *
     * @param string $serviceName
     * @return bool
     */
    private function isCircuitOpen(string $serviceName): bool
    {
        $middleware = new FuseCircuitBreakerMiddleware($serviceName);
        $stats = $middleware->getStats();
        
        return $stats['state'] === 'open';
    }

    /**
     * Get queue stats for a specific service
     *
     * @param string $serviceName
     * @return array
     */
    private function getServiceQueueStats(string $serviceName): array
    {
        $middleware = new FuseCircuitBreakerMiddleware($serviceName);
        $stats = $middleware->getStats();
        
        // Add queue-specific metrics
        $stats['release_counters'] = $this->getServiceReleaseCounters($serviceName);
        $stats['config'] = config("fuse.services.{$serviceName}", []);
        
        return $stats;
    }

    /**
     * Get stats for a specific queue
     *
     * @param string $queueName
     * @return array
     */
    private function getQueueStats(string $queueName): array
    {
        return [
            'queue' => $queueName,
            'size' => Queue::size($queueName),
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Get stats for all services and queues
     *
     * @return array
     */
    private function getAllQueueStats(): array
    {
        $services = array_keys(config('fuse.services', []));
        $stats = [];
        
        foreach ($services as $service) {
            $stats['services'][$service] = $this->getServiceQueueStats($service);
        }
        
        $stats['queues'] = [
            'default' => $this->getQueueStats('default'),
            'high' => $this->getQueueStats('high'),
            'low' => $this->getQueueStats('low')
        ];
        
        return $stats;
    }

    /**
     * Get release counters for a service
     *
     * @param string $serviceName
     * @return array
     */
    private function getServiceReleaseCounters(string $serviceName): array
    {
        $pattern = "fuse_releases:{$serviceName}:*";
        $keys = Cache::getRedis()->keys($pattern);
        $counters = [];
        
        foreach ($keys as $key) {
            $jobId = str_replace("fuse_releases:{$serviceName}:", '', $key);
            $counters[$jobId] = Cache::get($key, 0);
        }
        
        return $counters;
    }

    /**
     * Clear release counters for a service
     *
     * @param string $serviceName
     * @return void
     */
    private function clearServiceReleaseCounters(string $serviceName): void
    {
        $pattern = "fuse_releases:{$serviceName}:*";
        $keys = Cache::getRedis()->keys($pattern);
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Get failed jobs count
     *
     * @return int
     */
    private function getFailedJobsCount(): int
    {
        try {
            // This would depend on your failed jobs table structure
            return \DB::table('failed_jobs')->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get all circuit breaker states
     *
     * @return array
     */
    private function getAllCircuitStates(): array
    {
        $services = array_keys(config('fuse.services', []));
        $states = [];
        
        foreach ($services as $service) {
            $middleware = new FuseCircuitBreakerMiddleware($service);
            $stats = $middleware->getStats();
            $states[$service] = $stats['state'];
        }
        
        return $states;
    }
}
