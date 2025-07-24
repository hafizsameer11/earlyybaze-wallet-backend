<?php

namespace App\Repositories;

use App\Models\WalletCurrency;
use Exception;

class WalletCurrencyRepository
{
    public function all()
    {
        return WalletCurrency::all();
    }
    public function ngnCurrency()
    {
        return [[
            'id' => 1,
            'currency' => 'NGN',
            'symbol' => '₦',
            'blockchain' => 'NGN',
            'blockhain_name' => 'Naira',
        ]];
        // return WalletCurrency::where('currency', 'NGN')->latest()->get();
    }

    public function find($id)
    {
        return WalletCurrency::find($id);
    }

    public function create(array $data)
    {
        if (isset($data['symbol']) && $data['symbol']) {
            $path = $data['symbol']->store('wallet_symbols', 'public');

            $data['symbol'] = $path;
        }

        return WalletCurrency::create($data);
    }

    public function update($id, array $data)
    {
        //check weather it exists
        $walletCurrency = $this->find($id);

        if (!$walletCurrency) {
            throw new Exception('Wallet Currency not found');
        }
        //if symbol is present handle image
        if (isset($data['symbol']) && $data['symbol']) {
            $path = $data['symbol']->store('wallet_symbols', 'public');

            $data['symbol'] = $path;
        }


        $walletCurrency->update($data);
        return $walletCurrency;

        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }

    public function findByBlockchain($blockchain): ?WalletCurrency
    {
        return WalletCurrency::where('blockchain', $blockchain)->first();
    }
    public function getBlockChainsforCurrency($currency_id)
    {
        $currency = WalletCurrency::where('id', $currency_id)->first();
        $networks = WalletCurrency::where('currency', $currency->currency)->get();
        // return $networks;
        $networks = $networks->map(function ($network) {
            return [
                'id' => $network->id,
                'network' => $network->blockchain,
                'symbol' => $network->symbol,
                'name'=>$network->blockhain_name
            ];
        });
        return $networks;
    }
}
