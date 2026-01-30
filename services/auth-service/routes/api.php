<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\PasswordResetController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    
    // Public authentication routes
    Route::prefix('auth')->group(function () {
        
        // Basic authentication
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        
        // Social authentication
        Route::prefix('social')->group(function () {
            Route::post('/{provider}/redirect', [AuthController::class, 'socialRedirect']);
            Route::post('/{provider}/callback', [AuthController::class, 'socialCallback']);
        });
        
        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        });
        
        // User profile (authenticated)
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/me', [AuthController::class, 'updateProfile']);
            Route::delete('/me', [AuthController::class, 'deleteAccount']);
            Route::get('/sessions', [AuthController::class, 'getSessions']);
            Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeSession']);
            Route::delete('/sessions', [AuthController::class, 'revokeAllSessions']);
        });
        
        // Two-factor authentication
        Route::middleware('auth:sanctum')->prefix('2fa')->group(function () {
            Route::post('/enable', [AuthController::class, 'enableTwoFactor']);
            Route::post('/disable', [AuthController::class, 'disableTwoFactor']);
            Route::post('/verify', [AuthController::class, 'verifyTwoFactor']);
            Route::get('/backup-codes', [AuthController::class, 'getBackupCodes']);
            Route::post('/backup-codes/regenerate', [AuthController::class, 'regenerateBackupCodes']);
        });
    });
    
    // Admin routes (using gates for authorization)
    Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
        Route::get('/users', [AuthController::class, 'getUsers'])->middleware('can:manage-users');
        Route::get('/users/{userId}', [AuthController::class, 'getUser'])->middleware('can:manage-users');
        Route::put('/users/{userId}/status', [AuthController::class, 'updateUserStatus'])->middleware('can:manage-users');
        Route::delete('/users/{userId}', [AuthController::class, 'deleteUser'])->middleware('can:manage-users');
        Route::get('/sessions', [AuthController::class, 'getAllSessions'])->middleware('can:admin-access');
        Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeUserSession'])->middleware('can:admin-access');
    });
    
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'auth-service',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    });
    
    // Service info
    Route::get('/info', function () {
        return response()->json([
            'service' => 'auth-service',
            'description' => 'Authentication microservice for Reverse Tender Platform',
            'version' => config('app.version', '1.0.0'),
            'endpoints' => [
                'POST /api/v1/auth/register' => 'User registration',
                'POST /api/v1/auth/login' => 'User login',
                'POST /api/v1/auth/logout' => 'User logout',
                'POST /api/v1/auth/refresh' => 'Refresh token',
                'GET /api/v1/auth/me' => 'Get user profile',
                'POST /api/v1/auth/otp/send' => 'Send OTP',
                'POST /api/v1/auth/otp/verify' => 'Verify OTP',
                'POST /api/v1/auth/social/{provider}/login' => 'Social login',
                'POST /api/v1/auth/password/forgot' => 'Forgot password',
                'POST /api/v1/auth/password/reset' => 'Reset password'
            ]
        ]);
    });
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'message' => 'Endpoint not found',
        'service' => 'auth-service'
    ], 404);
});
