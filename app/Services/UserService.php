<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    public function registerUser(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        $data['otp'] = rand(100000, 999999);
        $data['user_code'] = $this->generateUserCode();
        return $this->userRepository->create($data);
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
