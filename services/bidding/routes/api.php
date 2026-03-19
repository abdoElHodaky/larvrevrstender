<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Api\BiddingController;
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

// Public bidding routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // New shared procedure-based bidding endpoints
    Route::post('/bids', [BiddingController::class, 'placeBid']);
    Route::post('/bids/validate', [BiddingController::class, 'validateBid']);
    Route::get('/bids/{bidId}', [BiddingController::class, 'getBidDetails']);
    Route::get('/user/bids', [BiddingController::class, 'getUserBids']);
    Route::get('/auctions/{auctionId}/bids', [BiddingController::class, 'getAuctionBids']);
});

// Inter-service Routes (for RPC and service-to-service communication)
Route::middleware('service.auth')->group(function () {
    // Legacy bidding routes (keep for backward compatibility)
    Route::post('/legacy/bids', [App\Http\Controllers\BiddingController::class, 'placeBid']);
    Route::get('/legacy/bids/{bidId}', [App\Http\Controllers\BiddingController::class, 'getBid']);
    Route::get('/legacy/users/{userId}/bids', [App\Http\Controllers\BiddingController::class, 'getUserBids']);
    Route::get('/legacy/auctions/{auctionId}/bids', [App\Http\Controllers\BiddingController::class, 'getAuctionBids']);
    Route::put('/legacy/bids/{bidId}/status', [App\Http\Controllers\BiddingController::class, 'updateBidStatus']);
    Route::get('/legacy/auctions/{auctionId}/statistics', [App\Http\Controllers\BiddingController::class, 'getBidStatistics']);
    
    // Legacy auction routes (keep for backward compatibility)
    Route::get('/legacy/auctions', [App\Http\Controllers\AuctionController::class, 'index']);
    Route::post('/legacy/auctions', [App\Http\Controllers\AuctionController::class, 'store']);
    Route::get('/legacy/auctions/{auctionId}', [App\Http\Controllers\AuctionController::class, 'show']);
    Route::put('/legacy/auctions/{auctionId}/status', [App\Http\Controllers\AuctionController::class, 'updateStatus']);
    Route::get('/legacy/auctions/{auctionId}/with-bids', [App\Http\Controllers\AuctionController::class, 'getAuctionWithBids']);

    // RPC endpoints for cross-service communication via shared procedures
    Route::post('/rpc/createBidRecord', function (Request $request) {
        $handler = app('App\RPC\Handlers\BiddingRpcHandler');
        return response()->json($handler->createBidRecord($request->all()));
    });
    
    Route::post('/rpc/getAuctionBids', function (Request $request) {
        $handler = app('App\RPC\Handlers\BiddingRpcHandler');
        return response()->json($handler->getAuctionBids($request->all()));
    });
    
    Route::post('/rpc/broadcastToAuction', function (Request $request) {
        $handler = app('App\RPC\Handlers\BiddingRpcHandler');
        return response()->json($handler->broadcastToAuction($request->all()));
    });
    
    Route::post('/rpc/validateBidPlacement', function (Request $request) {
        $handler = app('App\RPC\Handlers\BiddingRpcHandler');
        return response()->json($handler->validateBidPlacement($request->all()));
    });
    
    Route::post('/rpc/getBidDetails', function (Request $request) {
        $handler = app('App\RPC\Handlers\BiddingRpcHandler');
        return response()->json($handler->getBidDetails($request->all()));
    });
});
