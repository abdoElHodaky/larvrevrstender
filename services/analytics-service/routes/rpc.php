<?php

use Sajya\Server\Route;
use App\RPC\Procedures\AnalyticsProcedure;

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
    AnalyticsProcedure::class,
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging']);
