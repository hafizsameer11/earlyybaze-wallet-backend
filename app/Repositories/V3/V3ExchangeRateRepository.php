<?php

namespace App\Repositories\V3;

use App\Helpers\ExchangeFeeHelper;
use App\Models\ExchangeRate;
use App\Models\MasterWallet;
use App\Models\WalletCurrency;
use App\Support\FiatExchangeHelper;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-fiat (ZAR + NGN preview) exchange rates — v3 only.
 * Legacy NGN app uses App\Repositories\ExchangeRateRepository unchanged.
 */
class V3ExchangeRateRepository
{
    public function allByFiatAnchor(string $fiat): \Illuminate\Database\Eloquent\Collection
    {
        $fiat = FiatExchangeHelper::normalizeFiat($fiat);

        return ExchangeRate::where('currency', $fiat)->orderByDesc('id')->get();
    }

    public function getByCurrency(string $currency): ExchangeRate
    {
        $exchangeRate = ExchangeRate::where('currency', $currency)->orderByDesc('created_at')->first();
        if (! $exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        return $exchangeRate;
    }

    public function calculateFiatExchangeRate(
        string $currency,
        string $amount,
        ?string $type = null,
        ?string $to = null,
        string $amount_in = 'usd',
        string $fiatCurrency = 'ZAR'
    ): array {
        $fiatCurrency = FiatExchangeHelper::normalizeFiat($fiatCurrency);

        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (! $exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        if (bccomp((string) $exchangeRate->rate_usd, '0', 8) == 0) {
            throw new \Exception('Invalid USD rate');
        }

        $amountUsd = '0.00';
        $amountCoin = '0.00';

        if ($amount_in === 'coin') {
            $amountCoin = $amount;
            $amountUsd = bcmul($amountCoin, (string) $exchangeRate->rate_usd, 8);
        } else {
            $amountUsd = $amount;
            $amountCoin = bcdiv($amountUsd, (string) $exchangeRate->rate_usd, 8);
        }

        $amountNaira = FiatExchangeHelper::usdToFiatViaCryptoRow($amountUsd, $exchangeRate, 'NGN');
        $amountZar = FiatExchangeHelper::usdToFiatViaCryptoRow($amountUsd, $exchangeRate, 'ZAR');
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
                    'platform_fee_usd' => '0.00',
                    'blockchain_fee_usd' => '0.00',
                    'total_fee_usd' => '0.00',
                    'amount_after_fee' => $amountUsd,
                ];
            } else {
                $feeSummary = [
                    'platform_fee_usd' => $fee['platform_fee_usd'] ?? '0.00',
                    'blockchain_fee_usd' => $fee['blockchain_fee_usd'] ?? '0.00',
                    'total_fee_usd' => $fee['total_fee_usd'] ?? '0.00',
                    'amount_after_fee' => bcsub($amountUsd, $fee['total_fee_usd'], 8) ?? '0.00',
                ];
            }
        }

        return [
            'amount' => $amountCoin,
            'amount_usd' => $amountUsd,
            'amount_naira' => $amountNaira,
            'amount_zar' => $amountZar,
            'amount_fiat' => $amountFiat,
            'fiat_currency' => $fiatCurrency,
            'fee_summary' => $feeSummary,
        ];
    }

    public function create(array $data): ExchangeRate
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

        $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->orderByDesc('id')->first();
        if (! $nairaExchangeRate) {
            throw new \Exception('NGN exchange rate not found.');
        }

        $data['rate_usd'] = 1 / $data['rate'];
        $usdRate = $data['rate_usd'];
        $data['rate_naira'] = $nairaExchangeRate->rate * $usdRate;

        $zarExchangeRate = ExchangeRate::where('currency', 'ZAR')->orderByDesc('id')->first();
        if ($zarExchangeRate) {
            $data['rate_zar'] = $zarExchangeRate->rate * $usdRate;
        }

        return ExchangeRate::create($data);
    }

    public function update(array $data, int $id): ExchangeRate
    {
        $exchangeRate = ExchangeRate::findOrFail($id);

        if (isset($data['rate']) && $data['rate'] != $exchangeRate->rate) {
            $anchor = strtoupper((string) $exchangeRate->currency);

            if ($anchor === 'ZAR') {
                $data['rate_zar'] = $data['rate'];
            } elseif ($anchor === 'NGN') {
                $data['rate_naira'] = $data['rate'];
            } else {
                $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->orderByDesc('id')->first();
                if (! $nairaExchangeRate) {
                    throw new \Exception('NGN Exchange Rate not found.');
                }

                $data['rate_usd'] = 1 / $data['rate'];
                $usdRate = $data['rate_usd'];
                $data['rate_naira'] = $nairaExchangeRate->rate * $usdRate;

                $zarExchangeRate = ExchangeRate::where('currency', 'ZAR')->orderByDesc('id')->first();
                if ($zarExchangeRate) {
                    $data['rate_zar'] = $zarExchangeRate->rate * $usdRate;
                }
            }
        }

        $exchangeRate->update($data);

        return $exchangeRate;
    }
}
