<?php

declare(strict_types=1);

namespace Shared\Health;

use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\QueueManager;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Shared\Health\Enums\HealthStatus;
use Shared\RPC\Contracts\RpcClientInterface;
use Throwable;

/**
 * Health Checker - PHP 8.3 & Laravel 12 Implementation
 * 
 * Comprehensive health monitoring for microservices including:
 * - Database connectivity
 * - Redis connectivity
 * - Queue status
 * - RPC client health
 * - Resource usage monitoring
 */
class HealthChecker
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly RedisManager $redis,
        private readonly QueueManager $queue,
        private readonly array $rpcClients = []
    ) {}

    /**
     * Perform comprehensive health check
     */
    public function check(): array
    {
        $startTime = microtime(true);
        $checks = [];

        // Database health
        $checks['database'] = $this->checkDatabase();
        
        // Redis health
        $checks['redis'] = $this->checkRedis();
        
        // Queue health
        $checks['queue'] = $this->checkQueue();
        
        // RPC clients health
        $checks['rpc_clients'] = $this->checkRpcClients();
        
        // System resources
        $checks['resources'] = $this->checkResources();

        // Calculate overall status
        $statuses = array_column($checks, 'status');
        $overallStatus = HealthStatus::aggregate($statuses);

        $responseTime = (microtime(true) - $startTime) * 1000;

        return [
            'status' => $overallStatus->value,
            'timestamp' => now()->toISOString(),
            'response_time_ms' => round($responseTime, 2),
            'checks' => $checks,
            'summary' => [
                'total_checks' => count($checks),
                'healthy_checks' => count(array_filter($statuses, fn($s) => $s === HealthStatus::HEALTHY)),
                'degraded_checks' => count(array_filter($statuses, fn($s) => $s === HealthStatus::DEGRADED)),
                'unhealthy_checks' => count(array_filter($statuses, fn($s) => $s === HealthStatus::UNHEALTHY)),
            ]
        ];
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabase(): array
    {
        $startTime = microtime(true);
        
        try {
            // Test connection
            $connection = $this->database->connection();
            $pdo = $connection->getPdo();
            
            // Simple query to test responsiveness
            $result = $connection->select('SELECT 1 as test');
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            // Check if response time is acceptable (< 100ms is good, < 500ms is degraded)
            $status = match (true) {
                $responseTime < 100 => HealthStatus::HEALTHY,
                $responseTime < 500 => HealthStatus::DEGRADED,
                default => HealthStatus::UNHEALTHY,
            };

            return [
                'status' => $status,
                'response_time_ms' => round($responseTime, 2),
                'connection_name' => $connection->getName(),
                'driver' => $connection->getDriverName(),
                'details' => [
                    'query_result' => $result[0]->test ?? null,
                    'pdo_available' => $pdo !== null,
                ]
            ];

        } catch (Throwable $e) {
            Log::error('Database health check failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'status' => HealthStatus::UNHEALTHY,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connectivity and performance
     */
    private function checkRedis(): array
    {
        $startTime = microtime(true);
        
        try {
            $redis = $this->redis->connection();
            
            // Test basic operations
            $testKey = 'health_check_' . uniqid();
            $testValue = 'test_' . time();
            
            // Set and get test value
            $redis->set($testKey, $testValue, 'EX', 10); // Expire in 10 seconds
            $retrievedValue = $redis->get($testKey);
            $redis->del($testKey);
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            $isWorking = $retrievedValue === $testValue;
            
            $status = match (true) {
                !$isWorking => HealthStatus::UNHEALTHY,
                $responseTime < 50 => HealthStatus::HEALTHY,
                $responseTime < 200 => HealthStatus::DEGRADED,
                default => HealthStatus::UNHEALTHY,
            };

            return [
                'status' => $status,
                'response_time_ms' => round($responseTime, 2),
                'details' => [
                    'set_get_test' => $isWorking,
                    'connection_name' => $redis->connection()->getName() ?? 'default',
                ]
            ];

        } catch (Throwable $e) {
            Log::error('Redis health check failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'status' => HealthStatus::UNHEALTHY,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue system health
     */
    private function checkQueue(): array
    {
        $startTime = microtime(true);
        
        try {
            $queueManager = $this->queue;
            $connection = $queueManager->connection();
            
            // Get queue size for default queue
            $queueSize = $connection->size();
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            // Determine status based on queue size and response time
            $status = match (true) {
                $queueSize > 10000 => HealthStatus::UNHEALTHY, // Too many pending jobs
                $queueSize > 1000 => HealthStatus::DEGRADED,   // High queue size
                $responseTime > 1000 => HealthStatus::DEGRADED, // Slow response
                default => HealthStatus::HEALTHY,
            };

            return [
                'status' => $status,
                'response_time_ms' => round($responseTime, 2),
                'details' => [
                    'queue_size' => $queueSize,
                    'connection_name' => $connection->getConnectionName(),
                ]
            ];

        } catch (Throwable $e) {
            Log::error('Queue health check failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'status' => HealthStatus::UNHEALTHY,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check RPC clients health
     */
    private function checkRpcClients(): array
    {
        if (empty($this->rpcClients)) {
            return [
                'status' => HealthStatus::UNKNOWN,
                'message' => 'No RPC clients configured',
                'clients' => []
            ];
        }

        $clientResults = [];
        $statuses = [];

        foreach ($this->rpcClients as $name => $client) {
            if (!$client instanceof RpcClientInterface) {
                continue;
            }

            $startTime = microtime(true);
            
            try {
                $response = $client->healthCheck();
                $responseTime = (microtime(true) - $startTime) * 1000;
                
                $status = $response->isSuccessful() 
                    ? HealthStatus::HEALTHY 
                    : HealthStatus::UNHEALTHY;

                $clientResults[$name] = [
                    'status' => $status,
                    'response_time_ms' => round($responseTime, 2),
                    'service_type' => $client->getServiceType()->value,
                    'success' => $response->success,
                ];

                $statuses[] = $status;

            } catch (Throwable $e) {
                $responseTime = (microtime(true) - $startTime) * 1000;
                
                $clientResults[$name] = [
                    'status' => HealthStatus::UNHEALTHY,
                    'response_time_ms' => round($responseTime, 2),
                    'service_type' => $client->getServiceType()->value,
                    'error' => $e->getMessage(),
                ];

                $statuses[] = HealthStatus::UNHEALTHY;
            }
        }

        $overallStatus = HealthStatus::aggregate($statuses);

        return [
            'status' => $overallStatus,
            'clients' => $clientResults,
            'summary' => [
                'total_clients' => count($clientResults),
                'healthy_clients' => count(array_filter($statuses, fn($s) => $s === HealthStatus::HEALTHY)),
                'unhealthy_clients' => count(array_filter($statuses, fn($s) => $s === HealthStatus::UNHEALTHY)),
            ]
        ];
    }

    /**
     * Check system resources
     */
    private function checkResources(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

            $diskUsage = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskPercent = $diskTotal > 0 ? (($diskTotal - $diskUsage) / $diskTotal) * 100 : 0;

            // Determine status based on resource usage
            $status = match (true) {
                $memoryPercent > 90 || $diskPercent > 95 => HealthStatus::UNHEALTHY,
                $memoryPercent > 80 || $diskPercent > 85 => HealthStatus::DEGRADED,
                default => HealthStatus::HEALTHY,
            };

            return [
                'status' => $status,
                'details' => [
                    'memory' => [
                        'used_bytes' => $memoryUsage,
                        'used_mb' => round($memoryUsage / 1024 / 1024, 2),
                        'limit_bytes' => $memoryLimit,
                        'usage_percent' => round($memoryPercent, 2),
                    ],
                    'disk' => [
                        'free_bytes' => $diskUsage,
                        'total_bytes' => $diskTotal,
                        'used_percent' => round($diskPercent, 2),
                    ],
                ]
            ];

        } catch (Throwable $e) {
            return [
                'status' => HealthStatus::UNKNOWN,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return 0; // No limit
        }

        $limit = trim($limit);
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $limit,
        };
    }

    /**
     * Add RPC client for health monitoring
     */
    public function addRpcClient(string $name, RpcClientInterface $client): void
    {
        $this->rpcClients[$name] = $client;
    }
}
