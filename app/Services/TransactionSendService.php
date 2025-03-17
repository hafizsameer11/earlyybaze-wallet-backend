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
       try{
        return $this->TransactionSendRepository->find($id);
       }catch(\Exception $e){
        throw new \Exception($e->getMessage());
       }
    }
    public function findByTransactionId($transactionId){
        try{
            return $this->TransactionSendRepository->findByTransactionId($transactionId);
        }catch(\Exception $e){
            throw new \Exception($e->getMessage());
        }
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
            $transaction= $this->TransactionSendRepository->sendInternalTransaction($data);
            return $transaction;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function getTransactionforUser($user_id, $userType)
    {
        try {
            return $this->TransactionSendRepository->getTransactionforUser($user_id, $userType);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function sendOnChainTransaction(array $data)
    {
        try {
            return $this->TransactionSendRepository->sendOnChainTransaction($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
