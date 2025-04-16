<?php

namespace App\Repositories;

use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;

class UserAccountRepository
{
    public function all() {}

    public function find($id)
    {
        // Add logic to find data by ID
    }
    public function getUserBalance($id)
    {
        $userAccount = UserAccount::where('user_id', $id)->first();
        $currencies = ['BTC', 'ETH', 'USDT'];
        $userVirtualAccounts = VirtualAccount::where('user_id', $id)->with('walletCurrency')->whereIn('currency', $currencies)->get();
        $userVirtualAccounts = $userVirtualAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->walletCurrency->name,
                'currency' => $account->currency,
                'blockchain' => $account->blockchain,
                'currency_id' => $account->currency_id,
                'available_balance' => $account->available_balance,
                'account_balance' => $account->account_balance,
                'deposit_addresses' => $account->depositAddresses,
                'status' => $account->active == true ? 'active' : 'inactive',
                'wallet_currency' => [
                    'id' => $account->walletCurrency->id,
                    'price' => $account->walletCurrency->price,
                    'symbol' => $account->walletCurrency->symbol,
                    'naira_price' => $account->walletCurrency->naira_price,
                    'name' => $account->walletCurrency->name
                ]
            ];
        });
        // return $userAccount;
        return [
            'userBalance'=>$userAccount,
            'userVirtualAccounts'=>$userVirtualAccounts
        ];
    }

    public function create(array $data)
    {
        return UserAccount::create($data);
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
}
