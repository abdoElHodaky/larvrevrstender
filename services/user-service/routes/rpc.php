<?php

use App\RPC\Procedures\KycProcedure;
use App\RPC\Procedures\UserProcedure;
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
    UserProcedure::class,
    KycProcedure::class,
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging']);
