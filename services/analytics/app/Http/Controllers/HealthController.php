<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Shared\Health\HealthChecker;
use Shared\Health\Enums\HealthStatus;

/**
 * Modern Health Controller - PHP 8.3 & Laravel 12 Implementation
 * 
 * Uses the new comprehensive health checking system
 * with standardized status reporting and monitoring.
 */
class HealthController extends Controller
{
    public function __construct(
        private readonly HealthChecker $healthChecker
    ) {}

    /**
     * Comprehensive health check endpoint for monitoring and load balancers
     */
    public function check(): JsonResponse
    {
        $healthData = $this->healthChecker->check();
        
        // Add service-specific metadata
        $healthData['service'] = 'analytics-service';
        $healthData['version'] = config('app.version', '1.0.0');
        $healthData['environment'] = config('app.env');

        $status = HealthStatus::from($healthData['status']);
        $statusCode = $status->getHttpStatusCode();

        return response()->json($healthData, $statusCode);
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

        return round($bytes, $precision).' '.$units[$i];
    }
}
