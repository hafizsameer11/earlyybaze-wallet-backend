<?php

namespace App\Repositories;

use App\Models\ResetPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ResetPasswordRepository
{

    public function forgetPassword(User $user)
    {
        $resetPassword = ResetPassword::create([
            'email' => $user->email,
            'otp' => rand(100000, 999999),
            'user_id' => $user->id,
        ]);

        return $resetPassword;
    }
    public function verifyForgetPassswordOtp(User $user, string $otp)
    {
        $resetPassword = ResetPassword::where('user_id', $user->id)->where('otp', $otp)->first();
        return $resetPassword ? true : false;
    }
    public function resetPassword(User $user, string $password)
    {
        $user->password = Hash::make($password);
        $user->save();
        ResetPassword::where('user_id', $user->id)->delete();
        return $user;
    }
}
