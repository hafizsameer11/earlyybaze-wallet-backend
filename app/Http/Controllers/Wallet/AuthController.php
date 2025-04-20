<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OtpVerificationRequst;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\NotificationService;
use App\Services\ResetPasswordService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $userService;
    protected $resetPasswordService,$notificationService;
    public function __construct(UserService $userService, ResetPasswordService $resetPasswordService, NotificationService $notificationService)
    {
        $this->userService = $userService;
        $this->resetPasswordService = $resetPasswordService;
        $this->notificationService = $notificationService;
    }
    public function register(RegisterRequest $request)
    {
        try {
            $user = $this->userService->registerUser($request->validated());
            return ResponseHelper::success($user, 'User registered successfully', 201);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function otpVerification(OtpVerificationRequst $request)
    {
        try {
            $user = $this->userService->verifyOtp($request->validated());
            return ResponseHelper::success($user, 'OTP verified successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function login(LoginRequest $request)
    {
        try {
            $user = $this->userService->login($request->validated());
            $userd = $user['user'];

            //check if users virtual accounts have been created if not than show a messae to use with error that pleae try again after 5-10 minutes
            if (count($user['virtual_accounts']) <= 7) {
                return ResponseHelper::error('Please try again after 5-10 minutes', 401);
            }
            Log::info('User Logged In:', [
                'user' => $user,
                'request_headers' => request()->headers->all()
            ]);

            $data = [
                'user' => $user['user'],
                'assets' => $user['virtual_accounts'],
                'token' => $user['token']
            ];
            $this->notificationService->sendToUserById($userd['id'], 'Login Notification', 'You logged in successfully');
            return ResponseHelper::success($data, 'User logged in successfully', 200);
        } catch (\Exception $e) {
            Log::error('Login Error:', ['error' => $e->getMessage()]);
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function adminLogin(Request $request)
    {
        try {
            $data = $this->userService->adminLogin($request->all());
            return ResponseHelper::success($data, 'Admin logged in successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function resendOtp(Request $request)
    {
        try {
            $user = $this->userService->resendOtp($request->email);
            return ResponseHelper::success($user, 'OTP resent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $user = $this->resetPasswordService->forgetPassword($request->email);
            return ResponseHelper::success($user, 'OTP resent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function verifyForgetPasswordOtp(ResetPasswordRequest $request)
    {
        try {
            $user = $this->resetPasswordService->verifyForgetPassswordOtp($request->email, $request->otp);
            return ResponseHelper::success($user, 'OTP verified successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function resetPassword(Request $request)
    {
        try {
            $user = $this->resetPasswordService->resetPassword($request->email, $request->password);
            return ResponseHelper::success($user, 'Password reset successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $this->userService->changePassword($request->old_password, $request->new_password);
            return ResponseHelper::success($user, 'Password changed successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
