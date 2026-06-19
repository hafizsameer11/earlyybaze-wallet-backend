<?php

namespace App\Repositories\V3;

use App\Models\NairaWallet;
use App\Models\User;
use App\Models\UserAccount;

class V3AuthRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByUserCode(string $userCode): ?User
    {
        return User::where('user_code', $userCode)->first();
    }

    public function findV3UserByEmail(string $email): ?User
    {
        return User::where('email', $email)
            ->where('wallet_flow_version', 'v3')
            ->first();
    }

    public function create(array $data): User
    {
        if (isset($data['profile_picture']) && $data['profile_picture']) {
            $path = $data['profile_picture']->store('profile_picture', 'public');
            $data['profile_picture'] = $path;
        }

        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            if ($existingUser->otp_verified && $existingUser->wallet_flow_version !== 'v3') {
                throw new \Exception('User already registered. Use the standard login flow.');
            }

            if ($existingUser->otp_verified && $existingUser->wallet_flow_version === 'v3') {
                throw new \Exception('User already registered.');
            }

            $existingUser->update($data);

            return $existingUser->fresh();
        }

        return User::create($data);
    }

    public function createNairaWallet(User $user): void
    {
        if (NairaWallet::where('user_id', $user->id)->exists()) {
            return;
        }

        NairaWallet::create([
            'user_id' => $user->id,
            'balance' => 0,
        ]);
    }

    public function createUserAccount(User $user, string $accountNumber): void
    {
        if ($user->userAccount()->exists()) {
            return;
        }

        UserAccount::create([
            'user_id' => $user->id,
            'account_number' => $accountNumber,
        ]);
    }
}
