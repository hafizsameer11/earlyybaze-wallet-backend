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
        return ExchangeRate::where('currency','NGN')->orderBy('id', 'desc')->get();
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

        $exchangeRate = ExchangeRate::where('currency', $currency)->orderBy('created_at', 'desc')->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        return $exchangeRate;
    }
   public function create(array $data)
{
    if (isset($data['currency_id']) && $data['currency_id'] == 1) {
        // Only allow NGN currency creation for ID 1
        if ($data['currency'] !== 'NGN') {
            throw new \Exception('Only NGN currency can be created with currency_id = 1.');
        }
        $data['currency_id']=null;
        // Skip USD calculation, just copy rate to rate_naira
        $data['rate_naira'] = $data['rate'];
        return ExchangeRate::create($data);
    }

    // For other currencies, continue with USD and Naira rate calculation
    $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')
        ->orderBy('id', 'desc')
        ->first();

    if (!$nairaExchangeRate) {
        throw new \Exception('NGN exchange rate not found.');
    }

    $data['rate_usd'] = 1 / $data['rate'];
    $usdRate = $data['rate_usd'];
    $data['rate_naira'] = $nairaExchangeRate->rate * $usdRate;

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
    public function calculateExchangeRate($currency, $amount, $type = null, $to = null, $amount_in = 'usd')
    {
        // Log::info("data received", [
        //     'currency' => $currency,
        //     'input_amount' => $amount,
        //     'amount_in' => $amount_in,
        //     'type' => $type,
        //     'to' => $to
        // ]);

        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        if (bccomp($exchangeRate->rate_usd, '0', 8) == 0) {
            throw new \Exception('Invalid USD rate');
        }
        $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->first();
        // Initialize vars
        $amountUsd = '0.00';
        $amountCoin = '0.00';
        $amountNaira = '0.00';

        if ($amount_in === 'coin') {
            // Coin → USD
            $amountCoin = $amount;
            $amountUsd = bcmul($amountCoin, $exchangeRate->rate_usd, 8);  // Coin × USD rate
            $amountNaira = bcmul($amountUsd, $nairaExchangeRate->rate_naira, 8); // Coin × Naira rate
        } else {
            // USD → Coin (default)
            $amountUsd = $amount;
            $amountCoin = bcdiv($amountUsd, $exchangeRate->rate_usd, 8); // USD ÷ USD rate
            $amountNaira = bcmul($amountUsd, $nairaExchangeRate->rate_naira, 8);
        }
        Log::info("Calculated amounts", [
            'amountCoin' => $amountCoin,
            'amountUsd' => $amountUsd,
            'amountNaira' => $amountNaira
        ]);
        $feeSummary = null;

        if ($type === 'send' && $to) {
            $isEmail = filter_var($to, FILTER_VALIDATE_EMAIL);
            if ($isEmail) {
                $from = Auth::user()->email;
            } else {
                $walletCurrency = WalletCurrency::where('currency', $currency)->first();
                $fromWallet = MasterWallet::where('blockchain', $walletCurrency->blockchain)->first();
                $from = $fromWallet?->address;
            }

            Log::info("Calculating fee for currency $currency from $from to $to");

            $fee = ExchangeFeeHelper::caclulateFee(
                $amountCoin, // always send fee in coin
                $currency,
                $type,
                $isEmail ? null : 'external_transfer',
                $from,
                $to,
                auth()->id()
            );
            if($fee==null){
                $feeSummary = [
                    'platform_fee_usd'    => '0.00',
                    'blockchain_fee_usd'  => '0.00',
                    'total_fee_usd'       => '0.00',
                    'amount_after_fee'    => $amountUsd, // No fee applied
                ];
            }else{
                  $feeSummary = [
                'platform_fee_usd'    => $fee['platform_fee_usd'] ?? '0.00',
                'blockchain_fee_usd'  => $fee['blockchain_fee_usd'] ?? '0.00',
                'total_fee_usd'       => $fee['total_fee_usd'] ?? '0.00',
                'amount_after_fee'    => bcsub($amountUsd, $fee['total_fee_usd'], 8) ?? '0.00', // Subtract from USD base
            ];
            }

        }

        return [
            'amount'         => $amountCoin,
            'amount_usd'     => $amount_in === 'coin' ? $amountUsd : $amountCoin,
            'amount_naira'   => $amountNaira,
            'fee_summary'    => $feeSummary,
        ];
    }
}
