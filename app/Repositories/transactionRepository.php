<?php

namespace App\Repositories;

use App\Models\Transaction;

class transactionRepository
{
    public function all()
    {
        return Transaction::with('user')->orderBy('created_at', 'desc')->get();
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
