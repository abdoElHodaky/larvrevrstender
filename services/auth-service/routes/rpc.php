<?php

use Sajya\Server\Route;
use App\RPC\Procedures\AuthProcedure;

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
    AuthProcedure::class,
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging']);
