<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OtpVerificationRequst;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\ResetPasswordService;
use App\Services\UserService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $userService;
    protected $resetPasswordService;
    public function __construct(UserService $userService, ResetPasswordService $resetPasswordService)
    {
        $this->userService = $userService;
        $this->resetPasswordService = $resetPasswordService;
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
            $token = $user->createToken('auth_token')->plainTextToken;
            $data = [
                'user' => $user,
                'token' => $token
            ];
            return ResponseHelper::success($data, 'User registered successfully', 200);
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




}
