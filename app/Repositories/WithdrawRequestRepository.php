<?php

namespace App\Repositories;

use App\Models\UserAccount;
use App\Models\WithdrawRequest;

// use App\Models\WithdrawRequest;

class WithdrawRequestRepository
{
    protected $withdrawTransactionRepository;
    public function __construct(WithdrawTransactionRepository $withdrawTransactionRepository)
    {
        $this->withdrawTransactionRepository = $withdrawTransactionRepository;
    }
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


    public function updateStatus($id, array $data)
    {
        $withdraw = WithdrawRequest::where('id', $id)->first();
        if (!$withdraw) {
            throw new \Exception('Withdraw Request not found');
        }
        $status = $data['status'];
        $send_account = $data['send_account'];
        $withdraw->send_account = $send_account;
        // $withdraw->save();
        if ($status == 'approved') {
            $withdraw->status = 'approved';
            $withdraw->save();
            $this->withdrawTransactionRepository->create([
                'withdraw_request_id' => $withdraw->id,
                'user_id' => $withdraw->user_id
            ]);
        } elseif ($status == 'rejected') {
            $withdraw->status = 'rejected';
            $userAccount = UserAccount::where('user_id', $withdraw->user_id)->first();
            $userAccount->naira_balance = $userAccount->naira_balance + $withdraw->total;
            $withdraw->save();
            $this->withdrawTransactionRepository->create([
                'withdraw_request_id' => $withdraw->id,
                'user_id' => $withdraw->user_id
            ]);
        }
        return $withdraw;
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
        $withdraq= WithdrawRequest::where('user_id', $userId)->with('bankAccount')->orderBy('created_at', 'desc')->get();
       $withdraq=$withdraq->map(function ($withdraw) {
            $withdraw->type='withdraw';
            return $withdraw;
        });
        return $withdraq;
    }
    public function findByTransactionId($transactionId)
    {
        // return WithdrawRequest::where('transaction_id', $transactionId)->first();
    }
    public function getAllwithdrawRequests()
    {
        return WithdrawRequest::where('status','!=', 'approved')->with('bankAccount', 'user')->orderBy('created_at', 'desc')->get();
    }
}
