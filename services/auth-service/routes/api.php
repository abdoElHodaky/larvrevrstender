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
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
        
        // Email verification
        Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('/email/resend', [AuthController::class, 'resendEmailVerification']);
        
        // Phone verification
        Route::post('/phone/verify', [AuthController::class, 'verifyPhone']);
        Route::post('/phone/resend', [AuthController::class, 'resendPhoneVerification']);
        
        // Password reset
        Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink']);
        Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
        Route::post('/password/change', [PasswordResetController::class, 'changePassword'])->middleware('auth:sanctum');
        
        // Social authentication
        Route::prefix('social')->group(function () {
            Route::get('/{provider}/redirect', [SocialAuthController::class, 'redirectToProvider']);
            Route::post('/{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
            Route::post('/{provider}/login', [SocialAuthController::class, 'socialLogin']);
        });
        
        // OTP management
        Route::prefix('otp')->group(function () {
            Route::post('/send', [OtpController::class, 'sendOtp']);
            Route::post('/verify', [OtpController::class, 'verifyOtp']);
            Route::post('/resend', [OtpController::class, 'resendOtp']);
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
    
    // Admin routes
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::get('/users', [AuthController::class, 'getUsers']);
        Route::get('/users/{userId}', [AuthController::class, 'getUser']);
        Route::put('/users/{userId}/status', [AuthController::class, 'updateUserStatus']);
        Route::delete('/users/{userId}', [AuthController::class, 'deleteUser']);
        Route::get('/sessions', [AuthController::class, 'getAllSessions']);
        Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeUserSession']);
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

