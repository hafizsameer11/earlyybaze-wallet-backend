<?php

namespace App\Http\Controllers\V3;

use App\Helpers\ResponseHelper;
use App\Helpers\UserActivityHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\V3\V3EmailOtpVerificationRequest;
use App\Http\Requests\V3\V3LoginRequest;
use App\Http\Requests\V3\V3PhoneOtpVerificationRequest;
use App\Http\Requests\V3\V3RegisterRequest;
use App\Http\Requests\V3\V3ResendEmailOtpRequest;
use App\Http\Requests\V3\V3ResendPhoneOtpRequest;
use App\Services\NotificationService;
use App\Services\V3\V3AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class V3AuthController extends Controller
{
    public function __construct(
        private V3AuthService $service,
        private NotificationService $notificationService,
    ) {}

    public function register(V3RegisterRequest $request)
    {
        try {
            $user = $this->service->register($request->validated());

            return ResponseHelper::success($user, 'User registered successfully (v3)', 201);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function verifyEmailOtp(V3EmailOtpVerificationRequest $request)
    {
        try {
            $payload = $this->service->verifyEmailOtp($request->validated());

            return ResponseHelper::success($payload, 'Email OTP verified successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function verifyPhoneOtp(V3PhoneOtpVerificationRequest $request)
    {
        try {
            $payload = $this->service->verifyPhoneOtp($request->validated());

            return ResponseHelper::success($payload, 'Phone verified successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function resendEmailOtp(V3ResendEmailOtpRequest $request)
    {
        try {
            $this->service->resendEmailOtp($request->validated('email'));

            return ResponseHelper::success(null, 'Email OTP resent successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function sendPhoneCode(V3ResendPhoneOtpRequest $request)
    {
        try {
            $delivery = $this->service->sendPhoneCode($request->validated('email'));

            return ResponseHelper::success(
                ['phone_delivery' => $delivery],
                ($delivery['success'] ?? false)
                    ? 'Phone verification code sent successfully'
                    : 'Phone code generated but delivery failed — check server logs',
                ($delivery['success'] ?? false) ? 200 : 502
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    public function login(V3LoginRequest $request)
    {
        try {
            $result = $this->service->login($request->validated());
            $userData = $result['user'];

            $deviceId = $request->header('Device-ID');
            $userAgent = $request->header('User-Agent') ?? $request->userAgent();
            $ipAddress = $request->ip();

            if (! $deviceId) {
                $deviceId = sha1($userAgent.$ipAddress.$userData['id']);
            }

            $existingDevice = \App\Models\UserDevice::where('device_id', $deviceId)
                ->where('user_id', $userData['id'])
                ->first();

            if (! $existingDevice) {
                \App\Models\UserDevice::create([
                    'user_id' => $userData['id'],
                    'device_id' => $deviceId,
                    'device_name' => $request->header('Device-Name') ?? 'Unknown Device',
                    'device_type' => $request->header('Device-Type') ?? 'Unknown',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                $this->notificationService->notifyUser(
                    (int) $userData['id'],
                    'New device login',
                    'Your account was accessed from a new device.',
                    'security'
                );
            }

            $this->notificationService->notifyUser(
                (int) $userData['id'],
                'Login successful',
                'You logged in to your EarlyBaze wallet.',
                'auth'
            );

            UserActivityHelper::LoggedInUserActivity('User logged in (v3)');

            return ResponseHelper::success([
                'user' => $result['user'],
                'assets' => $result['virtual_accounts'],
                'token' => $result['token'],
                'is_number_verified' => $result['is_number_verified'],
                'phone_verification_required' => $result['phone_verification_required'],
            ], 'User logged in successfully (v3)', 200);
        } catch (\Exception $e) {
            Log::error('V3 login error:', ['error' => $e->getMessage()]);

            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
