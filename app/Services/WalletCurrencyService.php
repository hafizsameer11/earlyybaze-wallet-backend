<?php

namespace App\Services;

use App\Repositories\WalletCurrencyRepository;
use Exception;

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
        try {
            $walletCurrency = $this->WalletCurrencyRepository->create($data);
            return $walletCurrency;
        } catch (Exception $e) {
            throw new Exception('Currency Creation Failed');
        }
    }

    public function update($id, array $data)
    {

        try {
            return $this->WalletCurrencyRepository->update($id, $data);
        } catch (Exception $e) {
            throw new Exception('Currency Update Failed');
        }
    }

    public function delete($id)
    {
        return $this->WalletCurrencyRepository->delete($id);
    }
}
