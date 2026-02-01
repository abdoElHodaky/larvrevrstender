<?php

use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check routes
Route::get('/health', [HealthController::class, 'check']);
Route::get('/up', [HealthController::class, 'up']);

// Service info route
Route::get('/info', function () {
    return response()->json([
        'service' => 'analytics-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Inter-service Routes
Route::middleware('service.auth')->group(function () {
    Route::post('/metrics', [App\Http\Controllers\AnalyticsController::class, 'collectMetric']);
    Route::post('/reports/{reportType}', [App\Http\Controllers\AnalyticsController::class, 'getReport']);
    Route::get('/health/services', [App\Http\Controllers\AnalyticsController::class, 'getServiceHealth']);
    Route::post('/events', [App\Http\Controllers\AnalyticsController::class, 'trackEvent']);
});

// External API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\AnalyticsController::class, 'getDashboard']);
        Route::get('/reports', [App\Http\Controllers\ReportController::class, 'index']);
        Route::post('/reports', [App\Http\Controllers\ReportController::class, 'generate']);
        Route::get('/reports/{report}', [App\Http\Controllers\ReportController::class, 'show']);
        Route::get('/reports/{report}/download', [App\Http\Controllers\ReportController::class, 'download']);
    });

    Route::prefix('metrics')->group(function () {
        Route::get('/', [App\Http\Controllers\MetricsController::class, 'index']);
        Route::get('/summary', [App\Http\Controllers\MetricsController::class, 'getSummary']);
        Route::get('/trends', [App\Http\Controllers\MetricsController::class, 'getTrends']);
        Route::get('/performance', [App\Http\Controllers\MetricsController::class, 'getPerformance']);
    });
});
