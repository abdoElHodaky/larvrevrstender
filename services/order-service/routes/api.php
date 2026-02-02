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
        'service' => 'order-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Inter-service Routes
Route::middleware('service.auth')->group(function () {
    Route::post('/orders', [App\Http\Controllers\OrderController::class, 'createOrder']);
    Route::get('/orders/{orderId}', [App\Http\Controllers\OrderController::class, 'getOrder']);
    Route::put('/orders/{orderId}/status', [App\Http\Controllers\OrderController::class, 'updateOrderStatus']);
    Route::post('/orders/{orderId}/items', [App\Http\Controllers\OrderController::class, 'addOrderItem']);
    Route::put('/orders/{orderId}/items/{itemId}', [App\Http\Controllers\OrderController::class, 'updateOrderItem']);
});

// External API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [App\Http\Controllers\OrderController::class, 'index']);
        Route::post('/', [App\Http\Controllers\OrderController::class, 'store']);
        Route::get('/{order}', [App\Http\Controllers\OrderController::class, 'show']);
        Route::put('/{order}', [App\Http\Controllers\OrderController::class, 'update']);
        Route::delete('/{order}', [App\Http\Controllers\OrderController::class, 'cancel']);
        Route::get('/{order}/tracking', [App\Http\Controllers\OrderController::class, 'getTracking']);
        Route::post('/{order}/confirm', [App\Http\Controllers\OrderController::class, 'confirm']);
    });

    Route::prefix('cart')->group(function () {
        Route::get('/', [App\Http\Controllers\CartController::class, 'show']);
        Route::post('/items', [App\Http\Controllers\CartController::class, 'addItem']);
        Route::put('/items/{item}', [App\Http\Controllers\CartController::class, 'updateItem']);
        Route::delete('/items/{item}', [App\Http\Controllers\CartController::class, 'removeItem']);
        Route::delete('/', [App\Http\Controllers\CartController::class, 'clear']);
        Route::post('/checkout', [App\Http\Controllers\CartController::class, 'checkout']);
    });
});
