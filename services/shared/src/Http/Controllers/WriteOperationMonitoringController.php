<?php

namespace Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class WriteOperationMonitoringController extends Controller
{
    /**
     * Get write operation monitoring dashboard data.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'overview' => $this->getOverviewMetrics(),
                'services' => $this->getServiceMetrics(),
                'buffer_status' => $this->getBufferStatus(),
                'recent_operations' => $this->getRecentOperations(),
                'alerts' => $this->getActiveAlerts(),
                'queue_metrics' => $this->getQueueMetrics(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get overview metrics across all services.
     */
    private function getOverviewMetrics(): array
    {
        $services = [
            'order-service',
            'auction-service', 
            'bidding-service',
            'payment-service',
            'auth-service',
            'user-service',
            'notification-service',
            'analytics-service',
            'vin-ocr-service',
            'gateway-service'
        ];

        $totalBuffered = 0;
        $totalReplayed = 0;
        $servicesWithBufferedOps = 0;

        foreach ($services as $service) {
            $buffered = Cache::get("{$service}_buffered_operations_count", 0);
            $replayed = Cache::get("{$service}_replayed_operations_count", 0);
            
            $totalBuffered += $buffered;
            $totalReplayed += $replayed;
            
            if ($buffered > 0) {
                $servicesWithBufferedOps++;
            }
        }

        return [
            'total_buffered_operations' => $totalBuffered,
            'total_replayed_operations' => $totalReplayed,
            'services_with_buffered_operations' => $servicesWithBufferedOps,
            'total_services' => count($services),
            'system_health' => $totalBuffered === 0 ? 'healthy' : ($totalBuffered < 10 ? 'warning' : 'critical'),
        ];
    }

    /**
     * Get per-service metrics.
     */
    private function getServiceMetrics(): array
    {
        $services = [
            'order-service' => ['critical_operations' => ['order_creation', 'status_update']],
            'auction-service' => ['critical_operations' => ['bid_placement', 'auction_creation']],
            'bidding-service' => ['critical_operations' => ['bid_submission', 'bid_evaluation']],
            'payment-service' => ['critical_operations' => ['payment_processing', 'refund_processing']],
            'auth-service' => ['critical_operations' => ['login', 'register', 'password_reset']],
            'user-service' => ['critical_operations' => ['profile_update', 'verification']],
            'notification-service' => ['critical_operations' => ['send_notification']],
            'analytics-service' => ['critical_operations' => []],
            'vin-ocr-service' => ['critical_operations' => ['vin_processing']],
            'gateway-service' => ['critical_operations' => ['request_routing']],
        ];

        $serviceMetrics = [];

        foreach ($services as $serviceName => $config) {
            $bufferedCount = Cache::get("{$serviceName}_buffered_operations_count", 0);
            $replayedCount = Cache::get("{$serviceName}_replayed_operations_count", 0);
            $lastBuffered = Cache::get("{$serviceName}_last_buffered_operation");
            $lastReplayed = Cache::get("{$serviceName}_last_replayed_operation");
            
            // Special handling for payment service
            $financialBuffered = 0;
            if ($serviceName === 'payment-service') {
                $financialBuffered = Cache::get('payment_financial_operations_buffered', 0);
            }

            $serviceMetrics[$serviceName] = [
                'buffered_operations' => $bufferedCount,
                'replayed_operations' => $replayedCount,
                'financial_operations_buffered' => $financialBuffered,
                'last_buffered_at' => $lastBuffered?->toISOString(),
                'last_replayed_at' => $lastReplayed?->toISOString(),
                'critical_operations' => $config['critical_operations'],
                'status' => $this->getServiceStatus($serviceName, $bufferedCount, $financialBuffered),
            ];
        }

        return $serviceMetrics;
    }

