<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use App\Models\Job;
use App\Models\FailedJob;

/**
 * Notification Queue Optimization Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Optimizes notification queues by cleaning up failed jobs, rebalancing workloads,
 * and managing queue health. Critical for maintaining notification delivery
 * performance and preventing queue bottlenecks across all channels.
 */
class OptimizeNotificationQueuesJob extends BaseQueueJob
{
    public array $queueNames;
    public array $optimizationTypes;
    public array $optimizationOptions;
    public int $tries = 3;
    public int $timeout = 1200; // 20 minutes for queue optimization

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $queueNames = [],
        array $optimizationTypes = [],
        array $optimizationOptions = []
    ) {
        parent::__construct();
        
        $this->queueNames = $queueNames ?: $this->getDefaultQueueNames();
        $this->optimizationTypes = $optimizationTypes ?: $this->getDefaultOptimizationTypes();
        $this->optimizationOptions = array_merge($this->getDefaultOptimizationOptions(), $optimizationOptions);
        
        // Set queue for optimization operations
        $this->onQueue('queue-optimization');
        
        // Configure circuit breaker for queue optimization
        $this->configureCircuitBreaker([
            'service_name' => 'notification_queue_optimization',
            'failure_threshold' => 35, // 35% failure rate triggers circuit breaker
            'timeout' => 600, // 10 minutes timeout for optimization operations
            'recovery_timeout' => 900, // 15 minutes before attempting recovery
            'tags' => [
                'service' => 'notification-service',
                'job_type' => 'optimization',
                'operation' => 'queue_optimization',
                'priority' => 'medium'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(): void
    {
        Log::info('Starting notification queue optimization with circuit breaker protection', [
            'queue_names' => $this->queueNames,
            'optimization_types' => $this->optimizationTypes,
            'optimization_options' => $this->optimizationOptions,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'notification_queue_optimization'
        ]);

        $this->executeWithCircuitBreaker(function() {
            $results = [
                'queues_processed' => 0,
                'jobs_cleaned' => 0,
                'jobs_requeued' => 0,
                'jobs_failed_permanently' => 0,
                'queue_health_improved' => 0,
                'processing_time_ms' => 0,
                'errors' => []
            ];

            $startTime = microtime(true);

            foreach ($this->queueNames as $queueName) {
                try {
                    $queueResult = $this->optimizeQueue($queueName);
                    
                    $results['queues_processed']++;
                    $results['jobs_cleaned'] += $queueResult['jobs_cleaned'];
                    $results['jobs_requeued'] += $queueResult['jobs_requeued'];
                    $results['jobs_failed_permanently'] += $queueResult['jobs_failed_permanently'];
                    
                    if ($queueResult['health_improved']) {
                        $results['queue_health_improved']++;
                    }
                    
                    Log::debug('Queue optimized successfully', [
                        'queue_name' => $queueName,
                        'jobs_cleaned' => $queueResult['jobs_cleaned'],
                        'health_improved' => $queueResult['health_improved']
                    ]);
                    
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'queue_name' => $queueName,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Failed to optimize queue', [
                        'queue_name' => $queueName,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $results['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);

            Log::info('Notification queue optimization completed successfully', [
                'queues_processed' => $results['queues_processed'],
                'total_jobs_cleaned' => $results['jobs_cleaned'],
                'total_jobs_requeued' => $results['jobs_requeued'],
                'queues_health_improved' => $results['queue_health_improved'],
                'processing_time_ms' => $results['processing_time_ms'],
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            Log::error('Notification queue optimization failed with circuit breaker protection', [
                'queue_names' => $this->queueNames,
                'optimization_types' => $this->optimizationTypes,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Optimize a specific queue
     */
    private function optimizeQueue(string $queueName): array
    {
        $jobsCleaned = 0;
        $jobsRequeued = 0;
        $jobsFailedPermanently = 0;
        $healthImproved = false;

        Log::debug('Starting queue optimization', [
            'queue_name' => $queueName,
            'optimization_types' => $this->optimizationTypes
        ]);

        // Get queue health metrics before optimization
        $healthBefore = $this->getQueueHealth($queueName);

        foreach ($this->optimizationTypes as $optimizationType) {
            $result = $this->performOptimizationType($queueName, $optimizationType);
            
            $jobsCleaned += $result['jobs_cleaned'];
            $jobsRequeued += $result['jobs_requeued'];
            $jobsFailedPermanently += $result['jobs_failed_permanently'];
        }

        // Get queue health metrics after optimization
        $healthAfter = $this->getQueueHealth($queueName);
        $healthImproved = $healthAfter['score'] > $healthBefore['score'];

        Log::info('Queue optimization completed', [
            'queue_name' => $queueName,
            'jobs_cleaned' => $jobsCleaned,
            'jobs_requeued' => $jobsRequeued,
            'health_before' => $healthBefore['score'],
            'health_after' => $healthAfter['score'],
            'health_improved' => $healthImproved
        ]);

        return [
            'jobs_cleaned' => $jobsCleaned,
            'jobs_requeued' => $jobsRequeued,
            'jobs_failed_permanently' => $jobsFailedPermanently,
            'health_improved' => $healthImproved,
            'health_before' => $healthBefore,
            'health_after' => $healthAfter
        ];
    }

    /**
     * Perform specific optimization type
     */
    private function performOptimizationType(string $queueName, string $optimizationType): array
    {
        return match ($optimizationType) {
            'cleanup_failed_jobs' => $this->cleanupFailedJobs($queueName),
            'requeue_stuck_jobs' => $this->requeueStuckJobs($queueName),
            'remove_duplicate_jobs' => $this->removeDuplicateJobs($queueName),
            'optimize_job_priorities' => $this->optimizeJobPriorities($queueName),
            'balance_queue_load' => $this->balanceQueueLoad($queueName),
            'cleanup_expired_jobs' => $this->cleanupExpiredJobs($queueName),
            default => throw new \InvalidArgumentException("Unknown optimization type: {$optimizationType}")
        };
    }

    /**
     * Cleanup failed jobs that have exceeded retry limits
     */
    private function cleanupFailedJobs(string $queueName): array
    {
        $jobsCleaned = 0;
        $jobsFailedPermanently = 0;

        // Get failed jobs older than threshold using Eloquent (Laravel 12)
        $failedJobs = FailedJob::inQueue($queueName)
            ->olderThan($this->optimizationOptions['failed_job_cleanup_hours'])
            ->get();

        foreach ($failedJobs as $failedJob) {
            try {
                // Check if job should be retried or permanently failed using Eloquent attributes (Laravel 12)
                if ($failedJob->has_exceeded_max_attempts) {
                    // Permanently fail the job using Eloquent delete (Laravel 12)
                    $failedJob->delete();
                    $jobsFailedPermanently++;
                    
                    Log::debug('Permanently failed job removed', [
                        'job_id' => $failedJob->id,
                        'queue' => $queueName,
                        'attempts' => $failedJob->attempts,
                        'max_attempts' => $failedJob->max_attempts
                    ]);
                } else {
                    // Clean up old failed job record using Eloquent delete (Laravel 12)
                    $failedJob->delete();
                    $jobsCleaned++;
                }

            } catch (\Exception $e) {
                Log::warning('Failed to process failed job', [
                    'job_id' => $failedJob->id,
                    'queue' => $queueName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'jobs_cleaned' => $jobsCleaned,
            'jobs_requeued' => 0,
            'jobs_failed_permanently' => $jobsFailedPermanently
        ];
    }

    /**
     * Requeue jobs that appear to be stuck
     */
    private function requeueStuckJobs(string $queueName): array
    {
        $jobsRequeued = 0;

        // Get jobs that have been processing for too long using Eloquent (Laravel 12)
        $stuckJobs = Job::inQueue($queueName)
            ->stuck($this->optimizationOptions['stuck_job_timeout_minutes'])
            ->get();

        foreach ($stuckJobs as $stuckJob) {
            try {
                // Reset the job to be available again using Eloquent update (Laravel 12)
                $stuckJob->update([
                    'reserved_at' => null,
                    'attempts' => $stuckJob->attempts + 1,
                    'available_at' => now()->timestamp
                ]);

                $jobsRequeued++;
                
                Log::debug('Requeued stuck job', [
                    'job_id' => $stuckJob->id,
                    'queue' => $queueName,
                    'stuck_duration_minutes' => now()->diffInMinutes(Carbon::createFromTimestamp($stuckJob->reserved_at))
                ]);

            } catch (\Exception $e) {
                Log::warning('Failed to requeue stuck job', [
                    'job_id' => $stuckJob->id,
                    'queue' => $queueName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'jobs_cleaned' => 0,
            'jobs_requeued' => $jobsRequeued,
            'jobs_failed_permanently' => 0
        ];
    }

    /**
     * Remove duplicate jobs from the queue
     */
    private function removeDuplicateJobs(string $queueName): array
    {
        $jobsCleaned = 0;

        // Find duplicate jobs based on payload hash using Eloquent (Laravel 12)
        $duplicateJobs = Job::selectRaw('payload, COUNT(*) as count, MIN(id) as keep_id')
            ->inQueue($queueName)
            ->groupBy('payload')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateJobs as $duplicate) {
            try {
                // Delete all duplicates except the first one using Eloquent (Laravel 12)
                $deletedCount = Job::inQueue($queueName)
                    ->where('payload', $duplicate->payload)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();

                $jobsCleaned += $deletedCount;
                
                Log::debug('Removed duplicate jobs', [
                    'queue' => $queueName,
                    'duplicates_removed' => $deletedCount,
                    'kept_job_id' => $duplicate->keep_id
                ]);

            } catch (\Exception $e) {
                Log::warning('Failed to remove duplicate jobs', [
                    'queue' => $queueName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'jobs_cleaned' => $jobsCleaned,
            'jobs_requeued' => 0,
            'jobs_failed_permanently' => 0
        ];
    }

    /**
     * Optimize job priorities based on importance and age
     */
    private function optimizeJobPriorities(string $queueName): array
    {
        $jobsOptimized = 0;

        // Get jobs that need priority adjustment using Eloquent (Laravel 12)
        $jobs = Job::inQueue($queueName)
            ->available()
            ->orderBy('created_at')
            ->get();

        foreach ($jobs as $job) {
            try {
                // Use Eloquent attributes for job class and age (Laravel 12)
                $jobClass = $job->job_class;
                $jobAge = $job->age_in_hours;
                
                // Calculate new priority based on job type and age
                $newPriority = $this->calculateJobPriority($jobClass, $jobAge);
                
                if ($newPriority !== null && $newPriority != $job->priority) {
                    // Update priority using Eloquent update (Laravel 12)
                    $job->update(['priority' => $newPriority]);
                    
                    $jobsOptimized++;
                }

            } catch (\Exception $e) {
                Log::warning('Failed to optimize job priority', [
                    'job_id' => $job->id,
                    'queue' => $queueName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'jobs_cleaned' => $jobsOptimized,
            'jobs_requeued' => 0,
            'jobs_failed_permanently' => 0
        ];
    }

    /**
     * Balance load across queue workers
     */
    private function balanceQueueLoad(string $queueName): array
    {
        $jobsRebalanced = 0;

        // This would typically involve moving jobs between queue partitions
        // For now, we'll implement a simple rebalancing by updating available_at times
        
        $queueSize = Job::inQueue($queueName)->count();
        $targetBatchSize = $this->optimizationOptions['rebalance_batch_size'];
        
        if ($queueSize > $targetBatchSize * 2) {
            // Spread out job execution times to prevent thundering herd using Eloquent (Laravel 12)
            $jobs = Job::inQueue($queueName)
                ->available()
                ->limit($queueSize - $targetBatchSize)
                ->get();

            foreach ($jobs as $index => $job) {
                try {
                    $newAvailableAt = now()->addSeconds($index * 5)->timestamp; // 5 second intervals
                    
                    // Update available_at using Eloquent update (Laravel 12)
                    $job->update(['available_at' => $newAvailableAt]);
                    
                    $jobsRebalanced++;

                } catch (\Exception $e) {
                    Log::warning('Failed to rebalance job', [
                        'job_id' => $job->id,
                        'queue' => $queueName,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return [
            'jobs_cleaned' => $jobsRebalanced,
            'jobs_requeued' => 0,
            'jobs_failed_permanently' => 0
        ];
    }

    /**
     * Cleanup expired jobs that are no longer relevant
     */
    private function cleanupExpiredJobs(string $queueName): array
    {
        $jobsCleaned = 0;

        // Get jobs older than expiration threshold using Eloquent (Laravel 12)
        $expiredJobs = Job::inQueue($queueName)
            ->expired($this->optimizationOptions['job_expiration_hours'])
            ->get();

        foreach ($expiredJobs as $expiredJob) {
            try {
                // Use Eloquent attribute for job class (Laravel 12)
                $jobClass = $expiredJob->job_class;
                
                // Check if this job type can be safely expired
                if ($this->canJobExpire($jobClass)) {
                    // Delete expired job using Eloquent delete (Laravel 12)
                    $expiredJob->delete();
                    $jobsCleaned++;
                    
                    Log::debug('Expired job removed', [
                        'job_id' => $expiredJob->id,
                        'queue' => $queueName,
                        'job_class' => $jobClass,
                        'age_hours' => $expiredJob->age_in_hours
                    ]);
                }

            } catch (\Exception $e) {
                Log::warning('Failed to cleanup expired job', [
                    'job_id' => $expiredJob->id,
                    'queue' => $queueName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'jobs_cleaned' => $jobsCleaned,
            'jobs_requeued' => 0,
            'jobs_failed_permanently' => 0
        ];
    }

    /**
     * Calculate job priority based on class and age
     */
    private function calculateJobPriority(string $jobClass, int $ageHours): ?int
    {
        // High priority jobs (lower number = higher priority)
        $highPriorityJobs = [
            'SendEmailNotificationJob' => 1,
            'SendSMSNotificationJob' => 1,
            'SendPushNotificationJob' => 2
        ];

        // Medium priority jobs
        $mediumPriorityJobs = [
            'SendBulkEmailJob' => 5,
            'ProcessNotificationTemplateJob' => 5
        ];

        // Low priority jobs
        $lowPriorityJobs = [
            'SendMarketingEmailJob' => 10,
            'GenerateNotificationReportJob' => 10
        ];

        $basePriority = null;
        
        if (isset($highPriorityJobs[$jobClass])) {
            $basePriority = $highPriorityJobs[$jobClass];
        } elseif (isset($mediumPriorityJobs[$jobClass])) {
            $basePriority = $mediumPriorityJobs[$jobClass];
        } elseif (isset($lowPriorityJobs[$jobClass])) {
            $basePriority = $lowPriorityJobs[$jobClass];
        }

        // Adjust priority based on age (older jobs get higher priority)
        if ($basePriority !== null && $ageHours > 1) {
            $basePriority = max(1, $basePriority - floor($ageHours / 6)); // Increase priority every 6 hours
        }

        return $basePriority;
    }

    /**
     * Check if a job type can safely expire
     */
    private function canJobExpire(string $jobClass): bool
    {
        $expirableJobs = [
            'SendMarketingEmailJob',
            'GenerateNotificationReportJob',
            'ProcessNotificationAnalyticsJob'
        ];

        return in_array($jobClass, $expirableJobs);
    }

    /**
     * Get queue health metrics
     */
    private function getQueueHealth(string $queueName): array
    {
        // Use Eloquent models for queue health monitoring (Laravel 12)
        $totalJobs = Job::inQueue($queueName)->count();
        $failedJobs = FailedJob::inQueue($queueName)->count();
        $stuckJobs = Job::inQueue($queueName)->stuck(30)->count();
        $oldJobs = Job::inQueue($queueName)->expired(24)->count();

        // Calculate health score (0-100, higher is better)
        $healthScore = 100;
        
        if ($totalJobs > 0) {
            $healthScore -= ($failedJobs / $totalJobs) * 30; // Failed jobs impact
            $healthScore -= ($stuckJobs / $totalJobs) * 25; // Stuck jobs impact
            $healthScore -= ($oldJobs / $totalJobs) * 20; // Old jobs impact
        }

        $healthScore = max(0, min(100, $healthScore));

        return [
            'score' => round($healthScore, 2),
            'total_jobs' => $totalJobs,
            'failed_jobs' => $failedJobs,
            'stuck_jobs' => $stuckJobs,
            'old_jobs' => $oldJobs
        ];
    }

    /**
     * Get default queue names
     */
    private function getDefaultQueueNames(): array
    {
        return [
            'notifications',
            'emails',
            'sms',
            'push-notifications',
            'webhooks'
        ];
    }

    /**
     * Get default optimization types
     */
    private function getDefaultOptimizationTypes(): array
    {
        return [
            'cleanup_failed_jobs',
            'requeue_stuck_jobs',
            'remove_duplicate_jobs',
            'cleanup_expired_jobs'
        ];
    }

    /**
     * Get default optimization options
     */
    private function getDefaultOptimizationOptions(): array
    {
        return [
            'failed_job_cleanup_hours' => 24,
            'stuck_job_timeout_minutes' => 30,
            'job_expiration_hours' => 72,
            'rebalance_batch_size' => 100,
            'max_queue_size' => 10000,
            'priority_adjustment_enabled' => true
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification queue optimization job failed permanently', [
            'queue_names' => $this->queueNames,
            'optimization_types' => $this->optimizationTypes,
            'optimization_options' => $this->optimizationOptions,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\Notifications\QueueOptimizationFailed(...));
    }
}
