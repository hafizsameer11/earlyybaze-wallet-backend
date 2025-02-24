<?php

namespace App\Repositories;

use App\Models\BankAccount;
use Exception;

class BankAccountRepository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
        return BankAccount::where('id', $id)->with('user')->first();
    }
    public function getForUser($userId)
    {
        return BankAccount::where('user_id', $userId)->get();
    }

    public function create(array $data)
    {
        $bankAccount = BankAccount::create($data);
        //return bankaccount with user
        return $bankAccount;
    }

    public function update($id, array $data)
    {
        //check if bank account exists
        $bankAccount = BankAccount::find($id);
        if (!$bankAccount) {
            return false;
        }
        $bankAccount = BankAccount::find($id);
        $bankAccount->update($data);
        return $bankAccount;
    }

    public function delete($id)
    {
        $bankAccount = BankAccount::find($id);
        if (!$bankAccount) {
            throw new Exception('Bank Account Not Found.');
        }
        BankAccount::destroy($id);
        return true;
    }
}
