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
        'service' => 'payment-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Inter-service Routes (Protected by ServiceAuthentication middleware)
Route::middleware('service.auth')->group(function () {
    // Payment processing routes for inter-service communication
    Route::post('/payments', [App\Http\Controllers\PaymentController::class, 'processPayment']);
    Route::get('/payments/{paymentId}', [App\Http\Controllers\PaymentController::class, 'getPaymentStatus']);
    Route::post('/payments/{paymentId}/refund', [App\Http\Controllers\PaymentController::class, 'refundPayment']);
    Route::post('/payments/{paymentId}/capture', [App\Http\Controllers\PaymentController::class, 'capturePayment']);
    Route::post('/payments/{paymentId}/cancel', [App\Http\Controllers\PaymentController::class, 'cancelPayment']);

    // Payment validation routes
    Route::post('/payments/validate', [App\Http\Controllers\PaymentController::class, 'validatePayment']);
    Route::post('/payments/verify', [App\Http\Controllers\PaymentController::class, 'verifyPayment']);

    // Gateway management routes
    Route::get('/gateways', [App\Http\Controllers\GatewayController::class, 'getAvailableGateways']);
    Route::get('/gateways/{gateway}/status', [App\Http\Controllers\GatewayController::class, 'getGatewayStatus']);
});

// External API Routes (Protected by Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Payment management
    Route::prefix('payments')->group(function () {
        Route::get('/', [App\Http\Controllers\PaymentController::class, 'index']);
        Route::post('/', [App\Http\Controllers\PaymentController::class, 'create']);
        Route::get('/{payment}', [App\Http\Controllers\PaymentController::class, 'show']);
        Route::post('/{payment}/confirm', [App\Http\Controllers\PaymentController::class, 'confirm']);
        Route::post('/{payment}/cancel', [App\Http\Controllers\PaymentController::class, 'cancel']);
        Route::get('/{payment}/receipt', [App\Http\Controllers\PaymentController::class, 'getReceipt']);
    });

    // Payment methods management
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [App\Http\Controllers\PaymentMethodController::class, 'index']);
        Route::post('/', [App\Http\Controllers\PaymentMethodController::class, 'store']);
        Route::get('/{paymentMethod}', [App\Http\Controllers\PaymentMethodController::class, 'show']);
        Route::put('/{paymentMethod}', [App\Http\Controllers\PaymentMethodController::class, 'update']);
        Route::delete('/{paymentMethod}', [App\Http\Controllers\PaymentMethodController::class, 'destroy']);
        Route::post('/{paymentMethod}/verify', [App\Http\Controllers\PaymentMethodController::class, 'verify']);
    });

    // Transaction history
    Route::prefix('transactions')->group(function () {
        Route::get('/', [App\Http\Controllers\TransactionController::class, 'index']);
        Route::get('/{transaction}', [App\Http\Controllers\TransactionController::class, 'show']);
        Route::get('/{transaction}/receipt', [App\Http\Controllers\TransactionController::class, 'getReceipt']);
    });

    // Refunds management
    Route::prefix('refunds')->group(function () {
        Route::get('/', [App\Http\Controllers\RefundController::class, 'index']);
        Route::post('/', [App\Http\Controllers\RefundController::class, 'create']);
        Route::get('/{refund}', [App\Http\Controllers\RefundController::class, 'show']);
        Route::get('/{refund}/status', [App\Http\Controllers\RefundController::class, 'getStatus']);
    });

    // Billing and invoices
    Route::prefix('billing')->group(function () {
        Route::get('/invoices', [App\Http\Controllers\InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [App\Http\Controllers\InvoiceController::class, 'show']);
        Route::get('/invoices/{invoice}/download', [App\Http\Controllers\InvoiceController::class, 'download']);
        Route::post('/invoices/{invoice}/pay', [App\Http\Controllers\InvoiceController::class, 'pay']);
    });

    // Payment analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/summary', [App\Http\Controllers\PaymentAnalyticsController::class, 'getSummary']);
        Route::get('/trends', [App\Http\Controllers\PaymentAnalyticsController::class, 'getTrends']);
        Route::get('/methods', [App\Http\Controllers\PaymentAnalyticsController::class, 'getMethodAnalytics']);
    });
});

// Webhook routes (no authentication required)
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [App\Http\Controllers\WebhookController::class, 'handleStripe']);
    Route::post('/paypal', [App\Http\Controllers\WebhookController::class, 'handlePaypal']);
    Route::post('/razorpay', [App\Http\Controllers\WebhookController::class, 'handleRazorpay']);
    Route::post('/square', [App\Http\Controllers\WebhookController::class, 'handleSquare']);
});
