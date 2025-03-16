<?php

namespace App\Repositories;

use App\Models\User;

class WalletManagementRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }

    public function create(array $data)
    {
        // Add logic to create data
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    public function getVirtualWalletsData()
    {
        $users = User::whereHas("virtualAccounts")->with('virtualAccounts.walletCurrency')->orderBy("id", "desc")->get();
        return $users;
    }
}
