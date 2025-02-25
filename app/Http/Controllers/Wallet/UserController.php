<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PinRequest;
use App\Models\UserAccount;
use App\Services\UserAccountService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected $userService;
    protected $userAccountService;
    public function __construct(UserService $userService, UserAccountService $userAccountService)
    {
        $this->userService = $userService;
        $this->userAccountService = $userAccountService;
    }

    public function setpin(PinRequest $request)
    {
        try {
            $user = $this->userService->setPin($request->email, $request->pin);
            return ResponseHelper::success($user, 'Pin set successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function verifyPin(Request $request)
    {
        try {
            $user = $this->userService->verifyPin($request->pin);
            return ResponseHelper::success($user, 'Pin verified successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserAccountsFromApi()
    {
        try {
            $user = $this->userService->getUserAccounts();
            return ResponseHelper::success($user, 'User accounts fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getUserBalance()
    {
        try {
            $user = $this->userAccountService->getBalance();
            return ResponseHelper::success($user, 'User balance fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserAssets()
    {
        try {
            $user = $this->userService->getUserAssets();
            return ResponseHelper::success($user, 'User assets fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function walletCurrenciesforUser()
    {
        try {
            $walletCurrencies = $this->userService->getwalletcurrenciesforuser();
            return ResponseHelper::success($walletCurrencies, 'Wallet Currencies retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function getDepositAddress($currency, $network)
    {
        try {
            $depositAddress = $this->userService->getDepostiAddress($currency, $network);
            return ResponseHelper::success($depositAddress, 'Deposit Address retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function allwalletCurrenciesforUser()
    {
        try {
            $walletCurrencies = $this->userService->allwalletcurrenciesforuser();
            return ResponseHelper::success($walletCurrencies, 'Wallet Currencies retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function addTestingBalance(Request $request)
    {
        try {
            $user = Auth::user();
            $userAccount = UserAccount::where('user_id', $user->id)->first();
            $userAccount->naira_balance += $request->naira_balance;
            return ResponseHelper::success($user, 'Testing balance added successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
