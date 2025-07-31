<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PinRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\UserAccount;
use App\Services\UserAccountService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserActivity;

class UserController extends Controller
{
    protected $userService;
    protected $userAccountService;
    public function __construct(UserService $userService, UserAccountService $userAccountService)
    {
        $this->userService = $userService;
        $this->userAccountService = $userAccountService;
    }
    // public fnct
    public function getUserDetails()
    {
        try {
            $user = $this->userService->getUserDetails();
            return ResponseHelper::success($user, 'User details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function UpdateUserProfile(UpdateProfileRequest $request)
    {
        try {
            $user = Auth::user();
            $user = $this->userService->updateUserProfile($request->validated(), $user->id);
            return ResponseHelper::success($user, 'User profile updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function UpdateUserProfileByAdmin(UpdateProfileRequest $request, $userId)
    {
        try {

            $user = $this->userService->updateUserProfile($request->validated(), $userId);
            return ResponseHelper::success($user, 'User profile updated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function setpin(PinRequest $request)
    {
        try {
            $data = $request->validated();
            $email = $data['email'];
            Log::info("data $email");
            $user = $this->userService->setPin($data['email'], $data['pin']);
            return ResponseHelper::success($user, 'Pin set successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function verifyPin(Request $request)
    {
        try {
            $user = $this->userService->verifyPin($request->pin, $request->email);
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
    public function allwalletCurrenciesforUser($isBuy)
    {
        try {
            // $isBuy = $request->all();
            Log::info('Is Buy: ' . $isBuy);
            if (!$isBuy) {
                $walletCurrencies = $this->userService->getwalletcurrenciesforuser();
                return ResponseHelper::success($walletCurrencies, 'Wallet Currencies retrieved successfully', 200);
            }

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
            $userAccount->save();
            return ResponseHelper::success($userAccount, 'Testing balance added successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function setFcmToken(Request $request)
    {
        $userId = Auth::user()->id;
        Log::info("FC token set: " . $request->fcmToken);
        $fcmToken = $request->fcmToken;

        $user = User::where('id', $userId)->first();
        $user->fcmToken = $fcmToken;
        $user->save();
        return response()->json(['status' => 'success', 'message' => 'FCM token set successfully'], 200);
    }
    public function validateEmail(Request $request)
    {
        try {
            $email = $request->email;
            $user = User::where('email', $email)->first();
            if(!$user) {
                return ResponseHelper::error('Email not found', 404);
            }
            // if ($user) {
            //     return ResponseHelper::error('Email already exists', 400);
            // }
            return ResponseHelper::success($user, 'Email validated successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserActivity(){
        $userId = Auth::user()->id;
        $userActivity = UserActivity::where('user_id', $userId)->get();
        return ResponseHelper::success($userActivity, 'User activity retrieved successfully', 200);
    }
}
