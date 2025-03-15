<?php

namespace App\Repositories;

// use App\Http\Requests\WithdrawRequest;

use App\Models\WithdrawRequest;
use App\Models\WithdrawTransaction;

class WithdrawTransactionRepository
{
    protected $transactionrepository;
    public function __construct(transactionRepository $transactionrepository)
    {
        $this->transactionrepository = $transactionrepository;
    }
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }
    public function fundByTransactionId($transactionId)
    {
        return WithdrawTransaction::where('transaction_id', $transactionId)->with('withdrawRequest', 'transaction')->first();
    }

    public function create(array $data)
    {
        $withdrawRequestId = $data['withdraw_request_id'];
        $userId = $data['user_id'];
        $withdrawRequest = WithdrawRequest::where('id', $withdrawRequestId)->where('user_id', $userId)->first();
        $refference = 'EarlyBaze' . time();
        $transaction = $this->transactionrepository->create([
            'type' => 'withdrawTransaction',
            'amount' => $withdrawRequest->total,
            'user_id' => $withdrawRequest->user_id,
            'currency' => 'NGN',
            'network' => 'NGN',
            'reference' => $refference,
            'status' => $withdrawRequest->status,
            'amount_usd' => ''
        ]);
        if (!$transaction) {
            throw new \Exception('Failed to create transaction');
        }
        $withdrawTransaction = WithdrawTransaction::create([
            'withdraw_request_id' => $withdrawRequestId,
            'transaction_id' => $transaction->id,
        ]);
        return $withdrawTransaction;
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
