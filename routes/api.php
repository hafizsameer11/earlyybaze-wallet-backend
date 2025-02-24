<?php

use App\Http\Controllers\MasterWalletController;
use App\Http\Controllers\Wallet\AuthController;
use App\Http\Controllers\Wallet\BankAccountController;
use App\Http\Controllers\Wallet\UserController;
use App\Http\Controllers\WalletCurrencyController;
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
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']); // Register a user
    Route::post('/otp-verification', [AuthController::class, 'otpVerification']); // Verify OTP
    Route::post('/login', [AuthController::class, 'login']); // Login
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']); // Resend OTP you can call verify OTP again

    Route::post('/forget-password', [AuthController::class, 'forgetPassword']); // Forget password
    Route::post('/verify-forget-password-otp', [AuthController::class, 'verifyForgetPasswordOtp']); // Verify forget password OTP
    Route::post('/reset-password', [AuthController::class, 'resetPassword']); // Reset password
});
Route::prefix('master-wallet')->group(function () {
    Route::post('/', [MasterWalletController::class, 'create']); // Create a master wallet
    Route::get('/', [MasterWalletController::class, 'index']);  // Get all master wallets
});

Route::post('/create-wallet-currency', [WalletCurrencyController::class, 'create']);
Route::prefix('user')->group(function () {
    Route::post('/set-pin', [UserController::class, 'setPin']);
    Route::post('/verify-pin', [UserController::class, 'verifyPin']);
    //bank account routes


});

//authenticated route
Route::get('/find-bank-account/{id}', [BankAccountController::class, 'find']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/get-bank-account', [BankAccountController::class, 'getForUser']);
    Route::put('/update-bank-account/{id}', [BankAccountController::class, 'update']);
    Route::delete('/delete-bank-account/{id}', [BankAccountController::class, 'delete']);
    Route::post('/create-bank-account', [BankAccountController::class, 'store']);

    //user account api testing
    Route::get('/user-accounts', [UserController::class, 'getUserAccountsFromApi']);
});
