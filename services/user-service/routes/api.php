<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Api\AuthController;
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
Route::get('/health', [HealthController::class, 'health']);
Route::get('/up', [HealthController::class, 'up']);
Route::get('/info', [HealthController::class, 'info']);

// External API Authentication Routes (Public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/validate-token', [AuthController::class, 'validateToken']);
});

// Protected External API Routes (Require Sanctum Token)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

// Inter-service Routes (Protected by ServiceAuthentication middleware)
Route::middleware('service.auth')->prefix('internal')->group(function () {
    Route::get('/users/{id}', function ($id) {
        return response()->json(['user' => ['id' => $id, 'name' => 'Test User']]);
    });
    Route::get('/users/{id}/payment-methods', function ($id) {
        return response()->json(['payment_methods' => []]);
    });
    Route::put('/users/{id}/payment-preferences', function ($id) {
        return response()->json(['success' => true]);
    });
    Route::get('/users/{id}/kyc-status', function ($id) {
        return response()->json(['kyc_verified' => true]);
    });
    Route::get('/users/{id}/billing-address', function ($id) {
        return response()->json(['address' => 'Test Address']);
    });
    Route::post('/users/{id}/wallet', function ($id) {
        return response()->json(['success' => true]);
    });
});

// Legacy protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
