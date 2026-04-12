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

// RPC HTTP Endpoints for Inter-Service Communication
Route::middleware(['service.auth'])->prefix('rpc')->group(function () {
    Route::post('/process-vin-image', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->processImage($request->all());
        return response()->json($result);
    });

    Route::post('/process-vin-text', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->processBase64($request->all());
        return response()->json($result);
    });

    Route::post('/validate-vin', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->validateVin($request->all());
        return response()->json($result);
    });

    Route::post('/extract-vin-data', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->decodeVin($request->all());
        return response()->json($result);
    });

    Route::get('/processing-history', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->getHistory($request->all());
        return response()->json($result);
    });

    Route::get('/ocr-stats', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->getStatistics($request->all());
        return response()->json($result);
    });

    Route::post('/reprocess-vin', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->batchProcess($request->all());
        return response()->json($result);
    });

    Route::get('/health-check', function (Request $request) {
        return response()->json([
            'success' => true,
            'service' => 'vin-ocr-service',
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ]);
    });

    Route::get('/service-info', function (Request $request) {
        $procedure = app(\App\RPC\Procedures\VinOcrProcedure::class);
        $result = $procedure->getSupportedFormats($request->all());
        return response()->json(array_merge($result, [
            'service' => 'vin-ocr-service',
            'version' => '1.0.0',
            'endpoints' => [
                'process-vin-image',
                'process-vin-text', 
                'validate-vin',
                'extract-vin-data',
                'processing-history',
                'ocr-stats',
                'reprocess-vin',
                'health-check',
                'service-info'
            ]
        ]));
    });
});
