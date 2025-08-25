<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $tfa) {}

    // Admin profile: begin setup (needs admin token; 2fa not required yet)
    public function setup(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA already enabled'], 400);
        }

        $secret = $user->two_factor_secret ?? $this->tfa->generateSecret();
        $user->two_factor_secret = $secret;
        $user->save();

        $otpauth = $this->tfa->getOtpAuthUrl(config('app.name'), $user->email, $secret);
        $qr = $this->tfa->renderQrDataUri($otpauth);   // <-- use the SVG data URI method

        return response()->json([
            'secret' => $secret,
            'qr' => $qr,
            'otpauth_url' => $otpauth,
        ]);
    }

    // Admin profile: confirm setup (enter one valid code)
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => '2FA not initialized'], 400);
        }

        if (!$this->tfa->verifyCode($user->two_factor_secret, $request->code)) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        if (!$user->two_factor_recovery_codes) {
            $user->two_factor_recovery_codes = $this->tfa->generateRecoveryCodes();
        }
        $user->save();

        return response()->json([
            'message' => '2FA enabled',
            'recovery_codes' => $user->two_factor_recovery_codes,
        ]);
    }

    // Step-2 after login (uses TEMP token with ability 2fa:pending)
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required_without:recovery_code|string',
            'recovery_code' => 'nullable|string',
        ]);

        $user = $request->user();
        $token = $user->currentAccessToken();

        // Ensure this call is made with the temp token
        if (!$token || !$token->can('2fa:pending')) {
            return response()->json(['message' => 'Invalid token for 2FA verification'], 403);
        }

        if (!$user->two_factor_enabled) {
            return response()->json(['message' => '2FA not enabled'], 400);
        }

        $ok = false;

        // recovery code path
        if ($request->filled('recovery_code')) {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array($request->recovery_code, $codes, true)) {
                $user->two_factor_recovery_codes = array_values(array_diff($codes, [$request->recovery_code]));
                $user->save();
                $ok = true;
            }
        } else {
            $ok = $this->tfa->verifyCode($user->two_factor_secret, $request->code);
        }

        if (!$ok) {
            return response()->json(['message' => 'Invalid 2FA code'], 422);
        }

        // Revoke the temp token, issue the real admin token
        $token->delete();
        $newToken = $user->createToken('auth_token', ['admin', '2fa:passed'])->plainTextToken;

        return response()->json([
            'message' => '2FA verified',
            'token' => $newToken,
        ]);
    }

    // Disable 2FA (require admin token; you may also require password)
    public function disable(Request $request)
    {
        $user = $request->user();
        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json(['message' => '2FA disabled']);
    }
}
