<?php

namespace App\Services;

use App\Repositories\BuyTransactionRepository;
use Illuminate\Support\Facades\Auth;

class BuyTransactionService
{
    protected $BuyTransactionRepository;

    public function __construct(BuyTransactionRepository $BuyTransactionRepository)
    {
        $this->BuyTransactionRepository = $BuyTransactionRepository;
    }

    public function all()
    {
        return $this->BuyTransactionRepository->all();
    }
public function findByTransactionId($transactionId){
    try{
        return $this->BuyTransactionRepository->findByTransactionId($transactionId);
    }catch(\Exception $e){
        throw new \Exception($e->getMessage());
    }
}
    public function find($id)
    {
        try {
            return $this->BuyTransactionRepository->find($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function create(array $data)
    {
        try {
            $user = Auth::user();
            $data['user_id'] = $user->id;
            return $this->BuyTransactionRepository->create($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function attachSlip($id, array $data)
    {
        try {
            return $this->BuyTransactionRepository->attachSlip($id, $data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function update($id, array $data)
    {
        return $this->BuyTransactionRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->BuyTransactionRepository->delete($id);
    }
    public function getUserAssetTransactions($userId){
        try{
            return $this->BuyTransactionRepository->getUserAssetTransactions($userId);
        }catch(\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function getAllBuyRequest(){
        try{
            return $this->BuyTransactionRepository->getAllBuyRequests();
        }catch(\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
