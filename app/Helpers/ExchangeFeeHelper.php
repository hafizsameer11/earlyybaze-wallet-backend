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
    public static function caclulateFee($amount, $currency, $type)
    {
        $exchangeRate = ExchangeRate::where('currency', $currency)->first();
        if (!$exchangeRate) {
            throw new \Exception('Exchange rate not found');
        }

        $fee = Fee::where('type', $type)->first();
        if (!$fee) {
            throw new \Exception('Fee not found');
        }

        // Calculate percentage of amount in USD
        $amountUsd = bcmul($amount, $exchangeRate->rate_usd, 8);
        $percentageFeeUsd = bcmul($amountUsd, bcdiv($fee->percentage, 100, 8), 8);

        // Total fee in USD = fixed + percentage
        $totalFeeUsd = bcadd($fee->amount, $percentageFeeUsd, 8);

        // Convert to target currency and Naira
        $totalFeeCurrency = bcdiv($totalFeeUsd, $exchangeRate->rate_usd, 8);
        $totalFeeNaira = bcmul($totalFeeUsd, $exchangeRate->rate_naira, 8);

        return [
            'fee_usd' => $totalFeeUsd,
            'fee_currency' => $totalFeeCurrency,
            'fee_naira' => $totalFeeNaira
        ];
    }
}
