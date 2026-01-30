<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    
    // Analytics routes (require authentication)
    Route::middleware('auth:sanctum')->prefix('analytics')->group(function () {
        
        // Event tracking
        Route::post('/events', [AnalyticsController::class, 'trackEvent']);
        Route::get('/users/{userId}', [AnalyticsController::class, 'getUserAnalytics']);
        
        // Business metrics
        Route::get('/metrics', [AnalyticsController::class, 'getBusinessMetrics']);
        Route::get('/dashboard', [AnalyticsController::class, 'getDashboardOverview']);
        Route::get('/realtime', [AnalyticsController::class, 'getRealTimeMetrics']);
        
        // Conversion and funnel analysis
        Route::get('/funnel', [AnalyticsController::class, 'getConversionFunnel']);
        
        // Custom reports
        Route::post('/reports', [AnalyticsController::class, 'generateReport']);
        
        // Admin-only routes
        Route::middleware('admin')->group(function () {
            Route::get('/users', [AnalyticsController::class, 'getAllUserAnalytics']);
            Route::get('/export/{type}', [AnalyticsController::class, 'exportData']);
            Route::delete('/events/{eventId}', [AnalyticsController::class, 'deleteEvent']);
            Route::post('/metrics/recalculate', [AnalyticsController::class, 'recalculateMetrics']);
        });
    });
    
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'analytics-service',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    });
    
    // Service info
    Route::get('/info', function () {
        return response()->json([
            'service' => 'analytics-service',
            'description' => 'Analytics and reporting microservice for Reverse Tender Platform',
            'version' => config('app.version', '1.0.0'),
            'endpoints' => [
                'POST /api/v1/analytics/events' => 'Track user events',
                'GET /api/v1/analytics/users/{userId}' => 'Get user analytics',
                'GET /api/v1/analytics/metrics' => 'Get business metrics',
                'GET /api/v1/analytics/dashboard' => 'Get dashboard overview',
                'GET /api/v1/analytics/realtime' => 'Get real-time metrics',
                'GET /api/v1/analytics/funnel' => 'Get conversion funnel',
                'POST /api/v1/analytics/reports' => 'Generate custom reports'
            ]
        ]);
    });
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'message' => 'Endpoint not found',
        'service' => 'analytics-service'
    ], 404);
});

