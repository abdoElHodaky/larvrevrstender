<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Api\AuctionController;
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
        'service' => 'auction-service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// Public auction routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // New shared procedure-based auction endpoints
    Route::post('/auctions', [AuctionController::class, 'createAuction']);
    Route::get('/auctions/{auctionId}', [AuctionController::class, 'getAuctionDetails']);
    Route::post('/auctions/{auctionId}/end', [AuctionController::class, 'endAuction']);
    Route::get('/auctions', [AuctionController::class, 'getActiveAuctions']);
    Route::get('/user/auctions', [AuctionController::class, 'getUserAuctions']);
    Route::put('/auctions/{auctionId}', [AuctionController::class, 'updateAuction']);
});

// Inter-service Routes (for RPC and service-to-service communication)
Route::middleware('service.auth')->group(function () {
    // Legacy auction routes (keep for backward compatibility)
    Route::post('/legacy/auctions', [App\Http\Controllers\AuctionController::class, 'store']);
    Route::get('/legacy/auctions/{auction}', [App\Http\Controllers\AuctionController::class, 'show']);
    Route::put('/legacy/auctions/{auction}', [App\Http\Controllers\AuctionController::class, 'update']);
    Route::delete('/legacy/auctions/{auction}', [App\Http\Controllers\AuctionController::class, 'destroy']);
    Route::get('/legacy/auctions/{auction}/bids', [App\Http\Controllers\AuctionController::class, 'getBids']);
    Route::post('/legacy/auctions/{auction}/close', [App\Http\Controllers\AuctionController::class, 'close']);

    // Legacy bidding routes (keep for backward compatibility)
    Route::post('/legacy/bids', [App\Http\Controllers\BiddingController::class, 'placeBid']);
    Route::get('/legacy/bids/{bidId}', [App\Http\Controllers\BiddingController::class, 'getBid']);
    Route::get('/legacy/users/{userId}/bids', [App\Http\Controllers\BiddingController::class, 'getUserBids']);
    Route::get('/legacy/auctions/{auctionId}/bids', [App\Http\Controllers\BiddingController::class, 'getAuctionBids']);
    Route::put('/legacy/bids/{bidId}/status', [App\Http\Controllers\BiddingController::class, 'updateBidStatus']);

    // RPC endpoints for cross-service communication via shared procedures
    Route::post('/rpc/validateAuctionActive', function (Request $request) {
        $handler = app('App\RPC\Handlers\AuctionRpcHandler');
        return response()->json($handler->validateAuctionActive($request->all()));
    });
    
    Route::post('/rpc/createAuctionRecord', function (Request $request) {
        $handler = app('App\RPC\Handlers\AuctionRpcHandler');
        return response()->json($handler->createAuctionRecord($request->all()));
    });
    
    Route::post('/rpc/getAuctionDetails', function (Request $request) {
        $handler = app('App\RPC\Handlers\AuctionRpcHandler');
        return response()->json($handler->getAuctionDetails($request->all()));
    });
    
    Route::post('/rpc/updateAuctionWithBid', function (Request $request) {
        $handler = app('App\RPC\Handlers\AuctionRpcHandler');
        return response()->json($handler->updateAuctionWithBid($request->all()));
    });
    
    Route::post('/rpc/updateAuctionStatus', function (Request $request) {
        $handler = app('App\RPC\Handlers\AuctionRpcHandler');
        return response()->json($handler->updateAuctionStatus($request->all()));
    });
    
    Route::post('/rpc/updateAuctionSettlement', function (Request $request) {
        $handler = app('App\RPC\Handlers\AuctionRpcHandler');
        return response()->json($handler->updateAuctionSettlement($request->all()));
    });
    
    Route::post('/rpc/getExpiredAuctions', function (Request $request) {
        $handler = app('App\RPC\Handlers\AuctionRpcHandler');
        return response()->json($handler->getExpiredAuctions($request->all()));
    });
});
