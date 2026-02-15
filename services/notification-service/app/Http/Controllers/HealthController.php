<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     */
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];
        
        // Determine overall status - service is healthy if at least basic functionality works
        $overallStatus = 'healthy';
        $criticalFailures = 0;
        
        foreach ($checks as $check) {
            if ($check['status'] === 'unhealthy') {
                $criticalFailures++;
            }
        }
        
        // Service is unhealthy only if all critical systems fail
        if ($criticalFailures >= 3) {
            $overallStatus = 'unhealthy';
        } elseif ($criticalFailures > 0) {
            $overallStatus = 'degraded';
        }
        
        $statusCode = $overallStatus === 'unhealthy' ? 503 : 200;
        
        return response()->json([
            'status' => $overallStatus,
            'service' => 'notification-service',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks
        ], $statusCode);
    }

    /**
     * Simple up check - doesn't depend on external services
     */
    public function up(): JsonResponse
    {
        return response()->json([
            'status' => 'up',
            'service' => 'notification-service',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            // For SQLite, ensure the database file exists
            if (config('database.default') === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if (!file_exists($dbPath)) {
                    // Create the database file
                    touch($dbPath);
                }
            }
            
            \DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check Redis connectivity
     */
    private function checkRedis(): array
    {
        try {
            // Use Laravel's Redis facade which handles connection better
            \Illuminate\Support\Facades\Redis::ping();
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check queue connectivity
     */
    private function checkQueue(): array
    {
        try {
            $queueSize = \Queue::size();
            return [
                'status' => 'healthy',
                'message' => 'Queue is accessible',
                'queue_size' => $queueSize
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue check failed: ' . $e->getMessage()
            ];
        }
    }
}
