<?php

namespace App\Http\Controllers\Wallet;

use App\Helpers\ResponseHelper;
use App\Helpers\UserActivityHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OtpVerificationRequst;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\UserNotification;
use App\Services\AdminAuthService;
use App\Services\NotificationService;
use App\Services\ResetPasswordService;
use App\Services\UserService;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $userService;
    protected $resetPasswordService, $notificationService;
    public function __construct(UserService $userService, ResetPasswordService $resetPasswordService, NotificationService $notificationService, private AdminAuthService $service)
    {
        $this->userService = $userService;
        $this->resetPasswordService = $resetPasswordService;
        $this->notificationService = $notificationService;
    }
    public function sendNotification($userId)
    {
        $notification =   $this->notificationService->sendToUserById($userId, 'Notification Title', 'Notification Body');
        return $notification;
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
    public function verifyPhoneOtp(Request $request)
{
    $validated = $request->validate([
        'email' => 'required|email',
        'sms_code' => 'required|digits:6',
    ]);

    try {
        $user = \App\Models\User::where('email', $validated['email'])->first();
        if (!$user) {
            return ResponseHelper::error('User not found', 404);
        }

        if ($user->sms_code !== $validated['sms_code']) {
            return ResponseHelper::error('Invalid verification code');
        }

        $user->is_number_verified = true;
        $user->sms_code = null;
        $user->save();

        return ResponseHelper::success($user, 'Phone number verified successfully', 200);
    } catch (\Throwable $e) {
        Log::error('Phone OTP verify error: ' . $e->getMessage());
        return ResponseHelper::error('Phone verification failed');
    }
}

    public function login(LoginRequest $request)
    {
        try {
            $user = $this->userService->login($request->validated());
            $userd = $user['user'];

            // if (count($user['virtual_accounts']) <= 7) {
            //     return ResponseHelper::error('Please try again after 5-10 minutes', 401);
            // }
            // Log::info('User Logged In:', [
            //     'user' => $user,
            //     'request_headers' => request()->headers->all()
            // ]);
            // Detect device info
            $deviceId = $request->header('Device-ID'); // you can send this from app or frontend
            $userAgent = $request->header('User-Agent') ?? request()->userAgent();
            $ipAddress = $request->ip();

            // Fallback if no device_id passed
            if (!$deviceId) {
                $deviceId = sha1($userAgent . $ipAddress . $userd['id']);
            }

            // Check if this device already exists
            $existingDevice = \App\Models\UserDevice::where('device_id', $deviceId)
                ->where('user_id', $userd['id'])
                ->first();

            if (!$existingDevice) {
                \App\Models\UserDevice::create([
                    'user_id' => $userd['id'],
                    'device_id' => $deviceId,
                    'device_name' => $request->header('Device-Name') ?? 'Unknown Device',
                    'device_type' => $request->header('Device-Type') ?? 'Unknown',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                // Send notification for new device
                $this->notificationService->sendToUserById($userd['id'], 'New Device Login', 'Your account was accessed from a new device.');
                \App\Models\UserNotification::create([
                    'user_id' => $userd['id'],
                    'title' => 'New Device Login',
                    'message' => 'Your account was accessed from a new device.',
                ]);
                
           
            }
                 $this->notificationService->sendToUserById($userd['id'], 'Login Notification', 'You logged in successfully');
                UserNotification::create([
                    'user_id' => $userd['id'],
                    'title' => 'Login Notification',
                    'message' => 'You logged in successfully'
                ]);
            UserActivityHelper::LoggedInUserActivity('User logged in');
            $data = [
                    'user' => $user['user'],
                    'assets' => $user['virtual_accounts'],
                    'token' => $user['token']
                ];
            return ResponseHelper::success($data, 'User logged in successfully', 200);
        } catch (\Exception $e) {
            Log::error('Login Error:', ['error' => $e->getMessage()]);
            return ResponseHelper::error($e->getMessage());
        }
    }
    // app/Http/Controllers/AuthController.php (excerpt)
    public function adminLogin(Request $request)
    {
        try {
            $data = $request->validate([
                'email'    => ['required', 'email'],
                'password' => ['required', 'string', 'min:6'],
            ]);

            // enrich payload with context (no Request in service)
            $payload = array_merge($data, [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $resp = $this->service->adminLogin($payload);
            return ResponseHelper::success($resp, $resp['message'] ?? 'OTP sent', 200);
        } catch (ValidationException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return ResponseHelper::error('Admin login failed: ' . $e->getMessage(), 422);
        }
    }

    /** Step 1b: Resend OTP (authorized by temp token with ability 2fa:pending) */
    public function resendOtpAdmin(Request $request)
    {
        try {
            $this->service->resendOtp(
                $request->user(),           // Sanctum user from temp token
                $request->ip(),
                $request->userAgent()
            );
            return ResponseHelper::success(['ok' => true], 'OTP resent to email', 200);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 429);
        }
    }

    /** Step 2: Verify OTP, revoke temp tokens, issue real admin token */
    public function verifyOtpAdmin(Request $request)
    {
        try {
            $validated = $request->validate([
                'otp' => ['required', 'digits:6'],
            ]);

            $resp = $this->service->verifyOtpAndIssueToken($request->user(), $validated['otp']);
            return ResponseHelper::success($resp, $resp['message'] ?? 'Logged in', 200);
        } catch (ValidationException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return ResponseHelper::error('OTP verification failed: ' . $e->getMessage(), 422);
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
            UserActivityHelper::LoggedInUserActivity('User changed their password');
            return ResponseHelper::success($user, 'Password changed successfully', 200);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage());
        }
    }
}
