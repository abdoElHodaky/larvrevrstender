<?php

use App\RPC\Procedures\VinOcrProcedure;
use Sajya\Server\Route;

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
    VinOcrProcedure::class,
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging']);
