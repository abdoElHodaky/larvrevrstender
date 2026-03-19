<?php

use App\RPC\Procedures\PaymentProcedure;

/*
|--------------------------------------------------------------------------
| RPC Routes
|--------------------------------------------------------------------------
|
| Here is where you can register RPC procedures for your application.
| These procedures are loaded by the RpcServiceProvider within a group
| which contains the "rpc" middleware group.
|
*/

// Check if Sajya Server is available before registering routes
if (class_exists('Sajya\Server\Route')) {
    \Sajya\Server\Route::rpc('/', [
        PaymentProcedure::class,
    ])->middleware(['rpc.correlation', 'rpc.ratelimit', 'rpc.performance', 'rpc.logging', 'rpc.auth']);
}
