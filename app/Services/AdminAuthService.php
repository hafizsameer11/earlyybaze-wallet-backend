<?php


namespace App\Services;

use App\Mail\AdminLoginOtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminAuthService
{
    const OTP_TTL_MIN   = 10;
    const MAX_ATTEMPTS  = 5;
    const PURPOSE       = 'admin_login';

    /**
     * Step 1: Check credentials, create temp token, generate & email OTP
     * @param array{email:string,password:string,ip?:string,user_agent?:string} $payload
     * @return array{twoFARequired:bool,temp_token?:string,message:string}
     */
    public function adminLogin(array $payload): array
    {
        $email = $payload['email'] ?? '';
        $password = $payload['password'] ?? '';
        $ip = $payload['ip'] ?? null;
        $ua = $payload['user_agent'] ?? null;
        $user = User::where('email', $email)->first();
        if (!$user) throw new Exception('User not found.');
        if ($user->role === 'user') {
            throw new Exception('User is not an admin.');
        }
        if($user->is_active === false) {
            throw new Exception('User account is inactive.');
        }
        if (!Hash::check($password, $user->password)) throw new Exception('Invalid credentials.');
        $tempToken = $user->createToken('2fa_temp', ['2fa:pending'])->plainTextToken;
        $otpPlain = (string) random_int(100000, 999999);
        $otp = OtpCode::create([
            'user_id'    => $user->id,
            'purpose'    => self::PURPOSE,
            'code'       => Hash::make($otpPlain),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MIN),
            'ip'         => $ip,
            'user_agent' => Str::limit((string) $ua, 512),
        ]);
        try {
            Mail::to($user->email)->send(
                new AdminLoginOtpMail($user->name ?? 'Admin', $otpPlain, self::OTP_TTL_MIN)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send admin login OTP: ' . $e->getMessage());
            // rollback artifacts
            $user->tokens()->where('name', '2fa_temp')->delete();
            $otp->delete();
            throw new Exception('Failed to send OTP. Try again.');
        }
        return [
            'twoFARequired' => true,
            'temp_token'    => $tempToken,
            'message'       => 'OTP sent to email',
        ];
    }

    /** Resend OTP using same temp context */
    public function resendOtp(User $user, ?string $ip = null, ?string $ua = null): void
    {
        // throttle: max 3 active OTPs within TTL window
        $recent = OtpCode::where('user_id', $user->id)
            ->where('purpose', self::PURPOSE)
            ->where('created_at', '>=', now()->subMinutes(self::OTP_TTL_MIN))
            ->count();

        if ($recent >= 3) {
            throw new Exception('Too many OTP requests. Please wait.');
        }

        $otpPlain = (string) random_int(100000, 999999);
        OtpCode::create([
            'user_id'    => $user->id,
            'purpose'    => self::PURPOSE,
            'code'       => Hash::make($otpPlain),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MIN),
            'ip'         => $ip,
            'user_agent' => Str::limit((string) $ua, 512),
        ]);

        Mail::to($user->email)->send(
            new AdminLoginOtpMail($user->name ?? 'Admin', $otpPlain, self::OTP_TTL_MIN)
        );
    }

    /**
     * Step 2: Verify OTP and exchange temp → real admin token
     * @return array{user:User,token:string,message:string}
     */
    public function verifyOtpAndIssueToken(User $user, string $otpInput): array
    {
        $otp = OtpCode::where('user_id', $user->id)
            ->where('purpose', self::PURPOSE)
            ->where('consumed', false)
            ->latest()
            ->first();

        if (!$otp) throw new Exception('OTP not found. Please request a new one.');
        if ($otp->expires_at->isPast()) throw new Exception('OTP expired. Please request a new one.');
        if ($otp->attempts >= self::MAX_ATTEMPTS) throw new Exception('Too many invalid attempts.');

        $otp->increment('attempts');

        if (!Hash::check($otpInput, $otp->code)) {
            $remaining = max(0, self::MAX_ATTEMPTS - $otp->attempts);
            throw new Exception($remaining ? "Invalid OTP. {$remaining} attempts left." : "Invalid OTP. Attempts exceeded.");
        }

        // mark consumed and revoke temp tokens
        $otp->update(['consumed' => true]);
        $user->tokens()->where('name', '2fa_temp')->delete();

        // issue real admin token
        $token = $user->createToken('auth_token', ['admin'])->plainTextToken;

        return [
            'user'    => $user,
            'token'   => $token,
            'message' => 'Admin logged in successfully',
        ];
    }
}
