<?php

namespace App\Repositories;

use App\Models\UserAccount;
use App\Models\WithdrawRequest;

class WithdrawRequestRepository
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
        $withdaaw = WithdrawRequest::create($data);
        //cut the user balance
        $userAccount = UserAccount::where('user_id', $data['user_id'])->first();
        $userAccount->naira_balance = $userAccount->naira_balance - $data['total'];
        $userAccount->save();
        return $withdaaw;
    }

    public function update($id, array $data)
    {
        // Add logic to update data
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    public function getwithdrawRequestStatus($id)
    {
        return WithdrawRequest::where('id', $id)->with('bankAccount')->first();
    }
    public function getWithDrawRequestByUserId($userId)
    {
        return WithdrawRequest::where('user_id', $userId)->with('bankAccount')->orderBy('created_at', 'desc')->get();
    }
}
