<?php

namespace App\Services;

use App\Repositories\UserAccountRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserAccountService
{
    protected $UserAccountRepository;

    public function __construct(UserAccountRepository $UserAccountRepository)
    {
        $this->UserAccountRepository = $UserAccountRepository;
    }

    public function all()
    {
        return $this->UserAccountRepository->all();
    }

    public function find($id)
    {
        return $this->UserAccountRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->UserAccountRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->UserAccountRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->UserAccountRepository->delete($id);
    }

    public function getBalance(){
        try{
            $user=Auth::user();
            return $this->UserAccountRepository->getUserBalance($user->id);
        }catch(Exception $e){
            Log::error('Get balance error: ' . $e->getMessage());
            throw new Exception('Get balance failed.');
        }
    }
}
