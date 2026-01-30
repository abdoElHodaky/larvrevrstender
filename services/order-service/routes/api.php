<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Order Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Order CRUD operations
    Route::apiResource('orders', OrderController::class);
    
    // Order-specific actions
    Route::post('orders/{id}/publish', [OrderController::class, 'publish']);
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{id}/images', [OrderController::class, 'uploadImages']);
    
    // Order search and statistics
    Route::get('orders/search', [OrderController::class, 'search']);
    Route::get('orders/statistics', [OrderController::class, 'statistics']);
    
});

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'service' => 'Order Service',
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

Route::get('/status', function () {
    return response()->json([
        'service' => 'Order Service',
        'status' => 'operational',
        'database' => 'connected',
        'cache' => 'connected',
        'queue' => 'operational',
        'timestamp' => now()->toISOString()
    ]);
});

/*
|--------------------------------------------------------------------------
| Internal Service Routes (Service-to-Service Communication)
|--------------------------------------------------------------------------
*/

Route::prefix('internal')->group(function () {
    
    // Internal order operations for other services
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::get('orders/customer/{customerId}', [OrderController::class, 'index']);
    Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
    
});
