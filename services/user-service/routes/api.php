<?php

use App\Http\Controllers\UserProfileController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'user-service',
        'timestamp' => now()->toISOString()
    ]);
});

// User Profile Management Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Profile CRUD operations
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::post('/profile', [UserProfileController::class, 'store']);
    
    // Delivery addresses
    Route::post('/profile/delivery-address', [UserProfileController::class, 'addDeliveryAddress']);
    
    // Preferences
    Route::put('/profile/preferences', [UserProfileController::class, 'updatePreferences']);
    
    // KYC Verification
    Route::post('/profile/kyc-verification', [UserProfileController::class, 'submitKYCVerification']);
    Route::get('/profile/verification-status', [UserProfileController::class, 'verificationStatus']);
    
    // Preferred categories
    Route::post('/profile/preferred-categories', [UserProfileController::class, 'addPreferredCategory']);
    Route::delete('/profile/preferred-categories', [UserProfileController::class, 'removePreferredCategory']);
});

// Admin routes (for internal service communication)
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // These routes would be used by other services or admin panel
    Route::get('/profiles/{userId}', function ($userId) {
        $profile = \App\Models\CustomerProfile::where('user_id', $userId)->first();
        return response()->json(['data' => $profile]);
    });
    
    Route::put('/profiles/{userId}/verification-status', function (Request $request, $userId) {
        $request->validate([
            'status' => 'required|in:pending,verified,rejected'
        ]);
        
        $profile = \App\Models\CustomerProfile::where('user_id', $userId)->firstOrFail();
        $profile->update(['verification_status' => $request->status]);
        
        // Fire event for verification completion
        event(new \App\Events\KYCVerificationCompleted($profile, $request->status));
        
        return response()->json(['data' => $profile]);
    });
});

