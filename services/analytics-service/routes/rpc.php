<?php

use App\RPC\Procedures\AnalyticsProcedure;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RPC Routes
|--------------------------------------------------------------------------
|
| Here is where you can register RPC routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "rpc" middleware group.
|
*/

Route::rpc('/', [
    AnalyticsProcedure::class,
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging', 'rpc.auth']);
