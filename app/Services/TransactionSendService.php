<?php

namespace App\Services;

use App\Repositories\TransactionSendRepository;

class TransactionSendService
{
    protected $TransactionSendRepository;

    public function __construct(TransactionSendRepository $TransactionSendRepository)
    {
        $this->TransactionSendRepository = $TransactionSendRepository;
    }

    public function all()
    {
        return $this->TransactionSendRepository->all();
    }

    public function find($id)
    {
        return $this->TransactionSendRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->TransactionSendRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->TransactionSendRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->TransactionSendRepository->delete($id);
    }
    public function sendInternalTransaction(array $data)
    {
        try {
            return $this->TransactionSendRepository->sendInternalTransaction($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
