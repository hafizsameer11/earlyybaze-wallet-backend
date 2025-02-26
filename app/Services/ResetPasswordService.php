<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Repositories\ResetPasswordRepository;
use App\Repositories\UserRepository;
use Exception;
use Illuminate\Support\Facades\Mail;

class ResetPasswordService
{
    protected $ResetPasswordRepository;
    protected $userRepository;

    public function __construct(ResetPasswordRepository $ResetPasswordRepository, UserRepository $userRepository)
    {
        $this->ResetPasswordRepository = $ResetPasswordRepository;
        $this->userRepository = $userRepository;
    }

    public function forgetPassword(string $email)
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            if (!$user) {
                throw new Exception('User not found');
            }
            $resetPassword = $this->ResetPasswordRepository->forgetPassword($user);
            Mail::to($user->email)->send(new OtpMail($resetPassword->otp));
            return $resetPassword;
        } catch (Exception $e) {
            throw new Exception('Forget password failed');
        }
    }
    public function verifyForgetPassswordOtp(string $email, string $otp)
    {
        $user = $this->userRepository->findByEmail($email);
        $verified = $this->ResetPasswordRepository->verifyForgetPassswordOtp($user, $otp);
        if (!$verified) {
            throw new Exception('Invalid OTP');
        }
        return true;
    }
    public function resetPassword(string $email, string $password)
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            throw new Exception('User not found');
        }
        return $this->ResetPasswordRepository->resetPassword($user, $password);
    }
}
