<?php

use App\Http\Controllers\MasterWalletController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('master-wallet')->group(function () {
    Route::post('/', [MasterWalletController::class, 'create']); // Create a master wallet
    Route::get('/', [MasterWalletController::class, 'index']);  // Get all master wallets
});
