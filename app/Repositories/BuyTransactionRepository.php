<?php

namespace App\Repositories;

use App\Models\BuyTransaction;
use App\Services\transactionService;

class BuyTransactionRepository
{
    protected $transactionService;
    public function __construct(transactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find($id)
    {
        return BuyTransaction::with('transaction', 'bankAccount')->find($id);
    }
    public function findByTransactionId($id)
    {
        return BuyTransaction::with('transaction', 'bankAccount')->where('transaction_id', $id)->first();
    }

    public function create(array $data)
    {
        $refference = 'EarlyBaze' . time();
        try {
            $transaction = $this->transactionService->create([
                'type' => 'buy',
                'amount' => $data['amount_coint'] ?? 0,
                'amount_usd' => $data['amount_usd'] ?? 0,
                'currency' => $data['currency'] ?? null,
                'network' => $data['network'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'status' => 'pending',
                'reference' => $refference,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        $data['transaction_id'] = $transaction->id;
        $data['reference'] = $refference;
        $data['status'] = 'pending';
        $buyTransaction = BuyTransaction::create($data);
        return $buyTransaction->load('transaction', 'bankAccount');
    }
    public function attachSlip($id, array $data)
    {
        $buyTransaction = BuyTransaction::find($id);
        if (!$buyTransaction) {
            throw new \Exception('Buy Transaction not found');
        }
        if (isset($data['receipt']) && $data['receipt']) {
            $path = $data['receipt']->store('receipts', 'public');
        }
        $data['receipt'] = $path;
        $buyTransaction->update($data);
        return $buyTransaction;
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
