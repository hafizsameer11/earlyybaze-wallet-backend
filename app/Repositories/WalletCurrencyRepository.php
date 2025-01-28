<?php

namespace App\Repositories;

use App\Models\WalletCurrency;

class WalletCurrencyRepository
{
    public function all()
    {
        return WalletCurrency::all();
    }

    public function find($id)
    {
        return WalletCurrency::find($id);
    }

    public function create(array $data)
    {
        return WalletCurrency::create($data);
    }

    public function update($id, array $data)
    {
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
}
