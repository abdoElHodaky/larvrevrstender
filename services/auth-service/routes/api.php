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

// Inter-service authentication routes (for service-to-service communication)
Route::middleware('service.auth')->prefix('auth')->group(function () {
    Route::post('/validate', [App\Http\Controllers\AuthController::class, 'validateToken']);
    Route::get('/permissions/{userId}', [App\Http\Controllers\AuthController::class, 'getUserPermissions']);
    Route::post('/check-permission', [App\Http\Controllers\AuthController::class, 'checkPermission']);
    Route::post('/log-activity', [App\Http\Controllers\AuthController::class, 'logActivity']);
    Route::post('/sessions', [App\Http\Controllers\AuthController::class, 'createSession']);
    Route::delete('/sessions/{sessionId}', [App\Http\Controllers\AuthController::class, 'invalidateSession']);
    Route::get('/roles/{userId}', [App\Http\Controllers\AuthController::class, 'getUserRoles']);
    Route::post('/check-role', [App\Http\Controllers\AuthController::class, 'checkRole']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // User management routes
    Route::prefix('users')->group(function () {
        Route::get('/', [App\Http\Controllers\UserController::class, 'index']);
        Route::get('/{user}', [App\Http\Controllers\UserController::class, 'show']);
        Route::put('/{user}', [App\Http\Controllers\UserController::class, 'update']);
        Route::delete('/{user}', [App\Http\Controllers\UserController::class, 'destroy']);

        // User permissions
        Route::get('/{user}/permissions', [App\Http\Controllers\UserController::class, 'getPermissions']);
        Route::post('/{user}/permissions', [App\Http\Controllers\UserController::class, 'assignPermissions']);
        Route::delete('/{user}/permissions', [App\Http\Controllers\UserController::class, 'revokePermissions']);

        // User roles
        Route::get('/{user}/roles', [App\Http\Controllers\UserController::class, 'getRoles']);
        Route::post('/{user}/roles', [App\Http\Controllers\UserController::class, 'assignRoles']);
        Route::delete('/{user}/roles', [App\Http\Controllers\UserController::class, 'revokeRoles']);
    });

    // Activity logs
    Route::prefix('activities')->group(function () {
        Route::get('/', [App\Http\Controllers\ActivityController::class, 'index']);
        Route::get('/{activity}', [App\Http\Controllers\ActivityController::class, 'show']);
        Route::get('/user/{userId}', [App\Http\Controllers\ActivityController::class, 'getUserActivities']);
    });

    // Permissions management
    Route::prefix('permissions')->group(function () {
        Route::get('/', [App\Http\Controllers\PermissionController::class, 'index']);
        Route::post('/', [App\Http\Controllers\PermissionController::class, 'store']);
        Route::get('/{permission}', [App\Http\Controllers\PermissionController::class, 'show']);
        Route::put('/{permission}', [App\Http\Controllers\PermissionController::class, 'update']);
        Route::delete('/{permission}', [App\Http\Controllers\PermissionController::class, 'destroy']);
    });

    // Roles management
    Route::prefix('roles')->group(function () {
        Route::get('/', [App\Http\Controllers\RoleController::class, 'index']);
        Route::post('/', [App\Http\Controllers\RoleController::class, 'store']);
        Route::get('/{role}', [App\Http\Controllers\RoleController::class, 'show']);
        Route::put('/{role}', [App\Http\Controllers\RoleController::class, 'update']);
        Route::delete('/{role}', [App\Http\Controllers\RoleController::class, 'destroy']);
    });
});
