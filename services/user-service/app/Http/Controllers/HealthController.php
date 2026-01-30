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
            'percentage' => round($memoryPercentage, 2) . '%'
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
            'usage_percentage' => round($diskUsagePercentage, 2) . '%'
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

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

