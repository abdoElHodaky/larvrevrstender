<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use Shared\Traits\RpcMonitoring;
use Shared\Enums\RpcMethodType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Health Check Procedure (PHP 8.3 + Laravel 12)
 * 
 * Provides comprehensive service health monitoring
 */
class HealthCheckProcedure extends BaseProcedure
{
    use RpcMonitoring;

    /**
     * Basic health check
     */
    public function ping(array $params = []): array
    {
        return $this->withMonitoring(__METHOD__, RpcMethodType::HEALTH_CHECK, $params, function() {
            return [
                'status' => 'healthy',
                'service' => 'analytics-service',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0')
            ];
        });
    }

    /**
     * Comprehensive health check with dependencies
     */
    public function health(array $params = []): array
    {
        return $this->withMonitoring(__METHOD__, RpcMethodType::HEALTH_CHECK, $params, function() {
            $checks = [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'memory' => $this->checkMemory(),
                'dependencies' => $this->checkDependencies()
            ];

            $overallStatus = $this->calculateOverallStatus($checks);

            return [
                'status' => $overallStatus,
                'service' => 'analytics-service',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
                'checks' => $checks,
                'uptime' => $this->getUptime(),
                'environment' => config('app.env')
            ];
        });
    }

    /**
     * Get service metrics
     */
    public function metrics(array $params = []): array
    {
        return $this->withMonitoring(__METHOD__, RpcMethodType::SYSTEM, $params, function() {
            return [
                'memory_usage' => [
                    'current' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                    'limit' => ini_get('memory_limit')
                ],
                'system_load' => sys_getloadavg(),
                'disk_usage' => $this->getDiskUsage(),
                'database_connections' => $this->getDatabaseConnections(),
                'cache_stats' => $this->getCacheStats(),
                'request_stats' => $this->getRequestStats()
            ];
        });
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;

            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
                'connection' => DB::connection()->getName()
            ];
        } catch (\Exception $e) {
            Log::error('Database health check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'response_time_ms' => null
            ];
        }
    }

    /**
     * Check cache connectivity
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $testKey = 'health_check_' . time();
            
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            
            $responseTime = (microtime(true) - $start) * 1000;

            return [
                'status' => $value === 'test' ? 'healthy' : 'degraded',
                'response_time_ms' => round($responseTime, 2),
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            Log::error('Cache health check failed', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'response_time_ms' => null
            ];
        }
    }

    /**
     * Check storage accessibility
     */
    private function checkStorage(): array
    {
        try {
            $testFile = storage_path('logs/health_check_' . time() . '.tmp');
            file_put_contents($testFile, 'test');
            $readable = file_get_contents($testFile) === 'test';
            unlink($testFile);

            return [
                'status' => $readable ? 'healthy' : 'degraded',
                'writable' => is_writable(storage_path('logs')),
                'free_space' => disk_free_space(storage_path())
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'writable' => false
            ];
        }
    }

    /**
     * Check memory usage
     */
    private function checkMemory(): array
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usage_percentage = $limit > 0 ? ($current / $limit) * 100 : 0;
        
        $status = match(true) {
            $usage_percentage > 90 => 'critical',
            $usage_percentage > 75 => 'warning',
            default => 'healthy'
        };

        return [
            'status' => $status,
            'current_bytes' => $current,
            'peak_bytes' => $peak,
            'limit_bytes' => $limit,
            'usage_percentage' => round($usage_percentage, 2)
        ];
    }

    /**
     * Check external dependencies
     */
    private function checkDependencies(): array
    {
        $dependencies = [];
        
        // Check other services if configured
        $services = ['user-service', 'order-service', 'payment-service'];
        
        foreach ($services as $service) {
            $url = config("rpc.services.{$service}.url");
            if ($url) {
                $dependencies[$service] = $this->checkServiceHealth($url);
            }
        }

        return $dependencies;
    }

    /**
     * Check external service health
     */
    private function checkServiceHealth(string $url): array
    {
        try {
            $start = microtime(true);
            $response = file_get_contents($url . '/health', false, stream_context_create([
                'http' => ['timeout' => 5]
            ]));
            $responseTime = (microtime(true) - $start) * 1000;

            return [
                'status' => $response ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($responseTime, 2)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'response_time_ms' => null
            ];
        }
    }

    /**
     * Calculate overall status from individual checks
     */
    private function calculateOverallStatus(array $checks): string
    {
        $statuses = collect($checks)->flatten()->filter(fn($item) => 
            is_array($item) && isset($item['status'])
        )->pluck('status');

        return match(true) {
            $statuses->contains('critical') => 'critical',
            $statuses->contains('unhealthy') => 'unhealthy',
            $statuses->contains('degraded') => 'degraded',
            $statuses->contains('warning') => 'warning',
            default => 'healthy'
        };
    }

    /**
     * Get service uptime
     */
    private function getUptime(): array
    {
        $uptime = file_get_contents('/proc/uptime');
        $uptimeSeconds = (float) explode(' ', $uptime)[0];

        return [
            'seconds' => $uptimeSeconds,
            'human' => $this->formatUptime($uptimeSeconds)
        ];
    }

    /**
     * Format uptime in human readable format
     */
    private function formatUptime(float $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }

    /**
     * Get disk usage information
     */
    private function getDiskUsage(): array
    {
        $path = storage_path();
        
        return [
            'total' => disk_total_space($path),
            'free' => disk_free_space($path),
            'used' => disk_total_space($path) - disk_free_space($path)
        ];
    }

    /**
     * Get database connection information
     */
    private function getDatabaseConnections(): array
    {
        try {
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return [
                'active' => $connections[0]->Value ?? 0,
                'max' => ini_get('mysql.max_connections') ?: 'unknown'
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get cache statistics
     */
    private function getCacheStats(): array
    {
        try {
            // This would depend on your cache driver
            return [
                'driver' => config('cache.default'),
                'status' => 'available'
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get request statistics
     */
    private function getRequestStats(): array
    {
        // This would typically come from your monitoring system
        return [
            'total_requests' => 'N/A',
            'requests_per_minute' => 'N/A',
            'average_response_time' => 'N/A'
        ];
    }
}
