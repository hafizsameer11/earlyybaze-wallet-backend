<?php

namespace App\Repositories;

use App\Models\UserAccount;

class UserAccountRepository
{
    public function all() {}

    public function find($id)
    {
        // Add logic to find data by ID
    }
    public function getUserBalance($id)
    {
        $userAccount= UserAccount::where('user_id', $id)->first();
        return $userAccount;
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
