<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\HealthCheck\ServiceDiscoveryHealth;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| These routes provide comprehensive health checking for blue-green
| deployment compatibility and service discovery validation.
|
*/

Route::get('/health', function () {
    $shutdownInProgress = Cache::get('shutdown_in_progress', false);
    $healthStatus = Cache::get('health_check_status', 'healthy');
    
    if ($shutdownInProgress || $healthStatus === 'shutting_down') {
        return response()->json([
            'status' => 'shutting_down',
            'message' => 'Service is gracefully shutting down',
            'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
            'shutdown_started_at' => Cache::get('shutdown_started_at'),
            'timestamp' => now()->toISOString(),
        ], 503);
    }
    
    // Basic health checks
    $health = [
        'status' => 'healthy',
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
        'service_name' => config('app.name'),
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'uptime' => Cache::get('service_uptime', 0),
    ];
    
    // Database connectivity check
    try {
        DB::connection()->getPdo();
        $health['database'] = 'connected';
    } catch (\Exception $e) {
        $health['database'] = 'disconnected';
        $health['status'] = 'degraded';
        $health['database_error'] = $e->getMessage();
    }
    
    // Redis connectivity check
    try {
        Redis::ping();
        $health['redis'] = 'connected';
    } catch (\Exception $e) {
        $health['redis'] = 'disconnected';
        $health['status'] = 'degraded';
        $health['redis_error'] = $e->getMessage();
    }
    
    // Add active requests count
    $health['active_requests'] = Cache::get('octane:active_requests', 0);
    
    $statusCode = $health['status'] === 'healthy' ? 200 : 503;
    
    return response()->json($health, $statusCode);
});

Route::get('/ready', function () {
    $shutdownInProgress = Cache::get('shutdown_in_progress', false);
    $readyStatus = Cache::get('ready_check_status', 'ready');
    
    if ($shutdownInProgress || $readyStatus === 'not_ready') {
        return response()->json([
            'status' => 'not_ready',
            'message' => 'Service is not ready to accept traffic',
            'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
            'timestamp' => now()->toISOString(),
        ], 503);
    }
    
    // Check if service dependencies are healthy
    $serviceHealth = new ServiceDiscoveryHealth();
    $dependencies = $serviceHealth->validateServiceDependencies();
    
    $ready = [
        'status' => 'ready',
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
        'service_name' => config('app.name'),
        'timestamp' => now()->toISOString(),
        'dependencies_healthy' => $dependencies['all_dependencies_healthy'],
    ];
    
    if (!$dependencies['all_dependencies_healthy']) {
        $ready['status'] = 'not_ready';
        $ready['message'] = 'Service dependencies are not healthy';
        $ready['failed_dependencies'] = array_keys(
            array_filter($dependencies['dependencies'], fn($dep) => !$dep['healthy'])
        );
    }
    
    $statusCode = $ready['status'] === 'ready' ? 200 : 503;
    
    return response()->json($ready, $statusCode);
});

Route::get('/health/deep', function () {
    $serviceHealth = new ServiceDiscoveryHealth();
    $allServices = $serviceHealth->checkAllServices();
    
    return response()->json([
        'status' => $allServices['overall_healthy'] ? 'healthy' : 'unhealthy',
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
        'service_name' => config('app.name'),
        'timestamp' => now()->toISOString(),
        'service_discovery' => $allServices,
        'dependencies' => $serviceHealth->validateServiceDependencies(),
    ], $allServices['overall_healthy'] ? 200 : 503);
});

Route::get('/health/cross-environment', function () {
    $serviceHealth = new ServiceDiscoveryHealth();
    $crossEnvHealth = $serviceHealth->checkCrossEnvironmentHealth();
    
    return response()->json([
        'status' => $crossEnvHealth['cross_environment_healthy'] ? 'healthy' : 'unhealthy',
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
        'service_name' => config('app.name'),
        'timestamp' => now()->toISOString(),
        'cross_environment' => $crossEnvHealth,
    ], $crossEnvHealth['cross_environment_healthy'] ? 200 : 503);
});

Route::get('/metrics', function () {
    $serviceHealth = new ServiceDiscoveryHealth();
    $metrics = $serviceHealth->getMetrics();
    
    // Add Octane-specific metrics
    $octaneMetrics = Cache::get('octane:metrics', []);
    $metrics = array_merge($metrics, [
        'octane_active_requests' => $octaneMetrics['active_requests'] ?? 0,
        'octane_total_requests' => $octaneMetrics['total_requests'] ?? 0,
        'octane_last_request_at' => $octaneMetrics['last_request_at'] ?? null,
    ]);
    
    // Format for Prometheus
    $prometheusMetrics = [];
    foreach ($metrics as $key => $value) {
        if (is_numeric($value)) {
            $prometheusMetrics[] = "# TYPE {$key} gauge";
            $prometheusMetrics[] = "{$key} {$value}";
        }
    }
    
    return response(implode("\n", $prometheusMetrics))
        ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
});

Route::get('/octane/health', function () {
    $octaneMetrics = Cache::get('octane:metrics', []);
    $shutdownInProgress = Cache::get('shutdown_in_progress', false);
    
    return response()->json([
        'status' => $shutdownInProgress ? 'shutting_down' : 'healthy',
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
        'service_name' => config('app.name'),
        'timestamp' => now()->toISOString(),
        'octane' => [
            'server' => env('OCTANE_SERVER', 'frankenphp'),
            'workers' => env('OCTANE_WORKERS', 4),
            'active_requests' => $octaneMetrics['active_requests'] ?? 0,
            'total_requests' => $octaneMetrics['total_requests'] ?? 0,
            'last_request_at' => $octaneMetrics['last_request_at'] ?? null,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ],
    ], $shutdownInProgress ? 503 : 200);
});

Route::get('/octane/metrics', function () {
    $octaneMetrics = Cache::get('octane:metrics', []);
    
    return response()->json([
        'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
        'service_name' => config('app.name'),
        'timestamp' => now()->toISOString(),
        'metrics' => $octaneMetrics,
        'system' => [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cpu_usage' => sys_getloadavg()[0] ?? 0,
        ],
    ]);
});
