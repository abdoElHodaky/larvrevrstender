<?php

use App\RPC\Procedures\OrderProcedure;
use Illuminate\Support\Facades\Route;

// use App\RPC\Procedures\EnhancedOrderProcedure;

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

Route::rpc('/', [
    OrderProcedure::class,
    // EnhancedOrderProcedure::class, // TODO: Review if this is needed or can be merged with OrderProcedure
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging']);
