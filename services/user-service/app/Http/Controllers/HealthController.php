<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Health check endpoint for monitoring and load balancers.
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'service' => 'auth-service',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ];

        $checks = [];

        // Database connectivity check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'connected';
        } catch (\Exception $e) {
            $checks['database'] = 'disconnected';
            $health['status'] = 'unhealthy';
        }

        // Redis connectivity check
        try {
            Redis::ping();
            $checks['redis'] = 'connected';
        } catch (\Exception $e) {
            $checks['redis'] = 'disconnected';
            $health['status'] = 'unhealthy';
        }

        // Memory usage check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;

        $checks['memory'] = [
            'usage' => $this->formatBytes($memoryUsage),
            'limit' => $this->formatBytes($memoryLimit),
            'percentage' => round($memoryPercentage, 2).'%',
        ];

        if ($memoryPercentage > 90) {
            $health['status'] = 'unhealthy';
        }

        // Disk space check
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsagePercentage = (($diskTotal - $diskFree) / $diskTotal) * 100;

        $checks['disk'] = [
            'free' => $this->formatBytes($diskFree),
            'total' => $this->formatBytes($diskTotal),
            'usage_percentage' => round($diskUsagePercentage, 2).'%',
        ];

        if ($diskUsagePercentage > 90) {
            $health['status'] = 'unhealthy';
        }

        $health['checks'] = $checks;

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    /**
     * Simple health check for load balancers.
     */
    public function up(): JsonResponse
    {
        return response()->json(['status' => 'up'], 200);
    }

    /**
     * Standard health endpoint for service monitoring.
     */
    public function health(): JsonResponse
    {
        return $this->check();
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
     * Parse memory limit string to bytes.
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;

        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }

        return $limit;
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
