<?php

namespace App\Repositories;

use App\Models\ExchangeRate;

class ExchangeRateRepository
{
    public function all()
    {
        return ExchangeRate::all();
    }

    public function find($id)
    {
        $exchangeRate = ExchangeRate::find($id);
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        return $exchangeRate;
        //    if()
    }
    public function getByCurrency($currency)
    {

        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        return $exchangeRate;
    }
    public function create(array $data)
    {
        $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->orderBy('id', 'desc')->first();
        //calculate the exchange rate in naira
        $data['rate_usd'] = 1 / $data['rate'];
        $usd_rate = $data['rate_usd'];
        $data['rate_naira'] = $nairaExchangeRate->rate * $usd_rate;
        return ExchangeRate::create($data);
    }

    public function update($id, array $data)
    {
        $exchangeRate = $this->find($id);
        $exchangeRate->update($data);
        return $exchangeRate;
    }

    public function delete($id)
    {
        // Add logic to delete data
    }
    public function changeStatus($id, $status)
    {
        $exchangeRate = $this->find($id);
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        $exchangeRate->status = $status;
        $exchangeRate->save();
        return $exchangeRate;
    }
}
