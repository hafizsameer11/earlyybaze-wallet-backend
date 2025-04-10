<?php

namespace App\Helpers;

use App\Models\ExchangeRate;
use App\Models\Fee;

class ExchangeFeeHelper
{

    public static function caclulateExchangeRate($amount, $currency)
    {
        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }
        $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
        $amountNaira = bcmul($amount, $exchangeRate->rate_naira, 8);
        return [
            'amount' => $amount,
            'amount_usd' => $amountUsd,
            'amount_naira' => $amountNaira
        ];
    }
    public static function caclulateFee($amount, $currency, $type, ?string $methode = 'external_transfer', $from = null, $to = null)
    {
        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        $fee = Fee::where('type', $type)->first();
        if (!$fee) {
            throw new \Exception('Fee not found');
        }

        $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
        $percentageFeeUsd = bcmul($amountUsd, bcdiv($fee->percentage, 100, 8), 8);
        $totalFeeUsd = bcadd($fee->amount, $percentageFeeUsd, 8);

        // External Transfer Logic — estimate gas and double it
        if ($methode === 'external_transfer') {
            $fromAddress = $from ?? '0x0000000000000000000000000000000000000000';
            $toAddress = $to ?? '0x0000000000000000000000000000000000000001';

            $chain = 'ETH';
            if (str_contains($currency, '_BSC')) $chain = 'BSC';
            elseif (strtoupper($currency) === 'TRON') $chain = 'TRON';

            $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, strtoupper($currency), $chain);

            $gasPrice = $gasEstimation['gasPrice'];
            $gasLimit = $gasEstimation['gasLimit'];

            $gasCostWei = bcmul((string) $gasPrice, (string) $gasLimit);
            $gasCostInNative = bcdiv($gasCostWei, bcpow('10', 18), 8); // ETH or BSC in native units

            // Convert native fee (ETH/BSC) to USD
            $nativeCurrency = str_contains($currency, '_BSC') ? 'BSC' : (strtoupper($currency) === 'TRON' ? 'TRON' : 'ETH');
            $nativeExchange = ExchangeRate::where('currency', $nativeCurrency)->first();
            if (!$nativeExchange) {
                throw new \Exception("Exchange rate not found for native currency $nativeCurrency.");
            }

            $gasFeeUsd = bcmul($gasCostInNative, $nativeExchange->rate_usd, 8);
            $doubledGasFeeUsd = bcmul($gasFeeUsd, '2', 8); // Double it as per requirement

            // Add to total fee in USD
            $totalFeeUsd = bcadd($totalFeeUsd, $doubledGasFeeUsd, 8);
        }

        $totalFeeCurrency = bcdiv($totalFeeUsd, $exchangeRate->rate_usd, 8);
        $totalFeeNaira = bcmul($totalFeeUsd, $exchangeRate->rate_naira, 8);

        return [
            'fee_usd' => $totalFeeUsd,
            'fee_currency' => $totalFeeCurrency,
            'fee_naira' => $totalFeeNaira
        ];
    }
}
