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
        return response()->json([
            'status' => 'healthy',
            'service' => 'notification-service',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'queue' => $this->checkQueue(),
            ]
        ]);
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
            'version' => config('app.version', '1.0.0'),
        ]);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            $pdo = \DB::connection()->getPdo();
            if ($pdo) {
                // Simple query to verify connection
                \DB::select('SELECT 1');
                return [
                    'status' => 'healthy',
                    'message' => 'Database connection successful'
                ];
            }
            return [
                'status' => 'unhealthy',
                'message' => 'Database PDO connection is null'
            ];
        } catch (\Exception $e) {
            \Log::warning('Database health check failed', ['error' => $e->getMessage()]);
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
            $redis = \Redis::connection();
            $result = $redis->ping();
            if ($result === true || $result === 'PONG') {
                return [
                    'status' => 'healthy',
                    'message' => 'Redis connection successful'
                ];
            }
            return [
                'status' => 'unhealthy',
                'message' => 'Redis ping returned unexpected result: ' . $result
            ];
        } catch (\Exception $e) {
            \Log::warning('Redis health check failed', ['error' => $e->getMessage()]);
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
            \Log::warning('Queue health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'message' => 'Queue check failed: ' . $e->getMessage()
            ];
        }
    }
}
