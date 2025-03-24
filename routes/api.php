<?php

use App\Http\Controllers\Admin\AmlRuleController;
use App\Http\Controllers\Admin\InAppBannerController;
use App\Http\Controllers\Admin\InAppNotificationController;
use App\Http\Controllers\Admin\MaintenanceServiceController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\PayoutRuleController;
use App\Http\Controllers\Admin\RefferalManagementController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TradeLimitController;
use App\Http\Controllers\Admin\TransactionManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WalletManagementController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\MarketDataController;
use App\Http\Controllers\MasterWalletController;
use App\Http\Controllers\ReferalPaymentController;
use App\Http\Controllers\RefferalEarningController;
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
Route::post('/admin/login', [AuthController::class, 'adminLogin']);

//Authenticated routes for user
Route::middleware('auth:sanctum')->group(function () {


    Route::post('/user/add-testing-balance', [UserController::class, 'addTestingBalance']);
    //Fee Module
    Route::prefix('fee')->group(function () {
        Route::post('/create', [FeeController::class, 'create']); // Create a fee
        Route::post('/update/{id}', [FeeController::class, 'update']); // Update a fee
        Route::get('/get-by-type/{type}', [FeeController::class, 'getByType']); // Get fee by type
        Route::get('/get-all', [FeeController::class, 'getAll']); // Get fee by type

    });
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
        Route::post('/calculate-exchange-rate', [ExchangeRateController::class, 'calculateExchangeRate']);
    });

    Route::post('/wallet/internal-transfer', [TransactionController::class, 'sendInternalTransaction']);
    Route::post('/wallet/on-chain-transfer', [TransactionController::class, 'sendOnChain']);
    Route::post('wallet/swap', [TransactionController::class, 'swap']);
    Route::get('wallet/single-swap/{id}', [TransactionController::class, 'singleSwapTransaction']);

    Route::post('wallet/buy', [TransactionController::class, 'buy']);
    Route::get('wallet/single-buy/{id}', [TransactionController::class, 'singleBuyTransaction']);
    Route::post('wallet/attach-slip/{id}', [TransactionController::class, 'attachSlip']);

    Route::get('transaction/get-all', [TransactionController::class, 'getTransactionsForUser']);

    Route::get('refferal/get-all', [RefferalEarningController::class, 'getForAuthUser']);
    Route::get('user-asset-transaction', [TransactionController::class, 'getUserAssetTransactions']);
    Route::get('notification/get-all', [InAppNotificationController::class, 'index']); // Get all notifications
});
//non auth routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin')->group(function () {

        Route::get('admin-virtual-accounts', [UserManagementController::class, 'adminVirtualAccounts']);
        Route::get('referal_payments', [ReferalPaymentController::class, 'index']); // Get all records
        Route::post('referal_payments', [ReferalPaymentController::class, 'store']); // Create new record
        Route::get('referal_payments/{id}', [ReferalPaymentController::class, 'show']); // Get single record
        Route::put('referal_payments/{id}', [ReferalPaymentController::class, 'update']); // Update record
        Route::delete('referal_payments/{id}', [ReferalPaymentController::class, 'destroy']); // Delete record
        //usermanagement
        Route::get('/user-management', [UserManagementController::class, 'getUserManagementData']);
        Route::get('/user-management/user-detail/{userId}', [UserManagementController::class, 'getUserDetails']);
        Route::get('/user-management/user-banks/{userId}', [UserManagementController::class, 'getBanksForUser']);
        Route::get('/user-management/virtualWallets/{userId}', [UserManagementController::class, 'getUserVirtualAccounts']);
        Route::post('/user-management/update-profile/{userId}', [UserController::class, 'UpdateUserProfileByAdmin']);
        Route::post('/create-user', [UserManagementController::class, 'createUser']);
        //banners
        Route::get('/banners', [InAppBannerController::class, 'index']);
        Route::get('/banners/{id}', [InAppBannerController::class, 'show']);
        Route::post('/banners', [InAppBannerController::class, 'create']);
        Route::post('/banners/{id}', [InAppBannerController::class, 'update']);
        Route::delete('/banners/{id}', [InAppBannerController::class, 'delete']);

        //reffer management data
        Route::get('/referal-management', [RefferalManagementController::class, 'getRefferalManagement']);
        Route::get('refferal/get-for-user/{id}', [RefferalEarningController::class, 'getForUser']);

        Route::prefix('InAppNotifications')->group(function () {
            Route::get('/get-all', [InAppNotificationController::class, 'index']); // Get all notifications
            Route::get('/get-single/{id}', [InAppNotificationController::class, 'show']); // Get single notification
            Route::post('/create', [InAppNotificationController::class, 'store']); // Create notification
            Route::post('/update/{id}', [InAppNotificationController::class, 'update']); // Update notification
            Route::delete('/delete/{id}', [InAppNotificationController::class, 'destroy']); // Delete notification
        });
        Route::prefix('transactions')->group(function () {
            Route::get('/get-all', [TransactionManagementController::class, 'getAll']);
            Route::get('/get-for-user/{id}', [TransactionManagementController::class, 'getTransactionsForUser']);
            Route::get('/get-singe/swap/{id}', [TransactionManagementController::class, 'getSingleSwapTransaction']);
            Route::get('/get-singe/buy/{id}', [TransactionManagementController::class, 'getSingleBuyTransaction']);
            Route::get('/get-singe/internal-send/{id}', [TransactionManagementController::class, 'getSingleInternalSendTransaction']);
            Route::get('/get-single/internal-receive/{id}', [TransactionManagementController::class, 'getSingleInternalReceiveTransaction']);
            Route::get('/get-single/receive/{id}', [TransactionManagementController::class, 'getSingleReceiveTransaction']);
            //withdraw single
            Route::get('/get-single/withdraw/{id}', [TransactionManagementController::class, 'getSingleWithdrawTransaction']);
            Route::get('/get-single/withdraw/{id}', [TransactionManagementController::class, 'ReferalPaymentController@destroy']);
        });
        Route::prefix('withdrawRequest')->group(function (): void {
            Route::get('/get-all', [WithdrawController::class, 'getAllwithdrawRequests']);
            Route::get('/get-single/{id}', [WithdrawController::class, 'getwithdrawRequestStatus']);
            Route::post('/withdraw/update-status/{id}', [WithdrawController::class, 'updateStatus']);
        });
        Route::prefix('kyc')->group(function (): void {
            Route::get('/get-all', [KycController::class, 'getAll']);
            Route::post('/update-status/{id}', [KycController::class, 'updateStatus']);
        });
        Route::prefix('walletmanagement')->group(function (): void {
            Route::get('/get-virtual-wallet', [WalletManagementController::class, 'getVirtualWalletData']);
            // Route::post('/update-status/{id}', [WalletManagementController::class, 'updateStatus']);
        });
        Route::prefix('trade-limits')->group(function () {
            Route::get('/get-all', [TradeLimitController::class, 'index']);
            Route::post('/create', [TradeLimitController::class, 'store']);
            Route::get('/get-single/{id}', [TradeLimitController::class, 'show']);
            Route::post('/update/{id}', [TradeLimitController::class, 'update']);
            Route::delete('/delete/{id}', [TradeLimitController::class, 'destroy']);
            Route::get('/type/{type}', [TradeLimitController::class, 'getByType']); // Custom method
        });
        Route::prefix('aml-rules')->group(function () {
            Route::get('/get-all', [AmlRuleController::class, 'index']);
            Route::post('/create', [AmlRuleController::class, 'store']);
            Route::get('/get-single/{id}', [AmlRuleController::class, 'show']);
            Route::post('/update/{id}', [AmlRuleController::class, 'update']);
            Route::delete('/delete/{id}', [AmlRuleController::class, 'destroy']);
            Route::get('/transaction-type/{type}', [AmlRuleController::class, 'getByTransactionType']);
        });
        Route::get('/market-data', [MarketDataController::class, 'index']);
        Route::prefix('maintenance-services')->group(function () {
            Route::get('/get-all', [MaintenanceServiceController::class, 'index']);          // Get all services
            Route::post('/create', [MaintenanceServiceController::class, 'store']);          // Create new service
            Route::get('/get-single/{id}', [MaintenanceServiceController::class, 'show']);   // Get single service by ID
            Route::post('/update/{id}', [MaintenanceServiceController::class, 'update']);    // Update service
            Route::delete('/delete/{id}', [MaintenanceServiceController::class, 'destroy']); // Delete service
        });

        Route::prefix('roles')->group(function () {
            Route::get('/get-all', [RoleController::class, 'index']);
            Route::post('/create', [RoleController::class, 'store']);
            Route::get('/get-single/{id}', [RoleController::class, 'show']);
            Route::post('/update/{id}', [RoleController::class, 'update']);
            Route::delete('/delete/{id}', [RoleController::class, 'destroy']);
            Route::get('/{id}/permissions', [RoleController::class, 'getRoleModulePermissions']);
            Route::post('/{id}/modules', [RoleController::class, 'assignModules']);
        });

        Route::prefix('modules')->group(function () {
            Route::get('/get-all', [ModuleController::class, 'index']);
            Route::post('/create', [ModuleController::class, 'store']);
            Route::delete('/delete/{id}', [ModuleController::class, 'destroy']);
        });
        Route::prefix('payout-rules')->group(function () {
            Route::get('/get-all', [PayoutRuleController::class, 'index']);
            Route::post('/create', [PayoutRuleController::class, 'store']);
            Route::get('/get-single/{id}', [PayoutRuleController::class, 'show']);
            Route::post('/update/{id}', [PayoutRuleController::class, 'update']);
            Route::delete('/delete/{id}', [PayoutRuleController::class, 'destroy']);
            Route::get('/get-by-event/{event}', [PayoutRuleController::class, 'getByEvent']);
        });
        Route::prefix('support')->group(function () {
            Route::get('/get-non-users', [UserManagementController::class, 'getNonUsers']);
            Route::get('/get-all-tickets', [SupportController::class, 'getAllTickets']);
            Route::post('/assign-to-agent', [SupportController::class, 'assignToAgent']); // {ticket_id, user_id}
            Route::post('/create-reply-by-admin', [SupportController::class, 'createReplyByAdmin']);
            Route::get('/get-ticket/{id}', [SupportController::class, 'getTicket']);
        });
    });
});
Route::get('/find-bank-account/{id}', [BankAccountController::class, 'find']);
