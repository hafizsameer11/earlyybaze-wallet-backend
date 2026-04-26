<?php

use App\Http\Controllers\DevTatumBtcWalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Lightweight API routes (no "api" middleware group)
|--------------------------------------------------------------------------
|
| Registered with an empty middleware stack so ThrottleRequests does not run.
| Throttle uses the default cache store; with CACHE_DRIVER=database that hits
| MySQL even when the controller never touches the DB.
|
*/

/*
|--------------------------------------------------------------------------
| Wallet flow v2 — dev-only (Tatum BTC wallet + v4 subscription smoke test)
|--------------------------------------------------------------------------
| POST /api/dev/tatum/btc-wallet-v4-subscription
| Gate: config tatum.dev_btc_wallet_v4_endpoint_enabled
|--------------------------------------------------------------------------
*/
Route::post('/dev/tatum/btc-wallet-v4-subscription', [DevTatumBtcWalletController::class, 'createWithV4IncomingNativeSubscription']);
