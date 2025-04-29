<?php

namespace App\Repositories;

use App\Helpers\ExchangeFeeHelper;
use App\Models\DepositAddress;
use App\Models\ExchangeRate;
use App\Models\MasterWallet;
use App\Models\VirtualAccount;
use App\Models\WalletCurrency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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


    public function update(array $data, $id)
    {
        $exchangeRate = ExchangeRate::findOrFail($id);

        if (isset($data['rate']) && $data['rate'] != $exchangeRate->rate) {
         
            $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->orderBy('id', 'desc')->first();

            if (!$nairaExchangeRate) {
                throw new \Exception('NGN Exchange Rate not found.');
            }

            $data['rate_usd'] = 1 / $data['rate'];
            $usdRate = $data['rate_usd'];
            $data['rate_naira'] = $nairaExchangeRate->rate * $usdRate;
        }

        // Update the record
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
    public function calculateExchangeRate($currency, $amount, $type = null, $to = null)
    {
        Log::info("data received", [
            'currency' => $currency,
            'amount_usd_received' => $amount, // USD now
            'type' => $type,
            'to' => $to
        ]);

        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        // Convert USD amount to Coin amount
        if (bccomp($exchangeRate->rate_usd, '0', 8) == 0) {
            throw new \Exception('Invalid USD rate');
        }

        $amountCoin = bcdiv($amount, $exchangeRate->rate_usd, 8); // USD ÷ Rate to get coin
        $amountNaira = bcmul($amount, $exchangeRate->rate_naira, 8); // USD × Naira rate

        $feeSummary = null;
        if ($type == 'send' && $to) {
            $isEmail = filter_var($to, FILTER_VALIDATE_EMAIL);
            if ($isEmail) {
                $from = Auth::user()->email;
            } else {
                $walletCurrency = WalletCurrency::where('currency', $currency)->first();
                $fromWallet = MasterWallet::where('blockchain', $walletCurrency->blockchain)->first();
                $from = $fromWallet?->address;
            }
            Log::info("Calculating for currency $currency");

            $fee = ExchangeFeeHelper::caclulateFee(
                $amountCoin, // send coin amount
                $currency,
                $type,
                $isEmail ? null : 'external_transfer',
                $from,
                $to,
                auth()->id()
            );

            $feeSummary = [
                'platform_fee_usd' => $fee['platform_fee_usd'],
                'blockchain_fee_usd' => $fee['blockchain_fee_usd'],
                'total_fee_usd' => $fee['total_fee_usd'],
                'amount_after_fee' => bcsub($amount, $fee['total_fee_usd'], 8), // USD - total_fee
            ];
        }

        return [
            'amount' => $amountCoin, // <- now returning COIN amount here
            'amount_usd' => $amountCoin, // <- original USD amount
            'amount_naira' => $amountNaira,
            'fee_summary' => $feeSummary, // null if not applicable
        ];
    }
}
