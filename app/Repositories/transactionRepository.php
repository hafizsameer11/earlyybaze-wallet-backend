<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\VirtualAccount;

class transactionRepository
{
    public function all()
    {
        $totalTransactions = Transaction::count();
        $totalWallets = VirtualAccount::count();

        $transactions = Transaction::with('user')->orderBy('created_at', 'desc')->get();
        return ['transactions' => $transactions, 'totalTransactions' => $totalTransactions, 'totalWallets' => $totalWallets];
    }

    public function find($id)
    {
        // Add logic to find data  by ID
    }
    public function getTransactionsForUser($user_id)
    {
        $totalTransactions = Transaction::where('user_id', $user_id)->count();
        $totalWallets = VirtualAccount::where('user_id', $user_id)->count();
        $transactions = Transaction::where('user_id', $user_id)->with('user')->get();
        return ['transactions' => $transactions, 'totalTransactions' => $totalTransactions, 'totalWallets' => $totalWallets];
    }
    public function getTransactionnsForUserWithCurrency($user_id, $currency)
    {
        $totalTransactions = Transaction::where('user_id', $user_id)->where('currency', $currency)->count();
        $totalWallets = VirtualAccount::where('user_id', $user_id)->count();
        $transactions = Transaction::where('user_id', $user_id)->with('user')->get();
        return ['transactions' => $transactions, 'totalTransactions' => $totalTransactions, 'totalWallets' => $totalWallets];
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
