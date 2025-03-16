<?php

namespace App\Repositories;

use App\Models\Transaction;

class transactionRepository
{
    public function all()
    {
        $transactions = Transaction::wiht('user')->get()->map(function ($transaction) {
            return [
                'username' => $transaction->user->name ?? 'Unknown User', // Assuming a relation exists
                'transaction_type' => $transaction->type, // Assuming `type` exists in DB
                'asset' => $transaction->currency ?? 'BTC', // Default to BTC if not set
                'network' => $transaction->network ?? 'Bitcoin',
                'amount' => $transaction->amount . ' ' . $transaction->asset,
                'amountUSD' => '$' . number_format($transaction->amount * 50000, 2), // Assuming BTC/USD rate
                'status' => $transaction->status ?? 'pending',
                'fees' => $transaction->fees ?? '0',
                'feesUSD' => '$' . number_format(($transaction->fees ?? 0.0000012) * 50000, 2),
                'date' => $transaction->created_at->format('m-d-Y'),
                'time' => $transaction->created_at->format('h:i A'),
            ];
        });
    }

    public function find($id)
    {
        // Add logic to find data by ID
    }
    public function getTransactionsForUser($user_id)
    {
        return Transaction::where('user_id', $user_id)->with('user')->get();
    }
    public function create(array $data)
    {
        return Transaction::create($data);
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
