<?php

use App\RPC\Procedures\NotificationProcedure;
use Illuminate\Support\Facades\Route;

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
    NotificationProcedure::class,
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging']);
