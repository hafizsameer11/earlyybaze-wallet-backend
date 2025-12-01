<?php

namespace App\Services;

use App\Repositories\transactionRepository;
use Exception;

class transactionService
{
    protected $transactionRepository;

    public function __construct(transactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

public function all(array $params)
{
    return $this->transactionRepository->all($params);
}

    public function getTransactionsForUser($user_id)
    {
        try {
            return $this->transactionRepository->getTransactionsForUser($user_id);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    public function getTransactionnsForUserWithCurrency($user_id,$currency)
    {
        try {
            return $this->transactionRepository->getTransactionnsForUserWithCurrency($user_id, $currency);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    // public function getTransactionBytype

    public function find($id)
    {
        return $this->transactionRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->transactionRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->transactionRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->transactionRepository->delete($id);
    }
}
