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
        'service' => 'vin-ocr-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Inter-service Routes
Route::middleware('service.auth')->group(function () {
    Route::post('/ocr/process', [App\Http\Controllers\OcrController::class, 'processOcr']);
    Route::get('/ocr/results/{requestId}', [App\Http\Controllers\OcrController::class, 'getOcrResult']);
    Route::get('/users/{userId}/usage', [App\Http\Controllers\OcrController::class, 'getUserUsage']);
    Route::post('/ocr/batch', [App\Http\Controllers\OcrController::class, 'processBatch']);
});

// External API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('ocr')->group(function () {
        Route::post('/upload', [App\Http\Controllers\OcrController::class, 'upload']);
        Route::get('/results', [App\Http\Controllers\OcrController::class, 'index']);
        Route::get('/results/{result}', [App\Http\Controllers\OcrController::class, 'show']);
        Route::delete('/results/{result}', [App\Http\Controllers\OcrController::class, 'destroy']);
        Route::get('/history', [App\Http\Controllers\OcrController::class, 'getHistory']);
    });

    Route::prefix('usage')->group(function () {
        Route::get('/', [App\Http\Controllers\UsageController::class, 'show']);
        Route::get('/statistics', [App\Http\Controllers\UsageController::class, 'getStatistics']);
        Route::get('/limits', [App\Http\Controllers\UsageController::class, 'getLimits']);
    });
});
