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
        'service' => 'bidding-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Inter-service Routes
Route::middleware('service.auth')->group(function () {
    Route::post('/bids', [App\Http\Controllers\BiddingController::class, 'placeBid']);
    Route::get('/bids/{bidId}', [App\Http\Controllers\BiddingController::class, 'getBid']);
    Route::get('/users/{userId}/bids', [App\Http\Controllers\BiddingController::class, 'getUserBids']);
    Route::get('/auctions/{auctionId}/bids', [App\Http\Controllers\BiddingController::class, 'getAuctionBids']);
    Route::put('/bids/{bidId}/status', [App\Http\Controllers\BiddingController::class, 'updateBidStatus']);
});

// External API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('bids')->group(function () {
        Route::get('/', [App\Http\Controllers\BiddingController::class, 'index']);
        Route::post('/', [App\Http\Controllers\BiddingController::class, 'store']);
        Route::get('/{bid}', [App\Http\Controllers\BiddingController::class, 'show']);
        Route::put('/{bid}', [App\Http\Controllers\BiddingController::class, 'update']);
        Route::delete('/{bid}', [App\Http\Controllers\BiddingController::class, 'cancel']);
    });

    Route::prefix('auctions')->group(function () {
        Route::get('/', [App\Http\Controllers\AuctionController::class, 'index']);
        Route::post('/', [App\Http\Controllers\AuctionController::class, 'store']);
        Route::get('/{auction}', [App\Http\Controllers\AuctionController::class, 'show']);
        Route::put('/{auction}', [App\Http\Controllers\AuctionController::class, 'update']);
        Route::delete('/{auction}', [App\Http\Controllers\AuctionController::class, 'destroy']);
        Route::get('/{auction}/bids', [App\Http\Controllers\AuctionController::class, 'getBids']);
        Route::post('/{auction}/close', [App\Http\Controllers\AuctionController::class, 'close']);
    });
});