    /**
     * Get buffer status from Redis.
     */
    private function getBufferStatus(): array
    {
        try {
            $redis = Redis::connection();
            
            // Get buffer keys
            $bufferKeys = $redis->keys('write_operation_buffer:*');
            $bufferSize = count($bufferKeys);
            
            // Get sample of buffered operations
            $sampleOperations = [];
            $sampleKeys = array_slice($bufferKeys, 0, 10);
            
            foreach ($sampleKeys as $key) {
                $operation = $redis->get($key);
                if ($operation) {
                    $sampleOperations[] = json_decode($operation, true);
                }
            }

            return [
                'total_buffer_size' => $bufferSize,
                'sample_operations' => $sampleOperations,
                'buffer_health' => $bufferSize === 0 ? 'healthy' : ($bufferSize < 50 ? 'warning' : 'critical'),
            ];
        } catch (\Exception $e) {
            return [
                'total_buffer_size' => 0,
                'sample_operations' => [],
                'buffer_health' => 'unknown',
                'error' => 'Could not connect to Redis',
            ];
        }
    }

    /**
     * Get recent operations from logs.
     */
    private function getRecentOperations(): array
    {
        // This would typically query your logging system
        // For now, return cached recent operations
        return Cache::get('recent_write_operations', []);
    }

