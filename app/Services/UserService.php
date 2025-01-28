<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\User;
use App\Repositories\UserRepository;
use Exception;
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
            return $user;
        } catch (Exception $e) {
            Log::error('OTP verification error: ' . $e->getMessage());
            throw new Exception('OTP verification failed.');
        }
    }

    public function login(array $data)
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
}
