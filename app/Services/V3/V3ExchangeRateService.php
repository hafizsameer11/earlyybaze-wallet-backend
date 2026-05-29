<?php

namespace App\Services\V3;

use App\Repositories\V3\V3ExchangeRateRepository;
use Illuminate\Support\Facades\Log;

class V3ExchangeRateService
{
    public function __construct(
        protected V3ExchangeRateRepository $repository,
    ) {}

    public function allByFiatAnchor(string $fiat)
    {
        try {
            return $this->repository->allByFiatAnchor($fiat);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getByCurrency(string $currency)
    {
        try {
            return $this->repository->getByCurrency($currency);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function calculateFiatExchangeRate(
        $currency,
        $amount,
        $type = null,
        $to = null,
        $amount_in = null,
        $fiatCurrency = 'ZAR'
    ) {
        try {
            return $this->repository->calculateFiatExchangeRate(
                $currency,
                $amount,
                $type,
                $to,
                $amount_in ?? 'usd',
                $fiatCurrency
            );
        } catch (\Exception $e) {
            Log::error('V3 fiat exchange rate error: '.$e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    public function create(array $data)
    {
        try {
            return $this->repository->create($data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        try {
            return $this->repository->update($data, (int) $id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
