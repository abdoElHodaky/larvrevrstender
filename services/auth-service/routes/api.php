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
        'service' => 'auth-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/refresh', [App\Http\Controllers\AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::get('/me', [App\Http\Controllers\AuthController::class, 'me'])->middleware('auth:sanctum');
    
    // OTP routes
    Route::post('/otp/send', [App\Http\Controllers\AuthController::class, 'sendOtp']);
    Route::post('/otp/verify', [App\Http\Controllers\AuthController::class, 'verifyOtp']);
    
    // Social authentication routes
    Route::get('/social/{provider}/redirect', [App\Http\Controllers\AuthController::class, 'socialRedirect']);
    Route::get('/social/{provider}/callback', [App\Http\Controllers\AuthController::class, 'socialCallback']);
    
    // Session management
    Route::get('/sessions', [App\Http\Controllers\AuthController::class, 'getSessions'])->middleware('auth:sanctum');
    Route::delete('/sessions/{sessionId}', [App\Http\Controllers\AuthController::class, 'revokeSession'])->middleware('auth:sanctum');
    Route::delete('/sessions', [App\Http\Controllers\AuthController::class, 'revokeAllSessions'])->middleware('auth:sanctum');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
