<?php

namespace App\Services;

use App\Repositories\BankAccountRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BankAccountService
{
    protected $BankAccountRepository;

    public function __construct(BankAccountRepository $BankAccountRepository)
    {
        $this->BankAccountRepository = $BankAccountRepository;
    }

    public function all()
    {
        return $this->BankAccountRepository->all();
    }

    public function find($id)
    {

        try {
            return $this->BankAccountRepository->find($id);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new Exception('Bank Account Not Found.');
        }
    }
    // public function getFor
    public function getforUser()
    {
        try {
            $user = Auth::user();
            return $this->BankAccountRepository->getForUser($user->id);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new Exception('Bank Account Not Found.');
        }
    }

    public function create(array $data)
    {
        try {
            $user = Auth::user();
            $data['user_id'] = $user->id;
            $bankAccount = $this->BankAccountRepository->create($data);

            return $bankAccount;
        } catch (Exception $e) {
            Log::error('Bank account creation error: ' . $e->getMessage());
            throw new Exception('Bank account creation failed.');
        }
    }

    public function update($id, array $data)
    {

        try {
            return $this->BankAccountRepository->update($id, $data);
        } catch (Exception $e) {
            Log::error('Bank account update error: ' . $e->getMessage());
            throw new Exception('Bank account update failed.');
        }
    }

    public function delete($id)
    {
        try {
            return $this->BankAccountRepository->delete($id);
        } catch (Exception $e) {
            Log::error('Bank account deletion error: ' . $e->getMessage());
            throw new Exception('Bank account deletion failed.');
        }
    }
}
