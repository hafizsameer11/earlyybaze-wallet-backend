<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\BankAccountService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    protected $userService, $bankAccountService;
    public function __construct(UserService $userService, BankAccountService $bankAccountService)
    {
        $this->userService = $userService;
        $this->bankAccountService = $bankAccountService;
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
}
