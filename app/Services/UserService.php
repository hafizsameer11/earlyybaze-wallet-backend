<?php

namespace App\Services;

use App\Mail\OtpMail;
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
    }
    private function generateUserCode(): string
    {
        do {
            $randomNumber = rand(100000, 999999);
            $userCode = 'EarlyBaze' . $randomNumber;
        } while ($this->userRepository->findByUserCode($userCode));

        return $userCode;
    }
}
