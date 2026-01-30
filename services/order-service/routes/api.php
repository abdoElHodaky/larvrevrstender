<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\ValidateOrderOwnership;
use App\Http\Middleware\CheckMerchantVerification;

/*
|--------------------------------------------------------------------------
| API Routes - Order Service
|--------------------------------------------------------------------------
|
| Laravel/Lumen routing system with proper middleware, policies, and gates
| Implements role-based access control and rate limiting
|
*/

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
        'version' => '1.0.0',
        'environment' => app()->environment()
    ]);
});

Route::get('/status', function () {
    try {
        // Check database connection
        \DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    try {
        // Check Redis connection
        \Redis::ping();
        $cacheStatus = 'connected';
    } catch (\Exception $e) {
        $cacheStatus = 'disconnected';
    }

    return response()->json([
        'service' => 'Order Service',
        'status' => 'operational',
        'database' => $dbStatus,
        'cache' => $cacheStatus,
        'queue' => 'operational',
        'timestamp' => now()->toISOString(),
        'memory_usage' => memory_get_usage(true),
        'uptime' => app()->hasBeenBootstrapped() ? 'running' : 'starting'
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated User Info
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->get('/user', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
        'permissions' => $request->user()->getAllPermissions(),
        'roles' => $request->user()->getRoleNames()
    ]);
});

/*
|--------------------------------------------------------------------------
| Order Management Routes - Customer Access
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    
    // Order listing with role-based filtering
    Route::get('orders', [OrderController::class, 'index'])
        ->middleware('can:viewAny,App\Models\Order');
    
    // Order creation - customers only
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware(['can:create,App\Models\Order', 'throttle:10,1']);
    
    // Order search with rate limiting
    Route::get('orders/search', [OrderController::class, 'search'])
        ->middleware(['can:viewAny,App\Models\Order', 'throttle:30,1']);
    
    // Order statistics - role-based access
    Route::get('orders/statistics', [OrderController::class, 'statistics'])
        ->middleware('can:viewStatistics,App\Models\Order');
    
});

/*
|--------------------------------------------------------------------------
| Individual Order Routes - Policy-based Authorization
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    
    // View specific order
    Route::get('orders/{order}', [OrderController::class, 'show'])
        ->middleware('can:view,order');
    
    // Update order - owner only, limited statuses
    Route::put('orders/{order}', [OrderController::class, 'update'])
        ->middleware(['can:update,order', 'throttle:20,1']);
    
    // Delete order - restricted access
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])
        ->middleware('can:delete,order');
    
});

/*
|--------------------------------------------------------------------------
| Order Action Routes - Specific Permissions
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    
    // Publish order - owner only
    Route::post('orders/{order}/publish', [OrderController::class, 'publish'])
        ->middleware(['can:publish,order', 'throttle:5,1']);
    
    // Cancel order - owner or admin
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->middleware(['can:cancel,order', 'throttle:10,1']);
    
    // Upload images - owner only, specific statuses
    Route::post('orders/{order}/images', [OrderController::class, 'uploadImages'])
        ->middleware(['can:uploadImages,order', 'throttle:20,1']);
    
    // Award order - customer owner only
    Route::post('orders/{order}/award', [OrderController::class, 'award'])
        ->middleware(['can:award,order', 'throttle:5,1']);
    
    // Complete order - owner or winning merchant
    Route::post('orders/{order}/complete', [OrderController::class, 'complete'])
        ->middleware(['can:complete,order', 'throttle:10,1']);
    
    // View order history - same as view permission
    Route::get('orders/{order}/history', [OrderController::class, 'history'])
        ->middleware('can:viewHistory,order');
    
});

/*
|--------------------------------------------------------------------------
| Merchant-specific Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:api', CheckMerchantVerification::class])->group(function () {
    
    // Get orders available for bidding
    Route::get('orders/available-for-bidding', [OrderController::class, 'availableForBidding'])
        ->middleware('can:viewAny,App\Models\Order');
    
    // Get orders by location/service area
    Route::get('orders/by-location', [OrderController::class, 'byLocation'])
        ->middleware('can:viewAny,App\Models\Order');
    
});

/*
|--------------------------------------------------------------------------
| Admin Routes - Full Access
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:admin', 'throttle:admin'])->prefix('admin')->group(function () {
    
    // Admin order management
    Route::get('orders', [OrderController::class, 'adminIndex']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('orders/analytics', [OrderController::class, 'analytics']);
    Route::post('orders/bulk-actions', [OrderController::class, 'bulkActions']);
    
    // Export functionality
    Route::get('orders/export', [OrderController::class, 'export'])
        ->middleware('can:export,App\Models\Order');
    
});

/*
|--------------------------------------------------------------------------
| Internal Service Routes (Service-to-Service Communication)
|--------------------------------------------------------------------------
*/

Route::prefix('internal')->middleware(['throttle:internal'])->group(function () {
    
    // Internal order operations for other services
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::get('orders/customer/{customerId}', [OrderController::class, 'index']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('orders/{order}/bids-count', [OrderController::class, 'getBidsCount']);
    Route::post('orders/batch', [OrderController::class, 'batchGet']);
    
});

/*
|--------------------------------------------------------------------------
| Webhook Routes - External Integrations
|--------------------------------------------------------------------------
*/

Route::prefix('webhooks')->middleware(['throttle:webhooks'])->group(function () {
    
    // Payment service webhooks
    Route::post('payment-completed', [OrderController::class, 'handlePaymentCompleted']);
    Route::post('payment-failed', [OrderController::class, 'handlePaymentFailed']);
    
    // VIN OCR service webhooks
    Route::post('vin-processed', [OrderController::class, 'handleVinProcessed']);
    
    // Notification service webhooks
    Route::post('notification-delivered', [OrderController::class, 'handleNotificationDelivered']);
    
});

/*
|--------------------------------------------------------------------------
| Real-time Routes - WebSocket Integration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('realtime')->group(function () {
    
    // Subscribe to order updates
    Route::post('orders/{order}/subscribe', [OrderController::class, 'subscribeToUpdates'])
        ->middleware('can:view,order');
    
    // Unsubscribe from order updates
    Route::delete('orders/{order}/subscribe', [OrderController::class, 'unsubscribeFromUpdates'])
        ->middleware('can:view,order');
    
});

/*
|--------------------------------------------------------------------------
| Route Model Binding
|--------------------------------------------------------------------------
*/

Route::bind('order', function ($value) {
    return \App\Models\Order::where('id', $value)
        ->orWhere('order_number', $value)
        ->firstOrFail();
});
