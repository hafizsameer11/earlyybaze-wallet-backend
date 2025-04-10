<?php

namespace App\Helpers;

use App\Models\ExchangeRate;
use App\Models\Fee;
use App\Models\TransactionFee;

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
    public static function caclulateFee($amount, $currency, $type, ?string $methode = 'external_transfer', $from = null, $to = null,$userId = null)
    {
        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        $nairaExchangeRate = ExchangeRate::where('currency', 'NGN')->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        $fee = Fee::where('type', $type)->first();
        if (!$fee) {
            throw new \Exception('Fee not found');
        }

        // 1. Calculate platform fee in USD
        $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
        $percentageFeeUsd = bcmul($amountUsd, bcdiv($fee->percentage, 100, 8), 8);
        $platformFeeUsd = bcadd($fee->amount, $percentageFeeUsd, 8);

        $blockchainFeeUsd = '0';
        $nativeGasFee = '0';
        $gasFeeDetails = [];

        // 2. If external transfer, estimate and calculate gas fee
        if ($methode === 'external_transfer') {
            $fromAddress = $from ?? '0x0000000000000000000000000000000000000000';
            $toAddress = $to ?? '0x0000000000000000000000000000000000000001';

            $chain = 'ETH';
            if (str_contains($currency, '_BSC')) $chain = 'BSC';
            elseif (strtoupper($currency) === 'TRON') $chain = 'TRON';

            $gasEstimation = BlockChainHelper::estimateGasFee($fromAddress, $toAddress, $amount, strtoupper($currency), $chain);

            $gasPrice = $gasEstimation['gasPrice'];
            $gasLimit = $gasEstimation['gasLimit'];

            $gasCostWei = bcmul((string)$gasPrice, (string)$gasLimit);
            $nativeGasFee = bcdiv($gasCostWei, bcpow('10', 18), 8); // ETH or BSC units

            $nativeCurrency = str_contains($currency, '_BSC') ? 'BSC' : (strtoupper($currency) === 'TRON' ? 'TRON' : 'ETH');
            $nativeExchange = ExchangeRate::where('currency', $nativeCurrency)->first();
            if (!$nativeExchange) {
                throw new \Exception("Exchange rate not found for native currency $nativeCurrency.");
            }

            $blockchainFeeUsd = bcmul($nativeGasFee, $nativeExchange->rate_usd, 8);
            $blockchainFeeUsd = bcmul($blockchainFeeUsd, '2', 8); // Double the gas fee for buffer

            $gasFeeDetails = [
                'native_currency' => $nativeCurrency,
                'native_fee' => $nativeGasFee,
                'native_fee_doubled' => bcmul($nativeGasFee, '2', 8),
                'gas_limit' => $gasLimit,
                'gas_price' => $gasPrice
            ];
        }

        // 3. Total USD Fee
        $totalFeeUsd = bcadd($platformFeeUsd, $blockchainFeeUsd, 8);
        $totalFeeCurrency = bcdiv($totalFeeUsd, $exchangeRate->rate_usd, 8);
        $totalFeeNaira = bcmul($totalFeeUsd, $nairaExchangeRate->rate, 8);
        TransactionFee::create([
            'user_id' => $userId, // or pass user ID as parameter
            'transaction_type' => $type,
            'currency' => $currency,
            'amount' => $amount,

            'platform_fee_usd' => $platformFeeUsd,
            'blockchain_fee_usd' => $blockchainFeeUsd,
            'total_fee_usd' => $totalFeeUsd,
            'fee_currency' => $totalFeeCurrency,
            'fee_naira' => $totalFeeNaira,

            'gas_limit' => $gasFeeDetails['gas_limit'] ?? null,
            'gas_price' => $gasFeeDetails['gas_price'] ?? null,
            'native_fee' => $gasFeeDetails['native_fee'] ?? null,
            'native_fee_doubled' => $gasFeeDetails['native_fee_doubled'] ?? null,
            'native_currency' => $gasFeeDetails['native_currency'] ?? null,

            'status' => 'pending',
        ]);

        return [
            'platform_fee_usd' => $platformFeeUsd,
            'blockchain_fee_usd' => $blockchainFeeUsd,
            'total_fee_usd' => $totalFeeUsd,
            'fee_currency' => $totalFeeCurrency,
            'fee_naira' => $totalFeeNaira,
            'gas_details' => $gasFeeDetails,
        ];
    }
}
