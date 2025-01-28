<?php

namespace App\Services;

use App\Repositories\WalletCurrencyRepository;

class WalletCurrencyService
{
    protected $WalletCurrencyRepository;

    public function __construct(WalletCurrencyRepository $WalletCurrencyRepository)
    {
        $this->WalletCurrencyRepository = $WalletCurrencyRepository;
    }

    public function all()
    {
        return $this->WalletCurrencyRepository->all();
    }

    public function find($id)
    {
        return $this->WalletCurrencyRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->WalletCurrencyRepository->create($data);
    }

    public function update($id, array $data)
    {
        return $this->WalletCurrencyRepository->update($id, $data);
    }

    public function delete($id)
    {
        return $this->WalletCurrencyRepository->delete($id);
    }
}