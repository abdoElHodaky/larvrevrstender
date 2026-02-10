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

// Inter-service Routes (for RPC and service-to-service communication)
Route::middleware('service.auth')->group(function () {
    // Bidding routes
    Route::post('/bids', [App\Http\Controllers\BiddingController::class, 'placeBid']);
    Route::get('/bids/{bidId}', [App\Http\Controllers\BiddingController::class, 'getBid']);
    Route::get('/users/{userId}/bids', [App\Http\Controllers\BiddingController::class, 'getUserBids']);
    Route::get('/auctions/{auctionId}/bids', [App\Http\Controllers\BiddingController::class, 'getAuctionBids']);
    Route::put('/bids/{bidId}/status', [App\Http\Controllers\BiddingController::class, 'updateBidStatus']);
    Route::get('/auctions/{auctionId}/statistics', [App\Http\Controllers\BiddingController::class, 'getBidStatistics']);
    
    // Auction routes
    Route::get('/auctions', [App\Http\Controllers\AuctionController::class, 'index']);
    Route::post('/auctions', [App\Http\Controllers\AuctionController::class, 'store']);
    Route::get('/auctions/{auctionId}', [App\Http\Controllers\AuctionController::class, 'show']);
    Route::put('/auctions/{auctionId}/status', [App\Http\Controllers\AuctionController::class, 'updateStatus']);
    Route::get('/auctions/{auctionId}/with-bids', [App\Http\Controllers\AuctionController::class, 'getAuctionWithBids']);
});

// External API Routes (for direct client access)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Bidding endpoints
    Route::prefix('bids')->group(function () {
        Route::get('/', [App\Http\Controllers\BiddingController::class, 'getUserBids']);
        Route::post('/', [App\Http\Controllers\BiddingController::class, 'placeBid']);
        Route::get('/{bidId}', [App\Http\Controllers\BiddingController::class, 'getBid']);
        Route::put('/{bidId}/status', [App\Http\Controllers\BiddingController::class, 'updateBidStatus']);
        Route::post('/{bidId}/withdraw', [App\Http\Controllers\BiddingController::class, 'withdrawBid']);
    });

    // Auction endpoints
    Route::prefix('auctions')->group(function () {
        Route::get('/', [App\Http\Controllers\AuctionController::class, 'index']);
        Route::post('/', [App\Http\Controllers\AuctionController::class, 'store']);
        Route::get('/{auctionId}', [App\Http\Controllers\AuctionController::class, 'show']);
        Route::put('/{auctionId}/status', [App\Http\Controllers\AuctionController::class, 'updateStatus']);
        Route::get('/{auctionId}/bids', [App\Http\Controllers\BiddingController::class, 'getAuctionBids']);
        Route::get('/{auctionId}/statistics', [App\Http\Controllers\BiddingController::class, 'getBidStatistics']);
        Route::get('/{auctionId}/with-bids', [App\Http\Controllers\AuctionController::class, 'getAuctionWithBids']);
    });
});
