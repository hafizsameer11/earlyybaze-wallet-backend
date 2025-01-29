<?php

namespace App\Services;

use App\Jobs\CreateVirtualAccount;
use App\Mail\OtpMail;
use App\Models\User;
use App\Repositories\UserRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserService
{
    protected $userRepository;
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    public function registerUser(array $data)
    {
        try {
            $data['password'] = Hash::make($data['password']);
            $data['otp'] = rand(100000, 999999);
            $data['user_code'] = $this->generateUserCode();
            $user = $this->userRepository->create($data);
            Mail::to($user->email)->send(new OtpMail($user->otp));
            return $user;
        } catch (Exception $e) {
            Log::error('User registration error: ' . $e->getMessage());
            throw new Exception('User registration failed.');
        }
        // return $user;
    }
    private function generateUserCode(): string
    {
        do {
            $randomNumber = rand(100000, 999999);
            $userCode = 'EarlyBaze' . $randomNumber;
        } while ($this->userRepository->findByUserCode($userCode));

        return $userCode;
    }
    public function verifyOtp(array $data): ?User
    {
        try {
            $user = $this->userRepository->findByEmail($data['email']);
            if (!$user) {
                throw new Exception('User not found.');
            }
            if ($user->otp !== $data['otp']) {
                throw new Exception('Invalid OTP.');
            }
            $user->otp = null;
            $user->otp_verified = true;
            $user->save();
            dispatch(new CreateVirtualAccount($user));
            return $user;
        } catch (Exception $e) {
            Log::error('OTP verification error: ' . $e->getMessage());
            throw new Exception('OTP verification failed.');
        }
    }

    public function login(array $data): ?User
    {
        try {
            $user = $this->userRepository->findByEmail($data['email']);
            if (!$user) {
                throw new Exception('User not found.');
            }
            if (!$user->otp_verified) {
                throw new Exception('OTP verification required.');
            }
            if (!Hash::check($data['password'], $user->password)) {
                throw new Exception('Invalid password.');
            }
            return $user;
        } catch (Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            throw new Exception('Login failed.');
        }
    }
    public function setPin(string $email, string $pin): ?User
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            return $this->userRepository->setPin($user, $pin);

        } catch (Exception $e) {
            Log::error('Set pin error: ' . $e->getMessage());
            throw new Exception('Set pin failed.');

        }
    }
    public function verifyPin(string $pin)
    {
        try {
            $user = Auth::user();
            $user= $this->userRepository->getById($user->id);
            $status = $this->userRepository->verifyPin($user, $pin);
            if (!$status) {
                throw new Exception('Invalid pin.');
            }
            return $user;
        } catch (Exception $e) {
            Log::error('Verify pin error: ' . $e->getMessage());
            throw new Exception('Verify pin failed.');
        }
    }
    public function resendOtp(string $email): ?User
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            $user->otp = rand(100000, 999999);
            $user->save();
            Mail::to($user->email)->send(new OtpMail($user->otp));
            return $user;
        } catch (Exception $e) {
            Log::error('Resend OTP error: ' . $e->getMessage());
            throw new Exception('Resend OTP failed.');
        }
    }
}
