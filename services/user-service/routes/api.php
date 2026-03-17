<?php

use App\Http\Controllers\Api\AuthController;
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
Route::middleware('service.auth')->group(function () {
    // User profile routes for inter-service communication
    Route::get('/users/{userId}', [App\Http\Controllers\UserController::class, 'getUserProfile']);
    Route::put('/users/{userId}', [App\Http\Controllers\UserController::class, 'updateUserProfile']);

    // Wallet management routes
    Route::get('/users/{userId}/wallet', [App\Http\Controllers\WalletController::class, 'getUserWallet']);
    Route::post('/users/{userId}/wallet/transactions', [App\Http\Controllers\WalletController::class, 'updateWalletBalance']);
    Route::post('/users/{userId}/wallet/reserve', [App\Http\Controllers\WalletController::class, 'reserveFunds']);
    Route::post('/users/{userId}/wallet/release', [App\Http\Controllers\WalletController::class, 'releaseFunds']);

    // User preferences routes
    Route::get('/users/{userId}/preferences', [App\Http\Controllers\UserController::class, 'getUserPreferences']);
    Route::put('/users/{userId}/preferences', [App\Http\Controllers\UserController::class, 'updateUserPreferences']);

    // KYC management routes
    Route::get('/users/{userId}/kyc', [App\Http\Controllers\KycController::class, 'getKycStatus']);
    Route::put('/users/{userId}/kyc', [App\Http\Controllers\KycController::class, 'updateKycStatus']);

    // Notification preferences routes
    Route::get('/users/{userId}/notification-preferences', [App\Http\Controllers\NotificationController::class, 'getNotificationPreferences']);
    Route::put('/users/{userId}/notification-preferences', [App\Http\Controllers\NotificationController::class, 'updateNotificationPreferences']);

    // Bulk user operations
    Route::post('/users/search', [App\Http\Controllers\UserController::class, 'getUsersByCriteria']);

    // Activity management routes
    Route::prefix('activities')->group(function () {
        Route::get('/', [App\Http\Controllers\ActivityController::class, 'index']);
        Route::post('/', [App\Http\Controllers\ActivityController::class, 'store']);
        Route::post('/bulk', [App\Http\Controllers\ActivityController::class, 'bulkStore']);
        Route::get('/{activityId}', [App\Http\Controllers\ActivityController::class, 'show']);
        Route::delete('/{activityId}', [App\Http\Controllers\ActivityController::class, 'destroy']);
        Route::get('/user/{userId}', [App\Http\Controllers\ActivityController::class, 'getUserActivities']);
        Route::get('/user/{userId}/stats', [App\Http\Controllers\ActivityController::class, 'getUserActivityStats']);
        Route::post('/subject', [App\Http\Controllers\ActivityController::class, 'getSubjectActivities']);
    });
});

// External API Routes (Protected by Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // User profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', [App\Http\Controllers\ProfileController::class, 'show']);
        Route::put('/', [App\Http\Controllers\ProfileController::class, 'update']);
        Route::get('/avatar', [App\Http\Controllers\ProfileController::class, 'getAvatar']);
        Route::post('/avatar', [App\Http\Controllers\ProfileController::class, 'uploadAvatar']);
        Route::delete('/avatar', [App\Http\Controllers\ProfileController::class, 'deleteAvatar']);
    });

    // Wallet management
    Route::prefix('wallet')->group(function () {
        Route::get('/', [App\Http\Controllers\WalletController::class, 'show']);
        Route::get('/transactions', [App\Http\Controllers\WalletController::class, 'getTransactions']);
        Route::get('/balance', [App\Http\Controllers\WalletController::class, 'getBalance']);
        Route::post('/deposit', [App\Http\Controllers\WalletController::class, 'deposit']);
        Route::post('/withdraw', [App\Http\Controllers\WalletController::class, 'withdraw']);
    });

    // User preferences
    Route::prefix('preferences')->group(function () {
        Route::get('/', [App\Http\Controllers\PreferencesController::class, 'show']);
        Route::put('/', [App\Http\Controllers\PreferencesController::class, 'update']);
        Route::get('/notifications', [App\Http\Controllers\PreferencesController::class, 'getNotificationPreferences']);
        Route::put('/notifications', [App\Http\Controllers\PreferencesController::class, 'updateNotificationPreferences']);
    });

    // KYC management
    Route::prefix('kyc')->group(function () {
        Route::get('/', [App\Http\Controllers\KycController::class, 'show']);
        Route::post('/submit', [App\Http\Controllers\KycController::class, 'submit']);
        Route::get('/status', [App\Http\Controllers\KycController::class, 'getStatus']);
        Route::get('/documents', [App\Http\Controllers\KycController::class, 'getDocuments']);
        Route::post('/documents', [App\Http\Controllers\KycController::class, 'uploadDocument']);
        Route::delete('/documents/{id}', [App\Http\Controllers\KycController::class, 'deleteDocument']);
    });

    // Address management
    Route::prefix('addresses')->group(function () {
        Route::get('/', [App\Http\Controllers\AddressController::class, 'index']);
        Route::post('/', [App\Http\Controllers\AddressController::class, 'store']);
        Route::get('/{address}', [App\Http\Controllers\AddressController::class, 'show']);
        Route::put('/{address}', [App\Http\Controllers\AddressController::class, 'update']);
        Route::delete('/{address}', [App\Http\Controllers\AddressController::class, 'destroy']);
        Route::post('/{address}/set-default', [App\Http\Controllers\AddressController::class, 'setDefault']);
    });

    // Payment methods
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [App\Http\Controllers\PaymentMethodController::class, 'index']);
        Route::post('/', [App\Http\Controllers\PaymentMethodController::class, 'store']);
        Route::get('/{paymentMethod}', [App\Http\Controllers\PaymentMethodController::class, 'show']);
        Route::put('/{paymentMethod}', [App\Http\Controllers\PaymentMethodController::class, 'update']);
        Route::delete('/{paymentMethod}', [App\Http\Controllers\PaymentMethodController::class, 'destroy']);
        Route::post('/{paymentMethod}/set-default', [App\Http\Controllers\PaymentMethodController::class, 'setDefault']);
    });

    // VIN OCR Processing routes
    Route::prefix('vin-ocr')->group(function () {
        Route::post('/process-image', [App\Http\Controllers\VinOcrController::class, 'processImage']);
        Route::post('/process-text', [App\Http\Controllers\VinOcrController::class, 'processText']);
        Route::post('/validate', [App\Http\Controllers\VinOcrController::class, 'validateVin']);
        Route::post('/reprocess/{vehicleId}', [App\Http\Controllers\VinOcrController::class, 'reprocessVin']);
        Route::get('/stats', [App\Http\Controllers\VinOcrController::class, 'getStats']);
        Route::post('/extract-data', [App\Http\Controllers\VinOcrController::class, 'extractData']);
    });
});
