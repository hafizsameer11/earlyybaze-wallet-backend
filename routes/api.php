<?php

use App\Http\Controllers\FeeController;
use App\Http\Controllers\MasterWalletController;
use App\Http\Controllers\Wallet\AuthController;
use App\Http\Controllers\Wallet\BankAccountController;
use App\Http\Controllers\Wallet\UserController;
use App\Http\Controllers\WalletCurrencyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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


Route::get('/optimize-app', function () {
    Artisan::call('optimize:clear'); // Clears cache, config, route, and view caches
    Artisan::call('cache:clear');    // Clears application cache
    Artisan::call('config:clear');   // Clears configuration cache
    Artisan::call('route:clear');    // Clears route cache
    Artisan::call('view:clear');     // Clears compiled Blade views
    Artisan::call('config:cache');   // Rebuilds configuration cache
    Artisan::call('route:cache');    // Rebuilds route cache
    Artisan::call('view:cache');     // Precompiles Blade templates
    Artisan::call('optimize');       // Optimizes class loading

    return "Application optimized and caches cleared successfully!";
});
Route::get('/migrate', function () {
    Artisan::call('migrate');
    return response()->json(['message' => 'Migration successful'], 200);
});

Route::get('/unath', function () {
    return response()->json(['message' => 'Unauthenticated'], 401);
})->name('login');

Route::post('/create-wallet-currency', [WalletCurrencyController::class, 'create']);
Route::post('/update-wallet-currency/{id}', [WalletCurrencyController::class, 'update']);
Route::get('/wallet-currencies', [WalletCurrencyController::class, 'index']);
Route::prefix('master-wallet')->group(function () {
    Route::post('/', [MasterWalletController::class, 'create']); // Create a master wallet
    Route::get('/', [MasterWalletController::class, 'index']);  // Get all master wallets
});

//Customer route

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']); // Register a user
    Route::post('/otp-verification', [AuthController::class, 'otpVerification']); // Verify OTP
    Route::post('/login', [AuthController::class, 'login']); // Login
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']); // Resend OTP you can call verify OTP again

    Route::post('/forget-password', [AuthController::class, 'forgetPassword']); // Forget password
    Route::post('/verify-forget-password-otp', [AuthController::class, 'verifyForgetPasswordOtp']); // Verify forget password OTP
    Route::post('/reset-password', [AuthController::class, 'resetPassword']); // Reset password


});
//Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    //Fee Module
    Route::prefix('fee')->group(function () {
        Route::post('/create', [FeeController::class, 'create']); // Create a fee
        Route::post('/update/{id}', [FeeController::class, 'update']); // Update a fee
        Route::get('/get-by-type/{type}', [FeeController::class, 'getByType']); // Get fee by type

    });


    Route::get('/get-bank-account', [BankAccountController::class, 'getForUser']);
    Route::put('/update-bank-account/{id}', [BankAccountController::class, 'update']);
    Route::delete('/delete-bank-account/{id}', [BankAccountController::class, 'delete']);
    Route::post('/create-bank-account', [BankAccountController::class, 'store']);

    Route::get('/user-accounts', [UserController::class, 'getUserAccountsFromApi']);
    Route::post('/user/set-pin', [UserController::class, 'setPin']);
    Route::post('/user/verify-pin', [UserController::class, 'verifyPin']);
    Route::get('/user/balance', [UserController::class, 'getUserBalance']);
    Route::get('/user/assets', [UserController::class, 'getUserAssets']);
    //routes for selecting currency with wallet
    Route::get('/user/wallet-currencies', [UserController::class, 'walletCurrenciesforUser']); //wallet currencies for user that have balance and associated virtual account
    Route::get('/user/all-wallet-currencies', [UserController::class, 'allwalletCurrenciesforUser']); //wallet currencies for user that have balance and associated virtual account
    Route::get('/user/networks/{currency_id}', [WalletCurrencyController::class, 'getNetworks']); //get networks for a currency
    Route::get('/user/deposit-address/{currency}/{network}', [UserController::class, 'getDepositAddress']); //get deposit address for a currency

});
//non auth routes
Route::get('/find-bank-account/{id}', [BankAccountController::class, 'find']);
