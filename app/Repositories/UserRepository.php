<?php

namespace App\Repositories;

use App\Models\NairaWallet;
use App\Models\User;
use App\Models\VirtualAccount;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function create(array $data): User
    {
        //check if profit_pic
        if (isset($data['profile_picture']) && $data['profile_picture']) {
            $path = $data['profile_picture']->store('profile_picture', 'public');
            $data['profile_picture'] = $path;
        }
        return User::create($data);
    }
    public function createNairaWallet(User $user)
    {
        return NairaWallet::create([
            'user_id' => $user->id,
            'balance' => 0
        ]);
    }
    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }
    public function delete(User $user): void
    {
        $user->delete();
    }
    public function getById(int $id): ?User
    {
        return User::find($id);
    }
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return User::all();
    }

    public function findByUserCode($userCode): ?User
    {
        return User::where('user_code', $userCode)->first();
    }
    public function findByEmail($email): ?User
    {
        return User::where('email', $email)->first();
    }
    public function setPin(User $user, string $pin): User
    {
        $user->pin = $pin;
        $user->save();
        return $user;
    }
    public function verifyPin(User $user, string $pin): bool
    {
        return $user->pin === $pin;
    }
    public function changePassword(string $oldPassword, string $newPassword,$userId): ?User
    {
        $user = User::find($userId);

        if (!Hash::check($oldPassword, $user->password)) {
           throw new Exception('Invalid old password');
        }
        $user->password = Hash::make($newPassword);
        $user->save();
        return $user;
    }
    public function getuserAssets($userId)
    {
        $virtualAccounts = VirtualAccount::where('user_id', $userId)->with('walletCurrency')->get();

        $virtualAccounts = $virtualAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'currency' => $account->currency,
                'blockchain' => $account->blockchain,
                'currency_id' => $account->currency_id,
                'available_balance' => $account->available_balance,
                'account_balance' => $account->account_balance,
                'wallet_currency' => [
                    'id' => $account->walletCurrency->id,
                    'price' => $account->walletCurrency->price,
                    'symbol' => $account->walletCurrency->symbol,
                    'naira_price' => $account->walletCurrency->naira_price
                ]
            ];
        });
        return $virtualAccounts;
    }
    public function walletCurrenyforUser($userId)
    {
        //get the wallet currency for the user from the virtual account having balance
        $walletCurrency = VirtualAccount::where('user_id', $userId)->where('available_balance', '>', 0)->with('walletCurrency')->get();
        $walletCurrency = $walletCurrency->map(function ($account) {
            return [
                'balance' => $account->available_balance,
                'currency' => $account->walletCurrency
            ];
        });
        return $walletCurrency;
    }
    public function getDepostiAddress($userId, $currency, $network)
    {
        $virtualAccount = VirtualAccount::where('user_id', $userId)->where('currency', $currency)->where('blockchain', $network)->with('depositAddresses')->orderBy('created_at', 'desc')->first();
        if (!$virtualAccount) {
            throw new Exception('No virtual account found');
        }
        return $virtualAccount->depositAddresses()->orderBy('created_at', 'desc')->first();
    }

    public function allwalletcurrenciesforuser($userId)
    {
        $walletCurrency = VirtualAccount::where('user_id', $userId)->with('walletCurrency')->get();
        $walletCurrency = $walletCurrency->map(function ($account) {
            return [
                'balance' => $account->available_balance,
                'currency' => $account->walletCurrency
            ];
        });
        return $walletCurrency;
    }
}
