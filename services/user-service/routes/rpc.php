<?php

use App\RPC\Procedures\KycProcedure;
use App\RPC\Procedures\UserProcedure;

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
        UserProcedure::class,
        KycProcedure::class,
    ])->middleware(['rpc.auth', 'rpc.correlation', 'rpc.performance', 'rpc.logging']);
}