    /**
     * Get active alerts.
     */
    private function getActiveAlerts(): array
    {
        $alerts = [];
        
        // Check for payment service alerts (most critical)
        $paymentBuffered = Cache::get('payment_buffered_operations_count', 0);
        if ($paymentBuffered > 0) {
            $alerts[] = [
                'service' => 'payment-service',
                'type' => 'critical',
                'message' => "Payment service has {$paymentBuffered} buffered operations",
                'severity' => 'CRITICAL',
                'created_at' => Cache::get('payment_last_buffered_operation')?->toISOString(),
            ];
        }

        // Check for high buffer counts across services
        $services = ['auction-service', 'bidding-service', 'order-service'];
        foreach ($services as $service) {
            $buffered = Cache::get("{$service}_buffered_operations_count", 0);
            if ($buffered > 10) {
                $alerts[] = [
                    'service' => $service,
                    'type' => 'warning',
                    'message' => "{$service} has {$buffered} buffered operations",
                    'severity' => 'WARNING',
                    'created_at' => Cache::get("{$service}_last_buffered_operation")?->toISOString(),
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get service status based on buffered operations.
     */
    private function getServiceStatus(string $serviceName, int $bufferedCount, int $financialBuffered = 0): string
    {
        if ($serviceName === 'payment-service' && ($bufferedCount > 0 || $financialBuffered > 0)) {
            return 'critical';
        }

        if ($bufferedCount === 0) {
            return 'healthy';
        }

        if ($bufferedCount < 5) {
            return 'warning';
        }

        return 'critical';
    }

    /**
     * Get detailed service status.
     */
    public function serviceStatus(string $service): JsonResponse
    {
        $bufferedCount = Cache::get("{$service}_buffered_operations_count", 0);
        $replayedCount = Cache::get("{$service}_replayed_operations_count", 0);
        $lastBuffered = Cache::get("{$service}_last_buffered_operation");
        $lastReplayed = Cache::get("{$service}_last_replayed_operation");

        return response()->json([
            'service' => $service,
            'status' => $this->getServiceStatus($service, $bufferedCount),
            'metrics' => [
                'buffered_operations' => $bufferedCount,
                'replayed_operations' => $replayedCount,
                'last_buffered_at' => $lastBuffered?->toISOString(),
                'last_replayed_at' => $lastReplayed?->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get queue metrics for monitoring dashboard.
     */
    private function getQueueMetrics(): array
    {
        try {
            $queueMetrics = [
                'overview' => [
                    'total_pending_jobs' => 0,
                    'total_failed_jobs' => 0,
                    'total_processed_jobs' => 0,
                    'queue_health' => 'unknown',
                ],
                'queues' => [],
                'workers' => [
                    'active_workers' => 0,
                    'worker_status' => 'unknown',
                ],
                'replay_jobs' => [
                    'pending_replay_jobs' => 0,
                    'urgent_replay_jobs' => 0,
                    'last_replay_scheduled' => null,
                ],
            ];

            // Get Redis queue information
            if (config('queue.default') === 'redis') {
                $redis = Redis::connection();
                
                // Check standard queues
                $standardQueues = ['default', 'high', 'low'];
                $totalPending = 0;
                
                foreach ($standardQueues as $queueName) {
                    $queueKey = config('queue.connections.redis.queue', 'queues:default');
                    if ($queueName !== 'default') {
                        $queueKey = "queues:{$queueName}";
                    }
                    
                    $queueSize = $redis->llen($queueKey);
                    $totalPending += $queueSize;
                    
                    $queueMetrics['queues'][$queueName] = [
                        'pending_jobs' => $queueSize,
                        'status' => $queueSize > 100 ? 'critical' : ($queueSize > 50 ? 'warning' : 'healthy'),
                    ];
                }

                // Check write operation replay queues
                $replayQueues = ['write-operation-replay', 'write-operation-replay-urgent'];
                $totalReplayPending = 0;
                $urgentReplayPending = 0;
                
                foreach ($replayQueues as $queueName) {
                    $queueKey = "queues:{$queueName}";
                    $queueSize = $redis->llen($queueKey);
                    
                    if ($queueName === 'write-operation-replay-urgent') {
                        $urgentReplayPending = $queueSize;
                    }
                    $totalReplayPending += $queueSize;
                    
                    $queueMetrics['queues'][$queueName] = [
                        'pending_jobs' => $queueSize,
                        'status' => $queueSize > 10 ? 'critical' : ($queueSize > 5 ? 'warning' : 'healthy'),
                        'priority' => $queueName === 'write-operation-replay-urgent' ? 'URGENT' : 'normal',
                    ];
                }

                $queueMetrics['overview']['total_pending_jobs'] = $totalPending + $totalReplayPending;
                $queueMetrics['replay_jobs']['pending_replay_jobs'] = $totalReplayPending;
                $queueMetrics['replay_jobs']['urgent_replay_jobs'] = $urgentReplayPending;
                
                // Determine overall queue health
                if ($urgentReplayPending > 0) {
                    $queueMetrics['overview']['queue_health'] = 'critical';
                } elseif ($totalPending > 200 || $totalReplayPending > 20) {
                    $queueMetrics['overview']['queue_health'] = 'warning';
                } else {
                    $queueMetrics['overview']['queue_health'] = 'healthy';
                }
            }

            // Get failed jobs count from database
            try {
                $failedJobsCount = DB::table('failed_jobs')->count();
                $queueMetrics['overview']['total_failed_jobs'] = $failedJobsCount;
                
                // Get recent failed jobs for analysis
                $recentFailedJobs = DB::table('failed_jobs')
                    ->select(['queue', 'failed_at', 'exception'])
                    ->orderBy('failed_at', 'desc')
                    ->limit(5)
                    ->get();
                
                $queueMetrics['recent_failures'] = $recentFailedJobs->map(function ($job) {
                    return [
                        'queue' => $job->queue,
                        'failed_at' => $job->failed_at,
                        'error_type' => $this->extractErrorType($job->exception),
                    ];
                })->toArray();
                
            } catch (\Exception $e) {
                $queueMetrics['overview']['total_failed_jobs'] = 'unknown';
                $queueMetrics['recent_failures'] = [];
            }

            // Check for recent replay job scheduling
            $lastReplayScheduled = Cache::get('last_replay_job_scheduled');
            if ($lastReplayScheduled) {
                $queueMetrics['replay_jobs']['last_replay_scheduled'] = $lastReplayScheduled;
            }

            return $queueMetrics;

        } catch (\Exception $e) {
            return [
                'error' => 'Failed to retrieve queue metrics',
                'message' => $e->getMessage(),
                'overview' => [
                    'queue_health' => 'error',
                ],
            ];
        }
    }

    /**
     * Extract error type from exception string for categorization.
     */
    private function extractErrorType(string $exception): string
    {
        if (str_contains($exception, 'Connection')) {
            return 'connection_error';
        } elseif (str_contains($exception, 'Timeout')) {
            return 'timeout_error';
        } elseif (str_contains($exception, 'Database')) {
            return 'database_error';
        } elseif (str_contains($exception, 'Redis')) {
            return 'redis_error';
        } else {
            return 'unknown_error';
        }
    }
}
