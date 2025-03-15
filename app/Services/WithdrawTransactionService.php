<?php

namespace App\Services;

use App\Repositories\WithdrawTransactionRepository;

class WithdrawTransactionService
{
    protected $WithdrawTransactionRepository;

    public function __construct(WithdrawTransactionRepository $WithdrawTransactionRepository)
    {
        $this->WithdrawTransactionRepository = $WithdrawTransactionRepository;
    }

    public function all()
    {
        return $this->WithdrawTransactionRepository->all();
    }

    public function find($id)
    {
        return $this->WithdrawTransactionRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->WithdrawTransactionRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->WithdrawTransactionRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->WithdrawTransactionRepository->delete($id);
    }
}