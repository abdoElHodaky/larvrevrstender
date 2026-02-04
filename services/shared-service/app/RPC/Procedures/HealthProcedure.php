<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthProcedure extends BaseProcedure
{
    /**
     * Basic health ping
     * 
     * @return array
     */
    public function ping(): array
    {
        return $this->executeWithLogging('Health@ping', [], function () {
            return [
                'status' => 'healthy',
                'service' => 'shared-service',
                'version' => config('app.version', '1.0.0'),
                'timestamp' => now()->toISOString(),
                'octane_enabled' => config('octane.server') !== null,
                'rpc_enabled' => true,
            ];
        });
    }

    /**
     * Comprehensive health check
     * 
     * @return array
     */
    public function check(): array
    {
        return $this->executeWithLogging('Health@check', [], function () {
            $checks = [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'cache' => $this->checkCache(),
                'memory' => $this->checkMemory(),
                'disk' => $this->checkDisk(),
                'octane' => $this->checkOctane(),
            ];

            $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'healthy')
                ? 'healthy'
                : 'degraded';

            return [
                'status' => $overallStatus,
                'checks' => $checks,
                'timestamp' => now()->toISOString(),
                'uptime' => $this->getUptime(),
                'service_info' => $this->getServiceInfo(),
            ];
        });
    }

    /**
     * Get detailed system metrics
     * 
     * @return array
     */
    public function metrics(): array
    {
        return $this->executeWithLogging('Health@metrics', [], function () {
            return [
                'memory' => [
                    'current_usage' => memory_get_usage(true),
                    'peak_usage' => memory_get_peak_usage(true),
                    'limit' => $this->parseBytes(ini_get('memory_limit')),
                    'formatted' => [
                        'current' => $this->formatBytes(memory_get_usage(true)),
                        'peak' => $this->formatBytes(memory_get_peak_usage(true)),
                        'limit' => $this->formatBytes($this->parseBytes(ini_get('memory_limit'))),
                    ],
                ],
                'system' => [
                    'load_average' => sys_getloadavg(),
                    'cpu_count' => $this->getCpuCount(),
                    'disk_usage' => $this->getDiskUsage(),
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'extensions' => get_loaded_extensions(),
                ],
                'laravel' => [
                    'version' => app()->version(),
                    'environment' => config('app.env'),
                    'debug' => config('app.debug'),
                ],
                'octane' => [
                    'enabled' => config('octane.server') !== null,
                    'server' => config('octane.server'),
                    'workers' => config('octane.workers'),
                    'max_requests' => config('octane.max_requests'),
                ],
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Check database connectivity
     * 
     * @return array
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'response_time_ms' => $responseTime,
                'driver' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'driver' => config('database.default'),
            ];
        }
    }

    /**
     * Check Redis connectivity
     * 
     * @return array
     */
    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache functionality
     * 
     * @return array
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_' . uniqid();
            
            $start = microtime(true);
            Cache::put($testKey, $testValue, 10);
            $retrievedValue = Cache::get($testKey);
            Cache::forget($testKey);
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            return $retrievedValue === $testValue 
                ? [
                    'status' => 'healthy',
                    'message' => 'Cache read/write successful',
                    'response_time_ms' => $responseTime,
                    'driver' => config('cache.default'),
                ]
                : [
                    'status' => 'unhealthy',
                    'message' => 'Cache read/write failed',
                    'driver' => config('cache.default'),
                ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }
    }

    /**
     * Check memory usage
     * 
     * @return array
     */
    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $percentage = ($memoryUsage / $memoryLimit) * 100;

        return [
            'status' => $percentage < 80 ? 'healthy' : ($percentage < 90 ? 'warning' : 'critical'),
            'usage' => $this->formatBytes($memoryUsage),
            'limit' => $this->formatBytes($memoryLimit),
            'percentage' => round($percentage, 2),
            'peak_usage' => $this->formatBytes(memory_get_peak_usage(true)),
        ];
    }

    /**
     * Check disk usage
     * 
     * @return array
     */
    private function checkDisk(): array
    {
        $freeBytes = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        $usedBytes = $totalBytes - $freeBytes;
        $percentage = ($usedBytes / $totalBytes) * 100;

        return [
            'status' => $percentage < 85 ? 'healthy' : ($percentage < 95 ? 'warning' : 'critical'),
            'free' => $this->formatBytes($freeBytes),
            'used' => $this->formatBytes($usedBytes),
            'total' => $this->formatBytes($totalBytes),
            'percentage' => round($percentage, 2),
        ];
    }

    /**
     * Check Octane status
     * 
     * @return array
     */
    private function checkOctane(): array
    {
        $octaneEnabled = config('octane.server') !== null;
        $workers = config('octane.workers', 0);
        
        return [
            'status' => $octaneEnabled ? 'healthy' : 'disabled',
            'enabled' => $octaneEnabled,
            'server' => config('octane.server', 'none'),
            'workers' => $workers,
            'task_workers' => config('octane.task_workers', 0),
            'max_requests' => config('octane.max_requests', 0),
            'rpc_port' => config('octane.rpc.port', null),
        ];
    }

    /**
     * Get system uptime
     * 
     * @return string
     */
    private function getUptime(): string
    {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $seconds = (int) explode(' ', $uptime)[0];
            return gmdate('H:i:s', $seconds);
        }
        
        return 'unknown';
    }

    /**
     * Get CPU count
     * 
     * @return int
     */
    private function getCpuCount(): int
    {
        if (function_exists('shell_exec')) {
            $cpuCount = shell_exec('nproc');
            return $cpuCount ? (int) trim($cpuCount) : 1;
        }
        
        return 1;
    }

    /**
     * Get disk usage information
     * 
     * @return array
     */
    private function getDiskUsage(): array
    {
        $paths = ['/', '/tmp', '/var/log'];
        $usage = [];
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $free = disk_free_space($path);
                $total = disk_total_space($path);
                $used = $total - $free;
                
                $usage[$path] = [
                    'free' => $this->formatBytes($free),
                    'used' => $this->formatBytes($used),
                    'total' => $this->formatBytes($total),
                    'percentage' => round(($used / $total) * 100, 2),
                ];
            }
        }
        
        return $usage;
    }

    /**
     * Parse bytes from string
     * 
     * @param string $size
     * @return int
     */
    private function parseBytes(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }

    /**
     * Format bytes to human readable
     * 
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
