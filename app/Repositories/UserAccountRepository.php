<?php

namespace App\Repositories;

use App\Models\ExchangeRate;
use App\Models\UserAccount;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use Illuminate\Support\Facades\Log;

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
        $currencies = WalletCurrency::all();;

        $userVirtualAccounts = VirtualAccount::where('user_id', $id)
            ->with('walletCurrency')

            ->get();

        $totalCryptoUsd = '0';

        $userVirtualAccounts = $userVirtualAccounts->map(function ($account) use (&$totalCryptoUsd) {
            $currency = $account->currency;
            $accountBalance = $account->available_balance ?? '0';

            $exchangeRate = ExchangeRate::where('currency', $currency)->first();
            if (!$exchangeRate || bccomp($exchangeRate->rate_usd, '0', 8) === 0) {
                Log::warning("Exchange rate missing or invalid for currency: $currency");
                $usdValue = '0';
            } else {
                $usdValue = bcmul($accountBalance, $exchangeRate->rate_usd, 8); // token * USD rate
            }

            // Accumulate total in USD
            $totalCryptoUsd = bcadd($totalCryptoUsd, $usdValue, 8);

            return [
                'id' => $account->id,
                'name' => $account->walletCurrency->name,
                'currency' => $currency,
                'blockchain' => $account->blockchain,
                'currency_id' => $account->currency_id,
                'available_balance' => $account->available_balance,
                'account_balance' => $accountBalance,
                'account_balance_usd' => $usdValue,
                'deposit_addresses' => $account->depositAddresses,
                'status' => $account->active == true ? 'active' : 'inactive',
                'wallet_currency' => [
                    'id' => $account->walletCurrency->id,
                    'price' => $exchangeRate->rate_usd,
                    'symbol' => $account->walletCurrency->symbol,
                    'naira_price' => $exchangeRate->rate_naira,
                    'name' => $account->walletCurrency->name
                ]
            ];
        });

        // Save USD sum to crypto_balance
        $userAccount->crypto_balance = $totalCryptoUsd;
        $userAccount->save();

        // Refresh to return updated record
        $userAccount->refresh();

        return [
            'userBalance' => $userAccount,
            'userVirtualAccounts' => $userVirtualAccounts
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
