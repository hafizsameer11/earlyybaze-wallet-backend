<?php

namespace App\Services;

use App\Repositories\ExchangeRateRepository;

class ExchangeRateService
{
    protected $ExchangeRateRepository;

    public function __construct(ExchangeRateRepository $ExchangeRateRepository)
    {
        $this->ExchangeRateRepository = $ExchangeRateRepository;
    }

    public function all()
    {
        try {
            return $this->ExchangeRateRepository->all();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function find($id)
    {
        try {
            return $this->ExchangeRateRepository->find($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function create(array $data)
    {
        try {
            return $this->ExchangeRateRepository->create($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    public function getByCurrency($currency)
    {
        try {
            return $this->ExchangeRateRepository->getByCurrency($currency);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return $this->ExchangeRateRepository->update( $data,$id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function delete($id)
    {
        return $this->ExchangeRateRepository->delete($id);
    }
    public function calculateExchangeRate($currency, $amount, $type = null,$to=null,$amount_in=null)
    {
        return $this->ExchangeRateRepository->calculateExchangeRate($currency, $amount, $type,$to,$amount_in);
    }
}
