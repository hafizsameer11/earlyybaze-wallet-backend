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
        return ExchangeRate::where('currency', 'NGN')->orderBy('id', 'desc')->get();
    }

    /** Used by v3 ZAR endpoints only — not legacy NGN routes. */
    public function allByFiatAnchor(string $fiat): \Illuminate\Database\Eloquent\Collection
    {
        $fiat = FiatExchangeHelper::normalizeFiat($fiat);

        return ExchangeRate::where('currency', $fiat)->orderByDesc('id')->get();
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
        if ($data['currency'] !== 'NGN') {
            throw new \Exception('Only NGN currency can be created with currency_id = 1.');
        }
        $data['currency_id'] = null;
        $data['rate_naira'] = $data['rate'];

        return ExchangeRate::create($data);
    }

    $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')
        ->orderBy('id', 'desc')
        ->first();

    if (! $nairaExchangeRate) {
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
        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        if (bccomp($exchangeRate->rate_usd, '0', 8) == 0) {
            throw new \Exception('Invalid USD rate');
        }

        $amountUsd = '0.00';
        $amountCoin = '0.00';
        $amountNaira = '0.00';

        if ($amount_in === 'coin') {
            $amountCoin = $amount;
            $amountUsd = bcmul($amountCoin, $exchangeRate->rate_usd, 8);
            $amountNaira = bcmul($amountCoin, $exchangeRate->rate_naira, 8);
        } else {
            $amountUsd = $amount;
            $amountCoin = bcdiv($amountUsd, $exchangeRate->rate_usd, 8);
            $amountNaira = bcmul($amountUsd, $exchangeRate->rate_naira, 8);
        }

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
                $amountCoin,
                $currency,
                $type,
                $isEmail ? null : 'external_transfer',
                $from,
                $to,
                auth()->id()
            );
            if ($fee == null) {
                $feeSummary = [
                    'platform_fee_usd'    => '0.00',
                    'blockchain_fee_usd'  => '0.00',
                    'total_fee_usd'       => '0.00',
                    'amount_after_fee'    => $amountUsd,
                ];
            } else {
                $feeSummary = [
                    'platform_fee_usd'    => $fee['platform_fee_usd'] ?? '0.00',
                    'blockchain_fee_usd'  => $fee['blockchain_fee_usd'] ?? '0.00',
                    'total_fee_usd'       => $fee['total_fee_usd'] ?? '0.00',
                    'amount_after_fee'    => bcsub($amountUsd, $fee['total_fee_usd'], 8) ?? '0.00',
                ];
            }
        }

        return [
            'amount'         => $amountCoin,
            'amount_usd'     => $amountUsd,
            'amount_naira'   => $amountNaira,
            'fee_summary'    => $feeSummary,
        ];
    }

    /** v3 only — ZAR/NGN fiat preview; legacy routes use calculateExchangeRate(). */
    public function calculateFiatExchangeRate($currency, $amount, $type = null, $to = null, $amount_in = 'usd', $fiatCurrency = 'NGN')
    {
        $fiatCurrency = FiatExchangeHelper::normalizeFiat($fiatCurrency);

        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (! $exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        if (bccomp($exchangeRate->rate_usd, '0', 8) == 0) {
            throw new \Exception('Invalid USD rate');
        }

        $amountUsd = '0.00';
        $amountCoin = '0.00';

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

            $fee = ExchangeFeeHelper::caclulateFee(
                $amountCoin,
                $currency,
                $type,
                $isEmail ? null : 'external_transfer',
                $from,
                $to,
                auth()->id()
            );
            if ($fee == null) {
                $feeSummary = [
                    'platform_fee_usd'    => '0.00',
                    'blockchain_fee_usd'  => '0.00',
                    'total_fee_usd'       => '0.00',
                    'amount_after_fee'    => $amountUsd,
                ];
            } else {
                $feeSummary = [
                    'platform_fee_usd'    => $fee['platform_fee_usd'] ?? '0.00',
                    'blockchain_fee_usd'  => $fee['blockchain_fee_usd'] ?? '0.00',
                    'total_fee_usd'       => $fee['total_fee_usd'] ?? '0.00',
                    'amount_after_fee'    => bcsub($amountUsd, $fee['total_fee_usd'], 8) ?? '0.00',
                ];
            }
        }

        return [
            'amount'         => $amountCoin,
            'amount_usd'     => $amountUsd,
            'amount_naira'   => $amountNaira,
            'amount_fiat'    => $amountFiat,
            'fiat_currency'  => $fiatCurrency,
            'fee_summary'    => $feeSummary,
        ] + ($fiatCurrency === 'ZAR' ? ['amount_zar' => $amountZar] : []);
    }
}
