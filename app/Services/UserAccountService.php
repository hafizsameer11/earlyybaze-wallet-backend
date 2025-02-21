<?php

namespace App\Services;

use App\Repositories\UserAccountRepository;

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
}