<?php

namespace Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint for service monitoring.
     */
    public function health(): JsonResponse
    {
        $checks = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'service' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
            ],
        ];

        // Determine overall health status
        $allHealthy = collect($checks['checks'])->every(fn($check) => $check['status'] === 'healthy');
        $checks['status'] = $allHealthy ? 'healthy' : 'unhealthy';

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json($checks, $statusCode);
    }

    /**
     * Service information endpoint.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'service' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'debug' => config('app.debug'),
            'uptime' => $this->getUptime(),
            'memory_usage' => $this->getMemoryUsage(),
        ]);
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'response_time' => $this->measureResponseTime(fn() => DB::select('SELECT 1')),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'response_time' => null,
            ];
        }
    }

    /**
     * Check cache connectivity.
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            $value = 'test';
            
            cache()->put($key, $value, 60);
            $retrieved = cache()->get($key);
            cache()->forget($key);

            if ($retrieved === $value) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working properly',
                    'driver' => config('cache.default'),
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache read/write test failed',
                    'driver' => config('cache.default'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache error: ' . $e->getMessage(),
                'driver' => config('cache.default'),
            ];
        }
    }

    /**
     * Check storage accessibility.
     */
    private function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'health check test';
            
            \Storage::put($testFile, $testContent);
            $retrieved = \Storage::get($testFile);
            \Storage::delete($testFile);

            if ($retrieved === $testContent) {
                return [
                    'status' => 'healthy',
                    'message' => 'Storage is accessible',
                    'disk' => config('filesystems.default'),
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Storage read/write test failed',
                    'disk' => config('filesystems.default'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage error: ' . $e->getMessage(),
                'disk' => config('filesystems.default'),
            ];
        }
    }

    /**
     * Measure response time for a given operation.
     */
    private function measureResponseTime(callable $operation): float
    {
        $start = microtime(true);
        $operation();
        $end = microtime(true);
        
        return round(($end - $start) * 1000, 2); // Convert to milliseconds
    }

    /**
     * Get application uptime.
     */
    private function getUptime(): array
    {
        $uptime = time() - filemtime(base_path('bootstrap/app.php'));
        
        return [
            'seconds' => $uptime,
            'human' => $this->formatUptime($uptime),
        ];
    }

    /**
     * Format uptime in human-readable format.
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}s";

        return implode(' ', $parts);
    }

    /**
     * Get memory usage information.
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => $this->formatBytes(memory_get_usage(true)),
            'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Format bytes in human-readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
