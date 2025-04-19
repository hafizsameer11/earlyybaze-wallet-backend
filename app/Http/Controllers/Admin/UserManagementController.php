<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccountRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\BankAccountService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    protected $userService, $bankAccountService;
    public function __construct(UserService $userService, BankAccountService $bankAccountService)
    {
        $this->userService = $userService;
        $this->bankAccountService = $bankAccountService;
    }



    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user) {
            // Append timestamp to avoid unique constraint issues
            $timestamp = now()->timestamp;

            // Update email before soft-deleting
            $user->email = $user->email . '-deleted-' . $timestamp;
            $user->save();

            // Perform soft delete
            $user->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'User soft deleted and email updated',
        ]);
    }
    public function blockUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = false;
        $user->save();
        return response()->json([
            'status' => 'success',
            'message' => 'User blocked',
        ]);
    }
    public function getUserManagementData()
    {
        try {
            $data = $this->userService->getUserManagementData();
            return ResponseHelper::success($data, 'User details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserDetails($userId)
    {
        try {
            $data = $this->userService->userDetails($userId);
            return ResponseHelper::success($data, 'User details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getBanksForUser($userId)
    {
        try {
            if (!$userId) {
                return ResponseHelper::error('User id is required', 500);
            }
            $data = $this->bankAccountService->getforUser($userId);
            return ResponseHelper::success($data, 'Bank details fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function adminVirtualAccounts()
    {
        try {
            $user = User::where('role', 'admin')->first();
            $data = $this->userService->getUserVirtualAccounts($user->id);
            return ResponseHelper::success($data, 'User virtual accounts fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserVirtualAccounts($userId)
    {
        try {
            $data = $this->userService->getUserVirtualAccounts($userId);
            return ResponseHelper::success($data, 'User virtual accounts fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getNonUsers()
    {
        try {
            $data = $this->userService->getNonUsers();
            return ResponseHelper::success($data, 'Non users fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function createUser(RegisterRequest $request)
    {
        try {
            $user = $this->userService->createUser($request->validated());
            return ResponseHelper::success($user, 'User created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function createBankAccount(BankAccountRequest $request, $userId)
    {
        try {
            $data = $this->bankAccountService->createBankAccount($request->validated(), $userId);
            return ResponseHelper::success($data, 'Bank account created successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
    public function getUserBalances()
    {
        try {
            $data = $this->userService->getUserBalances();
            return ResponseHelper::success($data, 'User balances fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getBalanceByCurrency($currencyId)
    {
        try {
            $data = $this->userService->getBalanceByCurrency($currencyId);
            return ResponseHelper::success($data, 'User balances fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
