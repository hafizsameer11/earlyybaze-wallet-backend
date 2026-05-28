<?php

namespace App\Repositories;

use App\Helpers\ExchangeFeeHelper;
use App\Support\FiatExchangeHelper;
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
        $fiat = strtoupper((string) ($data['currency'] ?? ''));
        if (! in_array($fiat, ['NGN', 'ZAR'], true)) {
            throw new \Exception('Fiat anchor rows must be NGN or ZAR when currency_id = 1.');
        }
        $data['currency_id'] = null;
        if ($fiat === 'NGN') {
            $data['rate_naira'] = $data['rate'];
        } else {
            $data['rate_zar'] = $data['rate'];
        }

        return ExchangeRate::create($data);
    }

    $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->orderBy('id', 'desc')->first();
    if (! $nairaExchangeRate) {
        throw new \Exception('NGN exchange rate not found.');
    }

    $data['rate_usd'] = 1 / $data['rate'];
    $usdRate = $data['rate_usd'];
    $data['rate_naira'] = $nairaExchangeRate->rate * $usdRate;

    $zarExchangeRate = ExchangeRate::where('currency', 'ZAR')->orderBy('id', 'desc')->first();
    if ($zarExchangeRate) {
        $data['rate_zar'] = $zarExchangeRate->rate * $usdRate;
    }

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

            $zarExchangeRate = ExchangeRate::where('currency', 'ZAR')->orderBy('id', 'desc')->first();
            if ($zarExchangeRate) {
                $data['rate_zar'] = $zarExchangeRate->rate * $usdRate;
            }
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
    public function calculateExchangeRate($currency, $amount, $type = null, $to = null, $amount_in = 'usd', $fiatCurrency = 'NGN')
    {
        $fiatCurrency = FiatExchangeHelper::normalizeFiat($fiatCurrency);
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
        $amountZar = '0.00';

        if ($amount_in === 'coin') {
            $amountCoin = $amount;
            $amountUsd = bcmul($amountCoin, $exchangeRate->rate_usd, 8);
        } else {
            $amountUsd = $amount;
            $amountCoin = bcdiv($amountUsd, $exchangeRate->rate_usd, 8);
        }

        $amountNaira = FiatExchangeHelper::usdToFiatViaCryptoRow($amountUsd, $exchangeRate, 'NGN');

        $amountZar = '0.00';
        if ($fiatCurrency === 'ZAR') {
            $amountZar = FiatExchangeHelper::usdToFiatViaCryptoRow($amountUsd, $exchangeRate, 'ZAR');
        }

        $amountFiat = $fiatCurrency === 'ZAR' ? $amountZar : $amountNaira;

        Log::info('Calculated amounts', [
            'amountCoin' => $amountCoin,
            'amountUsd' => $amountUsd,
            'amountNaira' => $amountNaira,
            'amountZar' => $amountZar,
            'fiatCurrency' => $fiatCurrency,
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
        ] + ($fiatCurrency === 'ZAR' ? [
            'amount_zar'     => $amountZar,
            'amount_fiat'    => $amountFiat,
            'fiat_currency'  => $fiatCurrency,
        ] : []);
    }
}
