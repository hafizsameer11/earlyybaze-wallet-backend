<?php

use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\MasterWalletController;
use App\Http\Controllers\Wallet\AuthController;
use App\Http\Controllers\Wallet\BankAccountController;
use App\Http\Controllers\Wallet\SupportController;
use App\Http\Controllers\Wallet\TransactionController;
use App\Http\Controllers\Wallet\UserController;
use App\Http\Controllers\WalletCurrencyController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WithdrawController;
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
Route::get('/migrate/rollback', function () {
    Artisan::call('migrate:rollback');
    return response()->json(['message' => 'Migration rollback successfully'], 200);
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

    Route::post('/forget-password', [AuthController::class, 'forgotPassword']); // Forget password
    Route::post('/verify-forget-password-otp', [AuthController::class, 'verifyForgetPasswordOtp']); // Verify forget password OTP
    Route::post('/reset-password', [AuthController::class, 'resetPassword']); // Reset password


});
Route::post('/user/set-pin', [UserController::class, 'setPin']);
Route::post('/user/verify-pin', [UserController::class, 'verifyPin']);
Route::post('/webhook', [WebhookController::class, 'webhook']);
//Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    //testing route for adding balance
    Route::post('/user/add-testing-balance', [UserController::class, 'addTestingBalance']);
    //Fee Module
    Route::prefix('fee')->group(function () {
        Route::post('/create', [FeeController::class, 'create']); // Create a fee
        Route::post('/update/{id}', [FeeController::class, 'update']); // Update a fee
        Route::get('/get-by-type/{type}', [FeeController::class, 'getByType']); // Get fee by type

    });
    //withdrawal routes
    Route::post('/withdraw/create', [WithdrawController::class, 'create']);
    Route::get('/withdraw-request-status/{id}', [WithdrawController::class, 'getwithdrawRequestStatus']);
    Route::get('/withdraw-requests', [WithdrawController::class, 'getWithdrawRequestforAuthenticatedUser']);

    Route::get('/get-bank-account', [BankAccountController::class, 'getForUser']);
    Route::put('/update-bank-account/{id}', [BankAccountController::class, 'update']);
    Route::delete('/delete-bank-account/{id}', [BankAccountController::class, 'delete']);
    Route::post('/create-bank-account', [BankAccountController::class, 'store']);

    Route::get('/user-accounts', [UserController::class, 'getUserAccountsFromApi']);
    Route::get('/user/balance', [UserController::class, 'getUserBalance']);
    Route::get('/user/assets', [UserController::class, 'getUserAssets']);
    //routes for selecting currency with wallet
    Route::get('/user/wallet-currencies', [UserController::class, 'walletCurrenciesforUser']); //wallet currencies for user that have balance and associated virtual account
    Route::get('/user/all-wallet-currencies', [UserController::class, 'allwalletCurrenciesforUser']); //wallet currencies for user that have balance and associated virtual account
    Route::get('/user/networks/{currency_id}', [WalletCurrencyController::class, 'getNetworks']); //get networks for a currency
    Route::get('/user/deposit-address/{currency}/{network}', [UserController::class, 'getDepositAddress']); //get deposit address for a currency
    //profile edit and user details routes
    Route::get('/user/details', [UserController::class, 'getUserDetails']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::post('/user/update-profile', [UserController::class, 'UpdateUserProfile']);

    Route::prefix('/kyc')->group(function () {
        Route::post('/create', [KycController::class, 'create']);
        Route::get('/get', [KycController::class, 'getKycForUser']);
    });
    Route::prefix('support')->group(function () {
        Route::post('/create-ticket', [SupportController::class, 'crateTicket']);
        Route::get('/get-tickets', [SupportController::class, 'getTicketsForAuthUser']);
        Route::get('/get-ticket/{id}', [SupportController::class, 'getTicket']);
        Route::post('/send-reply', [SupportController::class, 'createReplyByUser']);
    });

    Route::prefix('exchange-rate')->group(function () {
        Route::get('/get-exchange-rates', [ExchangeRateController::class, 'index']);
        Route::post('/create-exchange-rate', [ExchangeRateController::class, 'store']);
        Route::get('/get-exchange-rate/{currency}', [ExchangeRateController::class, 'getByCurrency']);
    });

    Route::post('/wallet/internal-transfer', [TransactionController::class, 'sendInternalTransaction']);
    Route::post('/wallet/on-chain-transfer', [TransactionController::class, 'sendOnChain']);
    Route::post('wallet/swap', [TransactionController::class, 'swap']);
    Route::post('wallet/single-swap/{id}', [TransactionController::class, 'singleSwap']);
    Route::get('transaction/get-all', [TransactionController::class, 'getTransactionsForUser']);
});
//non auth routes
Route::get('/find-bank-account/{id}', [BankAccountController::class, 'find']);
