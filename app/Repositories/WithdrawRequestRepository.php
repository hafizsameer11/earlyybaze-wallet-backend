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
        $wallet=UserAccount::where('user_id', $data['user_id'])->first();
        $data['balance_before'] = $wallet->naira_balance;
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
        
        // Update send_account if provided
        if (isset($data['send_account'])) {
            $withdraw->send_account = $data['send_account'];
        }
        
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
    /**
     * Transform withdrawal request to include formatted bank_account object
     * Uses relationship if bank_account_id exists, otherwise uses direct fields
     * Returns array representation with formatted bank_account
     */
    private function transformWithdrawRequest($withdraw)
    {
        if (!$withdraw) {
            return null;
        }

        // Get formatted bank account
        $bankAccountData = $withdraw->getFormattedBankAccount();
        
        // Convert to array and transform
        $data = $withdraw->toArray();
        
        // Remove raw bank account fields from response
        unset($data['bank_account_id']);
        unset($data['bank_account_name']);
        unset($data['bank_account_code']);
        unset($data['account_name']);
        unset($data['account_number']);
        
        // Remove bankAccount relationship if present (we'll use formatted version)
        if (isset($data['bank_account'])) {
            unset($data['bank_account']);
        }
        
        // Add formatted bank_account object (always in consistent format)
        $data['bank_account'] = $bankAccountData;
        
        return $data;
    }

    public function getwithdrawRequestStatus($id)
    {
        $withdraw = WithdrawRequest::where('id', $id)->with('bankAccount')->first();
        if (!$withdraw) {
            return null;
        }
        return $this->transformWithdrawRequest($withdraw);
    }
    
    public function getWithDrawRequestByUserId($userId)
    {
        $withdraws = WithdrawRequest::where('user_id', $userId)
            ->with('bankAccount')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $withdraws->map(function ($withdraw) {
            $transformed = $this->transformWithdrawRequest($withdraw);
            $transformed['type'] = 'withdraw';
            return $transformed;
        });
    }
    
    public function findByTransactionId($transactionId)
    {
        // return WithdrawRequest::where('transaction_id', $transactionId)->first();
    }
    
    public function getAllwithdrawRequests()
    {
        $withdraws = WithdrawRequest::where('status', '!=', 'approved')
            ->with('bankAccount', 'user')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $withdraws->map(function ($withdraw) {
            // Transform to array first
            $transformed = $this->transformWithdrawRequest($withdraw);
            
            // Include user relationship if loaded
            if ($withdraw->relationLoaded('user') && $withdraw->user) {
                $transformed['user'] = $withdraw->user->toArray();
            }
            
            return $transformed;
        });
    }
}
