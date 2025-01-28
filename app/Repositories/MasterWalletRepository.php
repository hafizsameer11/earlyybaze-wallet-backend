<?php

namespace App\Repositories;

use App\Models\MasterWallet;

class MasterWalletRepository
{
    public function create(array $data): MasterWallet
    {
        return MasterWallet::create($data);
    }

    public function getByBlockchain(string $blockchain): ?MasterWallet
    {
        return MasterWallet::where('blockchain', $blockchain)->first();
    }

    public function getAll(): iterable
    {
        return MasterWallet::all();
    }
}
