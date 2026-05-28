<?php

namespace App\Repositories;

use App\Models\BankAccount;
use App\Models\WithdrawRequest;
use App\Services\NotificationService;
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
        if (! empty($data['user_id'])) {
            app(NotificationService::class)->notifyUser(
                (int) $data['user_id'],
                'Bank account added',
                'A new bank account was added to your profile.',
                'bank_account'
            );
        }

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
        app(NotificationService::class)->notifyUser(
            (int) $bankAccount->user_id,
            'Bank account updated',
            'Your bank account details were updated.',
            'bank_account'
        );

        return $bankAccount;
    }

    public function delete($id)
    {
        $bankAccount = BankAccount::find($id);
        //check if bank account does have any withdraw requests
        $withdrawRequests =WithdrawRequest::where('bank_account_id', $id)->count();
        if($withdrawRequests >0){
            throw new Exception('Cannot delete bank account with existing withdraw requests. Please edit the bank account instead.');
        }
        if (!$bankAccount) {
            throw new Exception('Bank Account Not Found.');
        }
        $userId = (int) $bankAccount->user_id;
        BankAccount::destroy($id);
        app(NotificationService::class)->notifyUser(
            $userId,
            'Bank account removed',
            'A bank account was removed from your profile.',
            'bank_account'
        );

        return true;
    }
}
