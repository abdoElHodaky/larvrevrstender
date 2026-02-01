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
        'service' => 'notification-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Inter-service Routes
Route::middleware('service.auth')->group(function () {
    Route::post('/notifications', [App\Http\Controllers\NotificationController::class, 'sendNotification']);
    Route::post('/notifications/bulk', [App\Http\Controllers\NotificationController::class, 'sendBulkNotification']);
    Route::get('/notifications/{notificationId}', [App\Http\Controllers\NotificationController::class, 'getNotificationStatus']);
    Route::post('/templates', [App\Http\Controllers\TemplateController::class, 'createTemplate']);
});

// External API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\NotificationController::class, 'index']);
        Route::get('/{notification}', [App\Http\Controllers\NotificationController::class, 'show']);
        Route::put('/{notification}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);
        Route::delete('/{notification}', [App\Http\Controllers\NotificationController::class, 'destroy']);
        Route::post('/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
    });

    Route::prefix('templates')->group(function () {
        Route::get('/', [App\Http\Controllers\TemplateController::class, 'index']);
        Route::post('/', [App\Http\Controllers\TemplateController::class, 'store']);
        Route::get('/{template}', [App\Http\Controllers\TemplateController::class, 'show']);
        Route::put('/{template}', [App\Http\Controllers\TemplateController::class, 'update']);
        Route::delete('/{template}', [App\Http\Controllers\TemplateController::class, 'destroy']);
    });

    Route::prefix('preferences')->group(function () {
        Route::get('/', [App\Http\Controllers\PreferencesController::class, 'show']);
        Route::put('/', [App\Http\Controllers\PreferencesController::class, 'update']);
    });
});
