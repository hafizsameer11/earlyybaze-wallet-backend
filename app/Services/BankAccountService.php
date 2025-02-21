<?php

namespace App\Services;

use App\Repositories\BankAccountRepository;

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
        return $this->BankAccountRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->BankAccountRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->BankAccountRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->BankAccountRepository->delete($id);
    }
}